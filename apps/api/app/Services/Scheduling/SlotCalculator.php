<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\Tenant\AppointmentStatus;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\Professional;
use App\Models\Tenant\ProfessionalAvailability;
use App\Models\Tenant\ProfessionalUnavailability;
use Carbon\CarbonImmutable;

final class SlotCalculator
{
    /**
     * @return list<array{start_time_utc: CarbonImmutable, end_time_utc: CarbonImmutable}>
     */
    public static function getAvailableSlots(
        Professional $professional,
        CarbonImmutable $targetDate,
        string $clinicTimezone = 'America/Sao_Paulo',
        ?int $customDurationMinutes = null,
        bool $includePast = true
    ): array {
        $nowUtc = CarbonImmutable::now('UTC');
        // 1. Day of week in clinic timezone (0 = Sunday, 6 = Saturday)
        $dateInClinicTz = $targetDate->setTimezone($clinicTimezone);
        $dayOfWeek = (int) $dateInClinicTz->dayOfWeek;

        $availabilities = ProfessionalAvailability::query()
            ->where('professional_id', $professional->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        if ($availabilities->isEmpty()) {
            return [];
        }

        // 2. Fetch unavailabilities covering the target day in UTC
        $dayStartUtc = $dateInClinicTz->startOfDay()->setTimezone('UTC');
        $dayEndUtc = $dateInClinicTz->endOfDay()->setTimezone('UTC');

        $unavailabilities = ProfessionalUnavailability::query()
            ->where('professional_id', $professional->id)
            ->where('start_time_utc', '<', $dayEndUtc)
            ->where('end_time_utc', '>', $dayStartUtc)
            ->get();

        // 3. Fetch active appointments on that day in UTC
        $appointments = Appointment::query()
            ->where('professional_id', $professional->id)
            ->whereNotIn('status', [AppointmentStatus::CANCELLED->value, AppointmentStatus::NO_SHOW->value])
            ->where('start_time_utc', '<', $dayEndUtc)
            ->where('end_time_utc', '>', $dayStartUtc)
            ->get();

        $availableSlots = [];

        foreach ($availabilities as $availability) {
            $duration = $customDurationMinutes ?? (int) $availability->slot_duration_minutes;

            if ($duration <= 0) {
                $duration = 30;
            }

            $shiftStartLocal = CarbonImmutable::parse(
                $dateInClinicTz->toDateString() . ' ' . $availability->start_time,
                $clinicTimezone
            );

            $shiftEndLocal = CarbonImmutable::parse(
                $dateInClinicTz->toDateString() . ' ' . $availability->end_time,
                $clinicTimezone
            );

            $currentSlotStartLocal = $shiftStartLocal;

            while ($currentSlotStartLocal->addMinutes($duration)->lte($shiftEndLocal)) {
                $currentSlotEndLocal = $currentSlotStartLocal->addMinutes($duration);

                $slotStartUtc = $currentSlotStartLocal->setTimezone('UTC');
                $slotEndUtc = $currentSlotEndLocal->setTimezone('UTC');

                if (! $includePast && $slotStartUtc->lte($nowUtc)) {
                    $currentSlotStartLocal = $currentSlotEndLocal;

                    continue;
                }

                // Check collision with unavailabilities
                $hasUnavailabilityCollision = false;

                foreach ($unavailabilities as $unavailability) {
                    if (static::intervalsOverlap($slotStartUtc, $slotEndUtc, $unavailability->start_time_utc, $unavailability->end_time_utc)) {
                        $hasUnavailabilityCollision = true;

                        break;
                    }
                }

                if ($hasUnavailabilityCollision) {
                    $currentSlotStartLocal = $currentSlotEndLocal;

                    continue;
                }

                // Check collision with existing appointments
                $hasAppointmentCollision = false;

                foreach ($appointments as $appointment) {
                    if (static::intervalsOverlap($slotStartUtc, $slotEndUtc, $appointment->start_time_utc, $appointment->end_time_utc)) {
                        $hasAppointmentCollision = true;

                        break;
                    }
                }

                if ($hasAppointmentCollision) {
                    $currentSlotStartLocal = $currentSlotEndLocal;

                    continue;
                }

                $availableSlots[] = [
                    'start_time_utc' => $slotStartUtc,
                    'end_time_utc' => $slotEndUtc,
                ];

                $currentSlotStartLocal = $currentSlotEndLocal;
            }
        }

        return $availableSlots;
    }

    public static function intervalsOverlap(
        CarbonImmutable $startA,
        CarbonImmutable $endA,
        CarbonImmutable $startB,
        CarbonImmutable $endB
    ): bool {
        return $startA->lt($endB) && $endA->gt($startB);
    }
}

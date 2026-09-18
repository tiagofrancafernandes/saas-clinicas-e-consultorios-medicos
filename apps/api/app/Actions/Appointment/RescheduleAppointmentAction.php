<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Enums\Tenant\AppointmentStatus;
use App\Models\Tenant\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RescheduleAppointmentAction
{
    public static function execute(
        Appointment $appointment,
        CarbonImmutable $newStartUtc,
        CarbonImmutable $newEndUtc,
        ?string $newClinicRoomId = null
    ): Appointment {
        return DB::transaction(function () use ($appointment, $newStartUtc, $newEndUtc, $newClinicRoomId): Appointment {
            /** @var Appointment $lockedAppointment */
            $lockedAppointment = Appointment::query()
                ->where('id', $appointment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAppointment->status->isFinalized()) {
                throw new RuntimeException("Cannot reschedule an appointment that has already been finalized ({$lockedAppointment->status->value}).");
            }

            // 1. Professional collision check on target slot (excluding this appointment)
            $professionalCollision = Appointment::query()
                ->where('id', '!=', $lockedAppointment->id)
                ->where('professional_id', $lockedAppointment->professional_id)
                ->whereNotIn('status', [AppointmentStatus::CANCELLED->value, AppointmentStatus::NO_SHOW->value])
                ->where('start_time_utc', '<', $newEndUtc)
                ->where('end_time_utc', '>', $newStartUtc)
                ->lockForUpdate()
                ->exists();

            if ($professionalCollision) {
                throw new RuntimeException('The selected professional already has an appointment during this new time.');
            }

            // 2. Room collision check if specified or previously assigned
            $roomIdToCheck = $newClinicRoomId ?? $lockedAppointment->clinic_room_id;

            if (! empty($roomIdToCheck)) {
                $roomCollision = Appointment::query()
                    ->where('id', '!=', $lockedAppointment->id)
                    ->where('clinic_room_id', $roomIdToCheck)
                    ->whereNotIn('status', [AppointmentStatus::CANCELLED->value, AppointmentStatus::NO_SHOW->value])
                    ->where('start_time_utc', '<', $newEndUtc)
                    ->where('end_time_utc', '>', $newStartUtc)
                    ->lockForUpdate()
                    ->exists();

                if ($roomCollision) {
                    throw new RuntimeException('The clinic room is already occupied during this new time.');
                }
            }

            $lockedAppointment->update([
                'start_time_utc' => $newStartUtc,
                'end_time_utc' => $newEndUtc,
                'clinic_room_id' => $roomIdToCheck,
                'status' => AppointmentStatus::SCHEDULED,
            ]);

            return $lockedAppointment->fresh();
        });
    }
}

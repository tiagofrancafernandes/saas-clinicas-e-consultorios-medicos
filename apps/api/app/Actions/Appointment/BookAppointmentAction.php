<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Enums\Tenant\AppointmentStatus;
use App\Models\Tenant\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BookAppointmentAction
{
    /**
     * @param array{
     *     professional_id: string,
     *     patient_id: string,
     *     clinic_room_id?: string|null,
     *     appointment_type_id?: string|null,
     *     start_time_utc: CarbonImmutable,
     *     end_time_utc: CarbonImmutable,
     *     origin_timezone: string,
     *     notes?: string|null,
     *     created_by_user_id?: string|null
     * } $data
     */
    public static function execute(array $data): Appointment
    {
        return DB::transaction(function () use ($data): Appointment {
            // 1. Lock check for professional availability
            $professionalCollision = Appointment::query()
                ->where('professional_id', $data['professional_id'])
                ->whereNotIn('status', [AppointmentStatus::CANCELLED->value, AppointmentStatus::NO_SHOW->value])
                ->where('start_time_utc', '<', $data['end_time_utc'])
                ->where('end_time_utc', '>', $data['start_time_utc'])
                ->lockForUpdate()
                ->exists();

            if ($professionalCollision) {
                throw new RuntimeException('The selected professional already has an appointment during this time.');
            }

            // 2. Lock check for room collision if room specified
            if (! empty($data['clinic_room_id'])) {
                $roomCollision = Appointment::query()
                    ->where('clinic_room_id', $data['clinic_room_id'])
                    ->whereNotIn('status', [AppointmentStatus::CANCELLED->value, AppointmentStatus::NO_SHOW->value])
                    ->where('start_time_utc', '<', $data['end_time_utc'])
                    ->where('end_time_utc', '>', $data['start_time_utc'])
                    ->lockForUpdate()
                    ->exists();

                if ($roomCollision) {
                    throw new RuntimeException('The selected clinic room is already occupied during this time.');
                }
            }

            return Appointment::create([
                'professional_id' => $data['professional_id'],
                'patient_id' => $data['patient_id'],
                'clinic_room_id' => $data['clinic_room_id'] ?? null,
                'appointment_type_id' => $data['appointment_type_id'] ?? null,
                'start_time_utc' => $data['start_time_utc'],
                'end_time_utc' => $data['end_time_utc'],
                'status' => AppointmentStatus::SCHEDULED,
                'origin_timezone' => $data['origin_timezone'],
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $data['created_by_user_id'] ?? null,
            ]);
        });
    }
}

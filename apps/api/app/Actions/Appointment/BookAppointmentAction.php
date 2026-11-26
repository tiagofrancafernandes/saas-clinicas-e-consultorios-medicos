<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Actions\Package\DeductPackageCreditAction;
use App\Enums\Tenant\AppointmentStatus;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\AppointmentType;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientHealthInsurance;
use App\Models\Tenant\PatientPackage;
use App\Models\Tenant\Professional;
use App\Models\Tenant\ProfessionalUnavailability;
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
     *     parent_appointment_id?: string|null,
     *     patient_package_id?: string|null,
     *     patient_health_insurance_id?: string|null,
     *     health_insurance_auth_code?: string|null,
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
            /** @var Professional $professional */
            $professional = Professional::findOrFail($data['professional_id']);

            /** @var Patient $patient */
            $patient = Patient::findOrFail($data['patient_id']);

            $appointmentType = ! empty($data['appointment_type_id'])
                ? AppointmentType::find($data['appointment_type_id'])
                : null;

            $patientHealthInsurance = ! empty($data['patient_health_insurance_id'])
                ? PatientHealthInsurance::with('healthInsurancePlan')->find($data['patient_health_insurance_id'])
                : null;

            // 1. Business rules validation (insurances, authorizations, return limits)
            ValidateAppointmentRulesAction::execute(
                $professional,
                $patient,
                $data['start_time_utc'],
                $appointmentType,
                $patientHealthInsurance,
                $data['health_insurance_auth_code'] ?? null,
                $data['parent_appointment_id'] ?? null
            );

            // 2. Lock check for professional availability
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

            // 2b. Check professional unavailability blocks (vacations, absences, leaves)
            $unavailabilityCollision = ProfessionalUnavailability::query()
                ->where('professional_id', $data['professional_id'])
                ->where('start_time_utc', '<', $data['end_time_utc'])
                ->where('end_time_utc', '>', $data['start_time_utc'])
                ->exists();

            if ($unavailabilityCollision) {
                throw new RuntimeException('The selected professional is unavailable (blocked schedule) during this time.');
            }

            // 3. Lock check for room collision if room specified
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

            // 4. Create appointment
            $appointment = Appointment::create([
                'professional_id' => $data['professional_id'],
                'patient_id' => $data['patient_id'],
                'clinic_room_id' => $data['clinic_room_id'] ?? null,
                'appointment_type_id' => $data['appointment_type_id'] ?? null,
                'parent_appointment_id' => $data['parent_appointment_id'] ?? null,
                'patient_package_id' => $data['patient_package_id'] ?? null,
                'patient_health_insurance_id' => $data['patient_health_insurance_id'] ?? null,
                'health_insurance_auth_code' => $data['health_insurance_auth_code'] ?? null,
                'start_time_utc' => $data['start_time_utc'],
                'end_time_utc' => $data['end_time_utc'],
                'status' => AppointmentStatus::SCHEDULED,
                'origin_timezone' => $data['origin_timezone'],
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $data['created_by_user_id'] ?? null,
            ]);

            // 5. If booked via package, atomically deduct credit
            if (! empty($data['patient_package_id'])) {
                /** @var PatientPackage $patientPackage */
                $patientPackage = PatientPackage::findOrFail($data['patient_package_id']);
                DeductPackageCreditAction::execute($patientPackage, $appointment->id);
            }

            return $appointment;
        });
    }
}

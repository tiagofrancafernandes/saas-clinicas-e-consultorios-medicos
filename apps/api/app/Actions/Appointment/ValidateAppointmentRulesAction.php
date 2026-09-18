<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Enums\Tenant\AppointmentStatus;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\AppointmentType;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientHealthInsurance;
use App\Models\Tenant\Professional;
use App\Models\Tenant\ProfessionalInsurance;
use Carbon\CarbonImmutable;
use RuntimeException;

final class ValidateAppointmentRulesAction
{
    public static function execute(
        Professional $professional,
        Patient $patient,
        CarbonImmutable $startTimeUtc,
        ?AppointmentType $appointmentType = null,
        ?PatientHealthInsurance $patientHealthInsurance = null,
        ?string $healthInsuranceAuthCode = null,
        ?string $parentAppointmentId = null
    ): void {
        // 1. Health Insurance Validations
        if ($patientHealthInsurance !== null) {
            // A. Check if the professional accepts this health insurance
            $insuranceAccepted = ProfessionalInsurance::query()
                ->where('professional_id', $professional->id)
                ->where('health_insurance_id', $patientHealthInsurance->health_insurance_id)
                ->where('is_active', true)
                ->exists();

            if (!$insuranceAccepted) {
                throw new RuntimeException('The selected professional does not accept this health insurance provider.');
            }

            // B. Check if insurance card has expired
            if ($patientHealthInsurance->expiration_date !== null && $patientHealthInsurance->expiration_date->isPast()) {
                throw new RuntimeException('The patient\'s health insurance card has expired.');
            }

            // C. Check if plan requires prior authorization code
            $plan = $patientHealthInsurance->healthInsurancePlan;
            if ($plan !== null && $plan->requires_prior_authorization && empty(trim((string) $healthInsuranceAuthCode))) {
                throw new RuntimeException('The selected insurance plan requires a prior authorization code.');
            }
        }

        // 2. Return Appointment Validations
        if ($appointmentType !== null && $appointmentType->is_return) {
            if (empty($parentAppointmentId)) {
                throw new RuntimeException('A return appointment requires a valid parent appointment reference.');
            }

            $parentAppointment = Appointment::find($parentAppointmentId);

            if ($parentAppointment === null) {
                throw new RuntimeException('The referenced parent appointment does not exist.');
            }

            if ($parentAppointment->patient_id !== $patient->id) {
                throw new RuntimeException('The parent appointment belongs to a different patient.');
            }

            if ($parentAppointment->professional_id !== $professional->id) {
                throw new RuntimeException('The return appointment must be with the same professional as the parent appointment.');
            }

            if ($parentAppointment->status !== AppointmentStatus::COMPLETED) {
                throw new RuntimeException('The parent appointment must be completed before booking a return consultation.');
            }

            // Check maximum return days limit
            if ($appointmentType->max_return_days_limit !== null && $appointmentType->max_return_days_limit > 0) {
                $referenceTime = $parentAppointment->completed_at_utc ?? $parentAppointment->start_time_utc;
                $daysDifference = $referenceTime->diffInDays($startTimeUtc, false);

                if ($daysDifference > $appointmentType->max_return_days_limit) {
                    throw new RuntimeException("The return appointment exceeds the maximum allowed limit of {$appointmentType->max_return_days_limit} days from the original consultation.");
                }
            }
        }
    }
}

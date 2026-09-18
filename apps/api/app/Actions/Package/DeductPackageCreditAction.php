<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Models\Tenant\PatientCreditLedger;
use App\Models\Tenant\PatientPackage;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DeductPackageCreditAction
{
    public static function execute(PatientPackage $patientPackage, ?string $appointmentId = null): PatientPackage
    {
        return DB::transaction(function () use ($patientPackage, $appointmentId): PatientPackage {
            /** @var PatientPackage $lockedPackage */
            $lockedPackage = PatientPackage::query()
                ->where('id', $patientPackage->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPackage->expires_at_utc->isPast()) {
                throw new RuntimeException('The selected package has expired.');
            }

            if ($lockedPackage->remaining_credits <= 0) {
                throw new RuntimeException('The selected package has no remaining credits.');
            }

            $lockedPackage->decrement('remaining_credits');

            PatientCreditLedger::create([
                'patient_package_id' => $lockedPackage->id,
                'appointment_id' => $appointmentId,
                'credits_delta' => -1,
                'operation_type' => 'APPOINTMENT_DEDUCTION',
                'description' => 'Credit deducted for appointment booking.',
            ]);

            return $lockedPackage->fresh();
        });
    }
}

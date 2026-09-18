<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Models\Tenant\PatientCreditLedger;
use App\Models\Tenant\PatientPackage;
use Illuminate\Support\Facades\DB;

final class RefundPackageCreditAction
{
    public static function execute(
        PatientPackage $patientPackage,
        ?string $appointmentId = null,
        string $reason = 'Credit refunded due to appointment cancellation.'
    ): PatientPackage {
        return DB::transaction(function () use ($patientPackage, $appointmentId, $reason): PatientPackage {
            /** @var PatientPackage $lockedPackage */
            $lockedPackage = PatientPackage::query()
                ->where('id', $patientPackage->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPackage->increment('remaining_credits');

            PatientCreditLedger::create([
                'patient_package_id' => $lockedPackage->id,
                'appointment_id' => $appointmentId,
                'credits_delta' => 1,
                'operation_type' => 'REFUND',
                'description' => $reason,
            ]);

            return $lockedPackage->fresh();
        });
    }
}

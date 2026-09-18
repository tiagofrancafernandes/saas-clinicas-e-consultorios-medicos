<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Actions\Package\RefundPackageCreditAction;
use App\Actions\Waitlist\ProcessWaitlistOnCancellationAction;
use App\Enums\Tenant\AppointmentStatus;
use App\Models\Tenant\Appointment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CancelAppointmentAction
{
    public static function execute(
        Appointment $appointment,
        string $cancelledBy = 'CLINIC',
        ?string $reason = null
    ): Appointment {
        return DB::transaction(function () use ($appointment, $cancelledBy, $reason): Appointment {
            /** @var Appointment $lockedAppointment */
            $lockedAppointment = Appointment::query()
                ->where('id', $appointment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAppointment->status->isFinalized()) {
                throw new RuntimeException("Cannot cancel an appointment that has already been finalized ({$lockedAppointment->status->value}).");
            }

            $lockedAppointment->update([
                'status' => AppointmentStatus::CANCELLED,
                'notes' => $reason !== null
                    ? trim(($lockedAppointment->notes ?? '') . " [Cancelled by {$cancelledBy}: {$reason}]")
                    : $lockedAppointment->notes,
            ]);

            // Refund package credit if booked via package
            if ($lockedAppointment->patient_package_id !== null && $lockedAppointment->patientPackage !== null) {
                RefundPackageCreditAction::execute(
                    $lockedAppointment->patientPackage,
                    $lockedAppointment->id,
                    "Refund due to cancellation by {$cancelledBy}."
                );
            }

            // Trigger waitlist auto-fill
            ProcessWaitlistOnCancellationAction::execute($lockedAppointment);

            return $lockedAppointment->fresh(['patientPackage', 'patient']);
        });
    }
}

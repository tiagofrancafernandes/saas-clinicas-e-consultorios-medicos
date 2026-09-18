<?php

declare(strict_types=1);

namespace App\Actions\Waitlist;

use App\Models\Tenant\Appointment;
use App\Models\Tenant\WaitlistEntry;
use Illuminate\Database\Eloquent\Collection;

final class ProcessWaitlistOnCancellationAction
{
    /**
     * @return Collection<int, WaitlistEntry>
     */
    public static function execute(Appointment $cancelledAppointment): Collection
    {
        $appointmentDate = $cancelledAppointment->start_time_utc->toDateString();

        /** @var Collection<int, WaitlistEntry> $candidates */
        $candidates = WaitlistEntry::query()
            ->where('professional_id', $cancelledAppointment->professional_id)
            ->where('preferred_date', $appointmentDate)
            ->where('status', 'WAITING')
            ->orderBy('created_at')
            ->limit(3)
            ->get();

        if ($candidates->isEmpty()) {
            return $candidates;
        }

        foreach ($candidates as $entry) {
            $entry->update([
                'status' => 'NOTIFIED',
                'notified_at_utc' => now(),
            ]);
        }

        return $candidates;
    }
}

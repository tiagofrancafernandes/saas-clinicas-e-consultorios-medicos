<?php

declare(strict_types=1);

namespace App\Jobs\Central;

use App\Enums\Tenant\AppointmentStatus;
use App\Jobs\Tenant\DispatchAppointmentReminderJob;
use App\Models\Central\Tenant;
use App\Models\Tenant\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ScanUpcomingAppointmentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        Tenant::query()->each(function (Tenant $tenant): void {
            $tenant->run(function () use ($tenant): void {
                $noticeHours = (int) ($tenant->settings['reminder_notice_hours'] ?? 24);
                $targetWindowStart = CarbonImmutable::now()->addHours($noticeHours);
                $targetWindowEnd = $targetWindowStart->addMinutes(15);

                $appointments = Appointment::query()
                    ->where('status', AppointmentStatus::SCHEDULED)
                    ->whereBetween('start_time_utc', [$targetWindowStart, $targetWindowEnd])
                    ->with(['patient', 'professional'])
                    ->get();

                foreach ($appointments as $appointment) {
                    DispatchAppointmentReminderJob::dispatch($appointment);
                }
            });
        });
    }
}

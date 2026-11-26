<?php

declare(strict_types=1);

namespace App\Jobs\Central;

use App\Enums\Tenant\NotificationLogStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\NotificationLog;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessScheduledMessagesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const MAX_PAST_WINDOW_MINUTES = 60;

    private const BATCH_LIMIT_PER_TENANT = 100;

    public function handle(): void
    {
        $nowUtc = CarbonImmutable::now('UTC');
        $pastLimitUtc = $nowUtc->subMinutes(self::MAX_PAST_WINDOW_MINUTES);

        Tenant::query()
            ->select(['id'])
            ->chunkById(100, function ($tenants) use ($nowUtc, $pastLimitUtc): void {
                foreach ($tenants as $tenant) {
                    $this->dispatchDueMessagesForTenant($tenant, $nowUtc, $pastLimitUtc);
                }
            });
    }

    private function dispatchDueMessagesForTenant(
        Tenant $tenant,
        CarbonImmutable $nowUtc,
        CarbonImmutable $pastLimitUtc
    ): void {
        $tenant->run(function () use ($tenant, $nowUtc, $pastLimitUtc): void {
            NotificationLog::query()
                ->where('status', NotificationLogStatus::SCHEDULED)
                ->where('channel', 'WHATSAPP')
                ->where('scheduled_at_utc', '<', $pastLimitUtc)
                ->update([
                    'status' => NotificationLogStatus::EXPIRED,
                    'response_metadata' => [
                        'error' => 'Scheduled window expired before processing.',
                    ],
                ]);

            $hasDueMessages = NotificationLog::query()
                ->where('status', NotificationLogStatus::SCHEDULED)
                ->where('channel', 'WHATSAPP')
                ->where('scheduled_at_utc', '>=', $pastLimitUtc)
                ->where('scheduled_at_utc', '<=', $nowUtc)
                ->exists();

            if (!$hasDueMessages) {
                return;
            }

            $dueIds = NotificationLog::query()
                ->where('status', NotificationLogStatus::SCHEDULED)
                ->where('channel', 'WHATSAPP')
                ->where('scheduled_at_utc', '>=', $pastLimitUtc)
                ->where('scheduled_at_utc', '<=', $nowUtc)
                ->orderBy('scheduled_at_utc')
                ->limit(self::BATCH_LIMIT_PER_TENANT)
                ->pluck('id');

            foreach ($dueIds as $logId) {
                SendScheduledNotificationJob::dispatch((string) $tenant->id, (string) $logId);
            }
        });
    }
}

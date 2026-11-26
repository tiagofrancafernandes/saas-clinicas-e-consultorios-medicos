<?php

declare(strict_types=1);

namespace App\Jobs\Central;

use App\Enums\Tenant\NotificationLogStatus;
use App\Enums\Tenant\WhatsAppCategory;
use App\Models\Central\Tenant;
use App\Models\Tenant\NotificationLog;
use App\Services\Evolution\EvolutionApiClient;
use App\Services\Messaging\WhatsAppInstanceResolver;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

final class SendScheduledNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $notificationLogId,
    ) {
    }

    public function handle(
        WhatsAppInstanceResolver $resolver,
        EvolutionApiClient $client
    ): void {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($resolver, $client): void {
            $claimed = NotificationLog::query()
                ->where('id', $this->notificationLogId)
                ->where('status', NotificationLogStatus::SCHEDULED)
                ->update(['status' => NotificationLogStatus::PROCESSING]);

            if ($claimed === 0) {
                return;
            }

            $log = NotificationLog::query()->find($this->notificationLogId);

            if ($log === null) {
                return;
            }

            $categoryValue = (string) ($log->payload['category'] ?? WhatsAppCategory::DEFAULT->value);
            $category = WhatsAppCategory::tryFrom($categoryValue) ?? WhatsAppCategory::DEFAULT;
            $instance = $resolver->resolveForCategory($category);

            if ($instance === null) {
                $log->update([
                    'status' => NotificationLogStatus::FAILED,
                    'response_metadata' => [
                        'error' => 'No active default WhatsApp instance configured.',
                    ],
                ]);

                return;
            }

            $text = (string) ($log->payload['text'] ?? '');

            if ($instance->attendant_name) {
                $text = "*{$instance->attendant_name}:*\n{$text}";
            }

            try {
                $response = $client->sendText($instance, $log->recipient, $text);
                $externalId = (string) ($response['key']['id'] ?? ('wamid.' . Str::random(24)));

                $log->update([
                    'status' => NotificationLogStatus::SENT,
                    'external_message_id' => $externalId,
                    'response_metadata' => $response,
                    'sent_at_utc' => CarbonImmutable::now('UTC'),
                ]);
            } catch (Throwable $e) {
                $log->update([
                    'status' => NotificationLogStatus::FAILED,
                    'response_metadata' => [
                        'error' => $e->getMessage(),
                    ],
                ]);
            }
        });
    }
}

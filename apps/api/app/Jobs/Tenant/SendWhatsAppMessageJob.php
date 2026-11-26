<?php

declare(strict_types=1);

namespace App\Jobs\Tenant;

use App\Enums\Tenant\NotificationLogStatus;
use App\Enums\Tenant\WhatsAppCategory;
use App\Models\Tenant\NotificationLog;
use App\Services\Evolution\EvolutionApiClient;
use App\Services\Messaging\WhatsAppInstanceResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

final class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $recipientPhone,
        public readonly string $messageText,
        public readonly WhatsAppCategory $category = WhatsAppCategory::DEFAULT,
        public readonly string $type = 'GENERAL_MESSAGE',
        public readonly ?string $appointmentId = null,
        public readonly array $metadata = [],
    ) {
    }

    public function handle(
        WhatsAppInstanceResolver $resolver,
        EvolutionApiClient $client
    ): void {
        $instance = $resolver->resolveForCategory($this->category);

        if ($instance === null) {
            NotificationLog::create([
                'id' => (string) Str::uuid(),
                'appointment_id' => $this->appointmentId,
                'channel' => 'WHATSAPP',
                'type' => $this->type,
                'recipient' => $this->recipientPhone,
                'external_message_id' => null,
                'status' => NotificationLogStatus::FAILED,
                'payload' => [
                    'category' => $this->category->value,
                    'text' => $this->messageText,
                    'metadata' => $this->metadata,
                ],
                'response_metadata' => [
                    'error' => 'No active WhatsApp instance available for category or default.',
                ],
                'sent_at_utc' => now(),
            ]);

            return;
        }

        $formattedText = $this->formatMessage($instance->attendant_name, $this->messageText);

        try {
            $response = $client->sendText($instance, $this->recipientPhone, $formattedText);
            $externalId = (string) ($response['key']['id'] ?? ('wamid.' . Str::random(24)));

            NotificationLog::create([
                'id' => (string) Str::uuid(),
                'appointment_id' => $this->appointmentId,
                'channel' => 'WHATSAPP',
                'type' => $this->type,
                'recipient' => $this->recipientPhone,
                'external_message_id' => $externalId,
                'status' => NotificationLogStatus::SENT,
                'payload' => [
                    'instance_id' => $instance->id,
                    'instance_name' => $instance->instance_name,
                    'category' => $this->category->value,
                    'text' => $formattedText,
                    'metadata' => $this->metadata,
                ],
                'response_metadata' => $response,
                'sent_at_utc' => now(),
            ]);
        } catch (Throwable $e) {
            NotificationLog::create([
                'id' => (string) Str::uuid(),
                'appointment_id' => $this->appointmentId,
                'channel' => 'WHATSAPP',
                'type' => $this->type,
                'recipient' => $this->recipientPhone,
                'external_message_id' => null,
                'status' => NotificationLogStatus::FAILED,
                'payload' => [
                    'instance_id' => $instance->id,
                    'instance_name' => $instance->instance_name,
                    'category' => $this->category->value,
                    'text' => $formattedText,
                ],
                'response_metadata' => [
                    'error' => $e->getMessage(),
                ],
                'sent_at_utc' => now(),
            ]);

            throw $e;
        }
    }

    private function formatMessage(?string $attendantName, string $text): string
    {
        if ($attendantName === null || trim($attendantName) === '') {
            return $text;
        }

        return "*{$attendantName}:*\n{$text}";
    }
}

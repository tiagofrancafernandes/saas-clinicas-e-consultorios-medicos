<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Contracts\Messaging\MessagingDriverInterface;
use Illuminate\Support\Str;

final class WhatsAppDriver implements MessagingDriverInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{external_id: string, status: string, response: array<string, mixed>}
     */
    public function send(string $recipient, string $template, array $payload): array
    {
        $externalId = 'wamid.' . Str::random(32);

        return [
            'external_id' => $externalId,
            'status' => 'SENT',
            'response' => [
                'provider' => 'evolution_api',
                'recipient' => $recipient,
                'template' => $template,
                'sent_at' => now()->toIso8601String(),
            ],
        ];
    }
}

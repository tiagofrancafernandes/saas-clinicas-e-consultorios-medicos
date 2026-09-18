<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Contracts\Messaging\MessagingDriverInterface;
use Illuminate\Support\Str;

final class EmailDriver implements MessagingDriverInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{external_id: string, status: string, response: array<string, mixed>}
     */
    public function send(string $recipient, string $template, array $payload): array
    {
        $externalId = 'msg_em_'.Str::random(28);

        return [
            'external_id' => $externalId,
            'status' => 'SENT',
            'response' => [
                'provider' => 'mailpit_smtp',
                'recipient' => $recipient,
                'template' => $template,
                'sent_at' => now()->toIso8601String(),
            ],
        ];
    }
}

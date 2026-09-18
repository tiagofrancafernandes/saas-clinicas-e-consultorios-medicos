<?php

declare(strict_types=1);

namespace App\Contracts\Messaging;

interface MessagingDriverInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return array{external_id: string, status: string, response: array<string, mixed>}
     */
    public function send(string $recipient, string $template, array $payload): array;
}

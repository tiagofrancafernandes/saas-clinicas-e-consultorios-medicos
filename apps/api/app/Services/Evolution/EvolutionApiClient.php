<?php

declare(strict_types=1);

namespace App\Services\Evolution;

use App\Models\Tenant\WhatsAppInstance;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class EvolutionApiClient
{
    /**
     * @return array<string, mixed>
     */
    public function sendText(WhatsAppInstance $instance, string $number, string $text): array
    {
        $sanitizedNumber = $this->resolveRecipientNumber($number);
        $url = $this->buildUrl($instance, 'message/sendText');

        $response = $this->request($instance)->post($url, [
            'number' => $sanitizedNumber,
            'text' => $text,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                sprintf('Evolution API sendText failed (%d): %s', $response->status(), $response->body())
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    /**
     * @param  list<string>  $numbers
     * @return list<array<string, mixed>>
     */
    public function checkWhatsAppNumbers(WhatsAppInstance $instance, array $numbers): array
    {
        $normalized = array_values(array_map(
            fn (string $num): string => $this->normalizeNumber($num),
            $numbers
        ));

        $url = $this->buildUrl($instance, 'chat/whatsappNumbers');

        $response = $this->request($instance)->post($url, [
            'numbers' => $normalized,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                sprintf('Evolution API checkWhatsAppNumbers failed (%d): %s', $response->status(), $response->body())
            );
        }

        /** @var list<array<string, mixed>> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    public function checkConnectionState(WhatsAppInstance $instance): array
    {
        $url = $this->buildUrl($instance, 'instance/connectionState');

        $response = $this->request($instance)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException(
                sprintf('Evolution API checkConnectionState failed (%d): %s', $response->status(), $response->body())
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    private function request(WhatsAppInstance $instance): PendingRequest
    {
        $timeout = (int) config('services.evolution_api.timeout', 15);

        return Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders([
                'apikey' => (string) $instance->token,
            ]);
    }

    private function buildUrl(WhatsAppInstance $instance, string $endpoint): string
    {
        $baseUrl = rtrim((string) ($instance->settings['base_url'] ?? config('services.evolution_api.base_url')), '/');
        $encodedInstance = rawurlencode($instance->instance_name);

        return "{$baseUrl}/{$endpoint}/{$encodedInstance}";
    }

    private function resolveRecipientNumber(string $number): string
    {
        $forcedRecipientEnabled = filter_var(
            config('services.evolution_api.forced_recipient_enabled', false),
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $forcedRecipientEnabled) {
            return $this->normalizeNumber($number);
        }

        $forcedRecipient = $this->normalizeNumber((string) config('services.evolution_api.forced_recipient', ''));

        if ($forcedRecipient === '') {
            throw new RuntimeException('Evolution API forced_recipient must be set when forced_recipient_enabled is true.');
        }

        return $forcedRecipient;
    }

    private function normalizeNumber(string $number): string
    {
        return (string) preg_replace('/\D+/', '', $number);
    }
}

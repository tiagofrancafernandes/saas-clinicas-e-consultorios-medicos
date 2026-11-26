<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Contracts\Messaging\MessagingDriverInterface;
use App\Models\Tenant\WhatsAppInstance;
use App\Enums\Tenant\WhatsAppCategory;
use App\Services\Evolution\EvolutionApiClient;
use Illuminate\Support\Str;

final class WhatsAppDriver implements MessagingDriverInterface
{
    public function __construct(
        private readonly WhatsAppInstanceResolver $resolver,
        private readonly EvolutionApiClient $client,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{external_id: string, status: string, response: array<string, mixed>}
     */
    public function send(string $recipient, string $template, array $payload): array
    {
        $categoryValue = (string) ($payload['category'] ?? WhatsAppCategory::DEFAULT->value);
        $category = WhatsAppCategory::tryFrom($categoryValue) ?? WhatsAppCategory::DEFAULT;

        /** @var WhatsAppInstance */
        $instance = $this->resolver->resolveForCategory($category);

        if ($instance !== null) {
            $text = (string) ($payload['text'] ?? $this->renderTemplate($template, $payload));

            if ($instance->attendant_name) {
                $text = "*{$instance->attendant_name}:*\n{$text}";
            }

            $response = $this->client->sendText($instance, $recipient, $text);
            $externalId = (string) ($response['key']['id'] ?? ('wamid.' . Str::random(32)));

            return [
                'external_id' => $externalId,
                'status' => 'SENT',
                'response' => [
                    'provider' => 'evolution_api',
                    'instance_id' => $instance->id,
                    'instance_name' => $instance->instance_name,
                    'recipient' => $recipient,
                    'template' => $template,
                    'raw' => $response,
                    'sent_at' => now()->toIso8601String(),
                ],
            ];
        }

        $externalId = 'wamid.' . Str::random(32);

        return [
            'external_id' => $externalId,
            'status' => 'SENT',
            'response' => [
                'provider' => 'evolution_api_simulated',
                'recipient' => $recipient,
                'template' => $template,
                'sent_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderTemplate(string $template, array $payload): string
    {
        if ($template === 'appointment_reminder') {
            $patient = (string) ($payload['patient_name'] ?? 'Paciente');
            $doctor = (string) ($payload['doctor_name'] ?? 'Profissional');
            $time = (string) ($payload['start_time_utc'] ?? '');

            return "Olá {$patient}, lembramos da sua consulta com {$doctor} agendada para {$time}.";
        }

        return (string) ($payload['message'] ?? 'Notificação da clínica.');
    }
}

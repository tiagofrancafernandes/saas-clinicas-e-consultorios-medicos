<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tenant\WhatsAppInstance;
use App\Services\Evolution\EvolutionApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class EvolutionApiClientTest extends TestCase
{
    private EvolutionApiClient $client;

    private WhatsAppInstance $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new EvolutionApiClient();
        $this->instance = new WhatsAppInstance([
            'instance_name' => 'ClinicTestInstance',
            'token' => 'test-token',
            'settings' => [
                'base_url' => 'https://evolution-api.example.test',
            ],
        ]);
    }

    public function testSendTextUsesSanitizedOriginalNumberWhenForcedRecipientIsDisabled(): void
    {
        config([
            'services.evolution_api.forced_recipient_enabled' => false,
            'services.evolution_api.forced_recipient' => '5541999998888',
        ]);

        Http::fake([
            'https://evolution-api.example.test/message/sendText/*' => Http::response(['status' => 'PENDING'], 201),
        ]);

        $this->client->sendText($this->instance, '+55 (11) 98765-4321', 'Consulta confirmada');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://evolution-api.example.test/message/sendText/ClinicTestInstance'
                && $request['number'] === '5511987654321'
                && $request['text'] === 'Consulta confirmada');
    }

    public function testSendTextOverridesNumberWithSanitizedForcedRecipientWhenEnabled(): void
    {
        config([
            'services.evolution_api.forced_recipient_enabled' => true,
            'services.evolution_api.forced_recipient' => '+55 (41) 99999-8888',
        ]);

        Http::fake([
            'https://evolution-api.example.test/message/sendText/*' => Http::response(['status' => 'PENDING'], 201),
        ]);

        $this->client->sendText($this->instance, '+55 (11) 98765-4321', 'Mensagem de teste');

        Http::assertSent(fn (Request $request): bool => $request['number'] === '5541999998888'
                && $request['text'] === 'Mensagem de teste');
    }

    public function testSendTextTreatsStringFalseAsDisabled(): void
    {
        config([
            'services.evolution_api.forced_recipient_enabled' => 'false',
            'services.evolution_api.forced_recipient' => '5541999998888',
        ]);

        Http::fake([
            'https://evolution-api.example.test/message/sendText/*' => Http::response(['status' => 'PENDING'], 201),
        ]);

        $this->client->sendText($this->instance, '+55 (11) 98765-4321', 'Olá');

        Http::assertSent(fn (Request $request): bool => $request['number'] === '5511987654321');
    }

    public function testSendTextThrowsWhenForcedRecipientIsEnabledWithoutNumber(): void
    {
        config([
            'services.evolution_api.forced_recipient_enabled' => true,
            'services.evolution_api.forced_recipient' => null,
        ]);

        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Evolution API forced_recipient must be set when forced_recipient_enabled is true.');

        try {
            $this->client->sendText($this->instance, '5511987654321', 'Olá');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function testCheckWhatsappNumbersKeepsOriginalNumbersWhenForcedRecipientIsEnabled(): void
    {
        config([
            'services.evolution_api.forced_recipient_enabled' => true,
            'services.evolution_api.forced_recipient' => '5541999998888',
        ]);

        Http::fake([
            'https://evolution-api.example.test/chat/whatsappNumbers/*' => Http::response([
                ['exists' => true, 'number' => '5511987654321'],
            ], 200),
        ]);

        $this->client->checkWhatsAppNumbers($this->instance, ['+55 (11) 98765-4321']);

        Http::assertSent(fn (Request $request): bool => $request['numbers'] === ['5511987654321']);
    }
}

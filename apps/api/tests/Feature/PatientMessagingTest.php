<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Central\UserRole;
use App\Enums\Tenant\NotificationLogStatus;
use App\Enums\Tenant\WhatsAppCategory;
use App\Jobs\Central\ProcessScheduledMessagesJob;
use App\Jobs\Central\SendScheduledNotificationJob;
use App\Models\Central\Tenant;
use App\Models\Tenant\NotificationLog;
use App\Models\Tenant\Patient;
use App\Models\Tenant\WhatsAppInstance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PatientMessagingTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $schemas = DB::select("SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'tenant_%'");

        foreach ($schemas as $schema) {
            DB::statement("DROP SCHEMA IF EXISTS \"{$schema->schema_name}\" CASCADE");
        }

        parent::tearDown();
    }

    public function testPatientMessageCanBeSentImmediatelyAndScheduledForWorker(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);
        Sanctum::actingAs($superAdmin);

        $tenant = Tenant::query()->findOrFail(
            $this->postJson('/api/central/tenants', [
                'name' => 'Clinica Mensagens',
                'slug' => 'clinica-msg-' . strtolower(Str::random(4)),
            ])->json('data.id')
        );

        $patientId = (string) Str::uuid();
        $tenant->run(function () use ($patientId): void {
            Patient::create([
                'id' => $patientId,
                'name' => 'Carlos Mensagem',
                'phone' => '5541984402684',
            ]);

            WhatsAppInstance::create([
                'id' => (string) Str::uuid(),
                'label' => 'Default',
                'phone_number' => '5541984402684',
                'instance_name' => 'Tiago 2684 baileys',
                'token' => 'SECRET_TOKEN',
                'category' => WhatsAppCategory::DEFAULT,
                'is_default' => true,
                'active' => true,
            ]);
        });

        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->syncRoles([UserRole::CLINIC_ADMIN->value]);
        Sanctum::actingAs($admin);

        Http::fake([
            '*/message/sendText/*' => Http::response(['key' => ['id' => 'WA_MSG_1']], 201),
        ]);

        // 1. Immediate message
        $this->postJson("/api/tenant/patients/{$patientId}/messages", [
            'channel' => 'WHATSAPP',
            'number' => '5541984402684',
            'message' => 'Olá Carlos!',
            'schedule' => false,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.channel', 'WHATSAPP')
            ->assertJsonPath('data.status', 'SENT');

        // 2. Past schedule rejected (422)
        $this->postJson("/api/tenant/patients/{$patientId}/messages", [
            'channel' => 'WHATSAPP',
            'number' => '5541984402684',
            'message' => 'Passado',
            'schedule' => true,
            'scheduled_at' => CarbonImmutable::now('UTC')->subMinutes(10)->toIso8601String(),
        ], ['X-Tenant-ID' => $tenant->id])->assertStatus(422);

        // 3. Future schedule accepted with explicit timezone
        $targetTimezone = 'America/Sao_Paulo';
        $futureLocal = CarbonImmutable::now($targetTimezone)->addHours(2);
        $scheduledRes = $this->postJson("/api/tenant/patients/{$patientId}/messages", [
            'channel' => 'WHATSAPP',
            'number' => '5541984402684',
            'message' => 'Lembrete agendado',
            'schedule' => true,
            'scheduled_at' => $futureLocal->format('Y-m-d H:i:s'),
            'timezone' => $targetTimezone,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'SCHEDULED')
            ->assertJsonPath('data.payload.scheduled_timezone', $targetTimezone);

        $this->assertSame(
            $futureLocal->setTimezone('UTC')->format('Y-m-d H:i'),
            CarbonImmutable::parse((string) $scheduledRes->json('data.scheduled_at_utc'))->setTimezone('UTC')->format('Y-m-d H:i')
        );

        $scheduledId = (string) $scheduledRes->json('data.id');

        // Simulate time passing so worker picks it up
        $tenant->run(function () use ($scheduledId): void {
            NotificationLog::query()->where('id', $scheduledId)->update([
                'scheduled_at_utc' => CarbonImmutable::now('UTC')->subMinute(),
            ]);
        });

        ProcessScheduledMessagesJob::dispatchSync();

        // 4. Verify message list shows both messages as SENT with pagination metadata
        $listRes = $this->getJson("/api/tenant/patients/{$patientId}/messages?per_page=1&page=1", ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.has_more', true);

        $page2Res = $this->getJson("/api/tenant/patients/{$patientId}/messages?per_page=1&page=2", ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.has_more', false);

        $statuses = collect([...$listRes->json('data'), ...$page2Res->json('data')])->pluck('status')->all();
        $this->assertSame([
            NotificationLogStatus::SENT->value,
            NotificationLogStatus::SENT->value,
        ], $statuses);

        // 5. Already SENT message cannot be cancelled (422) and remains SENT
        $this->postJson(
            "/api/tenant/patients/{$patientId}/messages/{$scheduledId}/cancel",
            [],
            ['X-Tenant-ID' => $tenant->id]
        )
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only scheduled messages that have not been sent yet can be cancelled.');

        $tenant->run(function () use ($scheduledId): void {
            $this->assertSame(NotificationLogStatus::SENT, NotificationLog::query()->findOrFail($scheduledId)->status);
        });

        // 6. Schedule another message and cancel it before sending
        $toCancelRes = $this->postJson("/api/tenant/patients/{$patientId}/messages", [
            'channel' => 'WHATSAPP',
            'number' => '5541984402684',
            'message' => 'Mensagem para cancelar',
            'schedule' => true,
            'scheduled_at' => CarbonImmutable::now($targetTimezone)->addHours(3)->format('Y-m-d H:i:s'),
            'timezone' => $targetTimezone,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.status', NotificationLogStatus::SCHEDULED->value);

        $toCancelId = (string) $toCancelRes->json('data.id');

        $this->postJson(
            "/api/tenant/patients/{$patientId}/messages/{$toCancelId}/cancel",
            [],
            ['X-Tenant-ID' => $tenant->id]
        )
            ->assertOk()
            ->assertJsonPath('data.status', NotificationLogStatus::CANCELLED->value);

        // 6b. Cancelled message cannot be sent via send-now or cancelled again (422)
        $this->postJson(
            "/api/tenant/patients/{$patientId}/messages/{$toCancelId}/send-now",
            [],
            ['X-Tenant-ID' => $tenant->id]
        )
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only scheduled messages that have not been sent yet can be sent now.');

        $this->postJson(
            "/api/tenant/patients/{$patientId}/messages/{$toCancelId}/cancel",
            [],
            ['X-Tenant-ID' => $tenant->id]
        )
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only scheduled messages that have not been sent yet can be cancelled.');

        Http::assertNotSent(static fn ($request): bool => str_contains((string) $request->body(), 'Mensagem para cancelar'));

        // 7. Worker ignores CANCELLED messages even if scheduled_at_utc is in the past
        $tenant->run(function () use ($toCancelId): void {
            NotificationLog::query()->where('id', $toCancelId)->update([
                'scheduled_at_utc' => CarbonImmutable::now('UTC')->subMinute(),
            ]);
        });

        ProcessScheduledMessagesJob::dispatchSync();

        $tenant->run(function () use ($toCancelId): void {
            $cancelledLog = NotificationLog::query()->findOrFail($toCancelId);
            $this->assertSame(NotificationLogStatus::CANCELLED, $cancelledLog->status);
        });

        // 7b. Messages older than the past window are marked EXPIRED without sending HTTP requests
        $expiredId = (string) Str::uuid();
        $tenant->run(function () use ($patientId, $expiredId): void {
            NotificationLog::create([
                'id' => $expiredId,
                'patient_id' => $patientId,
                'channel' => 'WHATSAPP',
                'type' => 'SCHEDULED_MESSAGE',
                'recipient' => '5541984402684',
                'status' => NotificationLogStatus::SCHEDULED,
                'payload' => [
                    'text' => 'Mensagem antiga expirada',
                    'category' => WhatsAppCategory::DEFAULT->value,
                ],
                'scheduled_at_utc' => CarbonImmutable::now('UTC')->subHours(3),
            ]);
        });

        ProcessScheduledMessagesJob::dispatchSync();

        $tenant->run(function () use ($expiredId): void {
            $expiredLog = NotificationLog::query()->findOrFail($expiredId);
            $this->assertSame(NotificationLogStatus::EXPIRED, $expiredLog->status);
        });

        // 7c. Atomic claim prevents duplicate execution of SendScheduledNotificationJob
        SendScheduledNotificationJob::dispatchSync(
            (string) $tenant->id,
            $scheduledId
        );

        // 8. Schedule a message and trigger "send-now" immediately
        $sendNowRes = $this->postJson("/api/tenant/patients/{$patientId}/messages", [
            'channel' => 'WHATSAPP',
            'number' => '5541984402684',
            'message' => 'Enviar agora manualmente',
            'schedule' => true,
            'scheduled_at' => CarbonImmutable::now($targetTimezone)->addHours(4)->format('Y-m-d H:i:s'),
            'timezone' => $targetTimezone,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.status', NotificationLogStatus::SCHEDULED->value);

        $sendNowId = (string) $sendNowRes->json('data.id');

        $this->postJson(
            "/api/tenant/patients/{$patientId}/messages/{$sendNowId}/send-now",
            [],
            ['X-Tenant-ID' => $tenant->id]
        )
            ->assertOk()
            ->assertJsonPath('data.status', NotificationLogStatus::SENT->value);

        // 9. Already SENT message cannot be sent again via send-now (422)
        $this->postJson(
            "/api/tenant/patients/{$patientId}/messages/{$sendNowId}/send-now",
            [],
            ['X-Tenant-ID' => $tenant->id]
        )->assertStatus(422);
    }
}

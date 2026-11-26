<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Central\UserRole;
use App\Enums\Tenant\NotificationLogStatus;
use App\Enums\Tenant\WhatsAppCategory;
use App\Jobs\Tenant\SendWhatsAppMessageJob;
use App\Models\Central\Tenant;
use App\Models\Tenant\NotificationLog;
use App\Models\Tenant\WhatsAppInstance;
use App\Models\User;
use App\Services\Messaging\WhatsAppInstanceResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class WhatsAppEvolutionTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);
        Sanctum::actingAs($superAdmin);
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

    public function testSuperAdminCanManageMultipleWhatsappInstancesWithSingleDefaultInvariant(): void
    {
        $tenant = $this->createTenantWithSchema();

        $first = $this->postJson("/api/central/tenants/{$tenant->id}/whatsapp-instances", [
            'label' => 'Principal',
            'phone_number' => '5541984402684',
            'instance_name' => 'Tiago 2684 baileys',
            'token' => 'TOKEN_1',
            'category' => 'DEFAULT',
            'is_default' => true,
            'active' => true,
            'attendant_name' => 'Recepção',
        ]);

        $first->assertCreated()
            ->assertJsonPath('data.is_default', true);

        $second = $this->postJson("/api/central/tenants/{$tenant->id}/whatsapp-instances", [
            'label' => 'Lembretes',
            'phone_number' => '5541999990000',
            'instance_name' => 'Lembretes Instance',
            'token' => 'TOKEN_2',
            'category' => 'REMINDERS',
            'is_default' => true,
            'active' => true,
        ]);

        $second->assertCreated()
            ->assertJsonPath('data.is_default', true);

        $tenant->run(function () use ($first, $second): void {
            $this->assertFalse((bool) WhatsAppInstance::query()->find($first->json('data.id'))->is_default);
            $this->assertTrue((bool) WhatsAppInstance::query()->find($second->json('data.id'))->is_default);
        });
    }

    public function testResolverFallsBackToActiveDefaultWhenCategoryInstanceIsInactive(): void
    {
        $tenant = $this->createTenantWithSchema();

        $tenant->run(function (): void {
            $default = WhatsAppInstance::create([
                'id' => (string) Str::uuid(),
                'label' => 'Default Active',
                'phone_number' => '5541984402684',
                'instance_name' => 'DefaultInst',
                'token' => 'SECRET_1',
                'category' => WhatsAppCategory::DEFAULT,
                'is_default' => true,
                'active' => true,
            ]);

            WhatsAppInstance::create([
                'id' => (string) Str::uuid(),
                'label' => 'Inactive Marketing',
                'phone_number' => '5541999991111',
                'instance_name' => 'MktInst',
                'token' => 'SECRET_2',
                'category' => WhatsAppCategory::MARKETING,
                'is_default' => false,
                'active' => false,
            ]);

            $resolver = app(WhatsAppInstanceResolver::class);
            $resolved = $resolver->resolveForCategory(WhatsAppCategory::MARKETING);

            $this->assertNotNull($resolved);
            $this->assertSame($default->id, $resolved->id);
        });
    }

    public function testPatientWhatsappVerificationRequiresActiveDefaultInstance(): void
    {
        $tenant = $this->createTenantWithSchema();

        $statusDisabled = $this->getJson('/api/tenant/whatsapp/status', [
            'X-Tenant-ID' => $tenant->id,
        ]);
        $statusDisabled->assertOk()->assertJsonPath('enabled', false);

        $blocked = $this->postJson('/api/tenant/whatsapp/check-number', [
            'phone' => '5541984402684',
        ], ['X-Tenant-ID' => $tenant->id]);
        $blocked->assertStatus(422);

        $tenant->run(function (): void {
            WhatsAppInstance::create([
                'id' => (string) Str::uuid(),
                'label' => 'Default Active',
                'phone_number' => '5541984402684',
                'instance_name' => 'Tiago 2684 baileys',
                'token' => 'C2FBA7C967A5-4A80-A96A-EED2A0DE328D',
                'category' => WhatsAppCategory::DEFAULT,
                'is_default' => true,
                'active' => true,
            ]);
        });

        Http::fake([
            '*/chat/whatsappNumbers/*' => Http::response([
                ['exists' => true, 'jid' => '554184402684@s.whatsapp.net', 'number' => '5541984402684'],
            ], 200),
        ]);

        $statusEnabled = $this->getJson('/api/tenant/whatsapp/status', [
            'X-Tenant-ID' => $tenant->id,
        ]);
        $statusEnabled->assertOk()->assertJsonPath('enabled', true);

        $verified = $this->postJson('/api/tenant/whatsapp/check-number', [
            'phone' => '5541984402684',
        ], ['X-Tenant-ID' => $tenant->id]);

        $verified->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonPath('jid', '554184402684@s.whatsapp.net');
    }

    public function testSendWhatsappMessageJobSendsAndLogsNotification(): void
    {
        $tenant = $this->createTenantWithSchema();

        Http::fake([
            '*/message/sendText/*' => Http::response([
                'key' => ['id' => 'BAE59999TEST'],
                'status' => 'PENDING',
            ], 201),
        ]);

        $tenant->run(function (): void {
            WhatsAppInstance::create([
                'id' => (string) Str::uuid(),
                'label' => 'Reminders',
                'phone_number' => '5541984402684',
                'instance_name' => 'Tiago 2684 baileys',
                'token' => 'C2FBA7C967A5-4A80-A96A-EED2A0DE328D',
                'category' => WhatsAppCategory::REMINDERS,
                'is_default' => true,
                'active' => true,
                'attendant_name' => 'Clínica Teste',
            ]);

            SendWhatsAppMessageJob::dispatchSync(
                recipientPhone: '5541984402684',
                messageText: 'desconsiderar mensagem. isso é um teste',
                category: WhatsAppCategory::REMINDERS,
                type: 'TEST_MESSAGE',
            );

            $log = NotificationLog::query()->first();
            $this->assertNotNull($log);
            $this->assertSame(NotificationLogStatus::SENT, $log->status);
            $this->assertSame('BAE59999TEST', $log->external_message_id);
        });
    }

    private function createTenantWithSchema(): Tenant
    {
        $response = $this->postJson('/api/central/tenants', [
            'name' => 'Clinic Test ' . Str::random(5),
            'slug' => 'clinic-' . strtolower(Str::random(6)),
        ]);

        $response->assertCreated();

        return Tenant::query()->findOrFail($response->json('data.id'));
    }
}

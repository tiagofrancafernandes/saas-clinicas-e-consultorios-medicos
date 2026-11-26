<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Central\UserRole;
use App\Enums\Tenant\WhatsAppCategory;
use App\Models\Central\Tenant;
use App\Models\Tenant\WhatsAppInstance;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ClinicAndWhatsAppManagementTest extends TestCase
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

    public function testClinicOpenInfoIsReadableByStaffAndEditableOnlyByAdmin(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);
        Sanctum::actingAs($superAdmin);

        $tenantId = (string) $this->postJson('/api/central/tenants', [
            'name' => 'Clinica Aberta',
            'slug' => 'clinica-aberta-' . strtolower(Str::random(4)),
        ])->json('data.id');

        $doctor = User::factory()->create(['tenant_id' => $tenantId]);
        $doctor->syncRoles([UserRole::PROFESSIONAL->value]);

        $admin = User::factory()->create(['tenant_id' => $tenantId]);
        $admin->syncRoles([UserRole::CLINIC_ADMIN->value]);

        // Professional can read open info, cannot manage
        Sanctum::actingAs($doctor);
        $this->getJson('/api/tenant/clinic', ['X-Tenant-ID' => $tenantId])
            ->assertOk()
            ->assertJsonPath('data.name', 'Clinica Aberta')
            ->assertJsonPath('data.can_manage', false);

        $this->putJson('/api/tenant/clinic', [
            'name' => 'Hacked Name',
            'timezone' => 'UTC',
        ], ['X-Tenant-ID' => $tenantId])->assertForbidden();

        // Clinic Admin can read full info & update editable settings
        Sanctum::actingAs($admin);
        $this->putJson('/api/tenant/clinic', [
            'name' => 'Clinica Editada',
            'timezone' => 'America/Manaus',
            'settings' => [
                'reminder_notice_hours' => 12,
                'contact_phone' => '5541984402684',
            ],
        ], ['X-Tenant-ID' => $tenantId])
            ->assertOk()
            ->assertJsonPath('data.name', 'Clinica Editada')
            ->assertJsonPath('data.timezone', 'America/Manaus');
    }

    public function testClinicAdminCanManageExistingWhatsappInstancesWithoutCreating(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);
        Sanctum::actingAs($superAdmin);

        $tenant = Tenant::query()->findOrFail(
            $this->postJson('/api/central/tenants', [
                'name' => 'Clinica WA',
                'slug' => 'clinica-wa-' . strtolower(Str::random(4)),
            ])->json('data.id')
        );

        $instanceId = (string) Str::uuid();
        $tenant->run(function () use ($instanceId): void {
            WhatsAppInstance::create([
                'id' => $instanceId,
                'label' => 'Original Label',
                'phone_number' => '5541984402684',
                'instance_name' => 'Tiago 2684 baileys',
                'token' => 'SECRET_TOKEN',
                'category' => WhatsAppCategory::REMINDERS,
                'is_default' => false,
                'active' => true,
            ]);
        });

        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->syncRoles([UserRole::CLINIC_ADMIN->value]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/tenant/whatsapp/instances', ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->putJson("/api/tenant/whatsapp/instances/{$instanceId}", [
            'label' => 'Novo Label',
            'category' => 'MARKETING',
            'attendant_name' => 'Maria Atendimento',
            'is_default' => true,
            'active' => false,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.label', 'Novo Label')
            ->assertJsonPath('data.category', 'MARKETING')
            ->assertJsonPath('data.attendant_name', 'Maria Atendimento')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.active', false);

        Http::fake([
            '*/message/sendText/*' => Http::response(['key' => ['id' => 'WA_TEST_123']], 201),
        ]);

        $this->postJson("/api/tenant/whatsapp/instances/{$instanceId}/send-test", [
            'number' => '5541984402684',
            'text' => 'desconsiderar mensagem. isso é um teste',
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.key.id', 'WA_TEST_123');
    }
}

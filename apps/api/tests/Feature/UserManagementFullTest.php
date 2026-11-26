<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Central\PermissionName;
use App\Enums\Central\UserRole;
use App\Models\Central\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class UserManagementFullTest extends TestCase
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

    public function testClinicAdminCanManageUsersRolesPermissionsResetAndStatus(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);
        Sanctum::actingAs($superAdmin);

        $tenant = Tenant::query()->findOrFail(
            $this->postJson('/api/central/tenants', [
                'name' => 'Clinica Usuarios',
                'slug' => 'clinica-usr-' . strtolower(Str::random(4)),
            ])->json('data.id')
        );

        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $admin->syncRoles([UserRole::CLINIC_ADMIN->value]);
        Sanctum::actingAs($admin);

        // 1. Create new PROFESSIONAL user (creates linked Professional profile)
        $createRes = $this->postJson('/api/tenant/users', [
            'name' => 'Dra. Helena Souza',
            'email' => 'helena@clinica.com',
            'password' => 'secret123',
            'role' => UserRole::PROFESSIONAL->value,
            'specialty' => 'Psiquiatria',
            'license_number' => 'CRM/SP 998877',
            'extra_permissions' => [PermissionName::PATIENTS_CREATE->value],
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.roles.0', UserRole::PROFESSIONAL->value)
            ->assertJsonPath('data.professional.specialty', 'Psiquiatria');

        $userId = (int) $createRes->json('data.id');

        // 2. Show user details
        $this->getJson("/api/tenant/users/{$userId}", ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.email', 'helena@clinica.com')
            ->assertJsonPath('data.professional.license_number', 'CRM/SP 998877');

        // 3. Edit name, email, specialty, and role
        $this->putJson("/api/tenant/users/{$userId}", [
            'name' => 'Dra. Helena M. Souza',
            'email' => 'helena.souza@clinica.com',
            'role' => UserRole::PROFESSIONAL->value,
            'specialty' => 'Psiquiatria Clínica',
            'license_number' => 'CRM/SP 998877',
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.name', 'Dra. Helena M. Souza')
            ->assertJsonPath('data.email', 'helena.souza@clinica.com')
            ->assertJsonPath('data.professional.specialty', 'Psiquiatria Clínica');

        // 4. Change role e extra permissions
        $this->putJson("/api/tenant/users/{$userId}/permissions", [
            'role' => UserRole::RECEPTIONIST->value,
            'extra_permissions' => [PermissionName::MEDICAL_RECORDS_VIEW->value],
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.roles.0', UserRole::RECEPTIONIST->value)
            ->assertJsonPath('data.extra_permissions.0', PermissionName::MEDICAL_RECORDS_VIEW->value);

        // 5. Send password reset email
        $this->postJson("/api/tenant/users/{$userId}/send-password-reset", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk();

        $targetUser = User::query()->findOrFail($userId);
        Notification::assertSentTo($targetUser, ResetPassword::class);

        // 6. Admin cannot deactivate self (422)
        $this->postJson("/api/tenant/users/{$admin->id}/toggle-active", [], ['X-Tenant-ID' => $tenant->id])
            ->assertStatus(422);

        // 7. Deactivate user -> blocks login (403)
        $this->postJson("/api/tenant/users/{$userId}/toggle-active", ['is_active' => false], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->postJson('/api/auth/login', [
            'email' => 'helena.souza@clinica.com',
            'password' => 'secret123',
        ])->assertStatus(403);

        // 8. Reactivate user -> allows login again (200)
        $this->postJson("/api/tenant/users/{$userId}/toggle-active", ['is_active' => true], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->postJson('/api/auth/login', [
            'email' => 'helena.souza@clinica.com',
            'password' => 'secret123',
        ])->assertOk();
    }
}

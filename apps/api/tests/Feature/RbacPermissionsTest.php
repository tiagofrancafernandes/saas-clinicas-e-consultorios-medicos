<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Central\PermissionName;
use App\Enums\Central\UserRole;
use App\Models\Central\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class RbacPermissionsTest extends TestCase
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

    public function testUnauthenticatedAndUnauthorizedUsersAreBlockedByMiddlewares(): void
    {
        $this->getJson('/api/central/tenants')->assertUnauthorized();

        $clinicAdmin = User::factory()->create(['tenant_id' => null]);
        $clinicAdmin->syncRoles([UserRole::CLINIC_ADMIN->value]);
        Sanctum::actingAs($clinicAdmin);

        $this->getJson('/api/central/tenants')->assertForbidden();
    }

    public function testSuperAdminBypassesGateAndCanLogin(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'admin@tiagofranca.com',
            'password' => 'power@123',
        ]);
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@tiagofranca.com',
            'password' => 'power@123',
        ]);

        $login->assertOk()
            ->assertJsonPath('user.roles.0', UserRole::SUPER_ADMIN->value);

        $this->assertTrue($superAdmin->can(PermissionName::PATIENTS_CREATE->value));
        $this->assertTrue($superAdmin->can(PermissionName::TENANTS_MANAGE->value));

        Sanctum::actingAs($superAdmin);
        $this->getJson('/api/central/tenants')->assertOk();
    }

    public function testClinicAdminCanGrantExtraIndividualPermissionToOneDoctorOnly(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);
        Sanctum::actingAs($superAdmin);

        $tenantResp = $this->postJson('/api/central/tenants', [
            'name' => 'Clinica ACL',
            'slug' => 'clinica-acl-' . strtolower(Str::random(5)),
        ]);
        $tenantResp->assertCreated();
        $tenantId = (string) $tenantResp->json('data.id');

        $clinicAdmin = User::factory()->create(['tenant_id' => $tenantId]);
        $clinicAdmin->syncRoles([UserRole::CLINIC_ADMIN->value]);

        $doctorA = User::factory()->create(['tenant_id' => $tenantId]);
        $doctorA->syncRoles([UserRole::PROFESSIONAL->value]);

        $doctorB = User::factory()->create(['tenant_id' => $tenantId]);
        $doctorB->syncRoles([UserRole::PROFESSIONAL->value]);

        // By default, PROFESSIONAL cannot create patients
        Sanctum::actingAs($doctorA);
        $this->postJson('/api/tenant/patients', [
            'name' => 'Test Patient',
            'phone' => '5541984402684',
        ], ['X-Tenant-ID' => $tenantId])->assertForbidden();

        // CLINIC_ADMIN grants patients.create ONLY to doctorA
        Sanctum::actingAs($clinicAdmin);
        $this->putJson("/api/tenant/users/{$doctorA->id}/permissions", [
            'extra_permissions' => [PermissionName::PATIENTS_CREATE->value],
        ], ['X-Tenant-ID' => $tenantId])
            ->assertOk()
            ->assertJsonPath('data.extra_permissions.0', PermissionName::PATIENTS_CREATE->value);

        // Now doctorA can create a patient
        Sanctum::actingAs($doctorA->fresh());
        $this->postJson('/api/tenant/patients', [
            'name' => 'Test Patient',
            'phone' => '5541984402684',
        ], ['X-Tenant-ID' => $tenantId])->assertCreated();

        // doctorB still cannot create a patient
        Sanctum::actingAs($doctorB->fresh());
        $this->postJson('/api/tenant/patients', [
            'name' => 'Another Patient',
            'phone' => '5541984402684',
        ], ['X-Tenant-ID' => $tenantId])->assertForbidden();
    }

    public function testClinicAdminCannotCreateSuperAdminOrEditOtherTenantUsers(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);
        Sanctum::actingAs($superAdmin);

        $tenant1 = Tenant::query()->findOrFail(
            $this->postJson('/api/central/tenants', [
                'name' => 'Clinic 1',
                'slug' => 'clinic-1-' . strtolower(Str::random(4)),
            ])->json('data.id')
        );

        $tenant2 = Tenant::query()->findOrFail(
            $this->postJson('/api/central/tenants', [
                'name' => 'Clinic 2',
                'slug' => 'clinic-2-' . strtolower(Str::random(4)),
            ])->json('data.id')
        );

        $admin1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $admin1->syncRoles([UserRole::CLINIC_ADMIN->value]);

        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        $user2->syncRoles([UserRole::RECEPTIONIST->value]);

        Sanctum::actingAs($admin1);

        // Cannot create SUPER_ADMIN
        $this->postJson('/api/tenant/users', [
            'name' => 'Fake Super',
            'email' => 'fake@clinica1.com',
            'password' => 'secret123',
            'role' => UserRole::SUPER_ADMIN->value,
        ], ['X-Tenant-ID' => $tenant1->id])->assertStatus(422);

        // Cannot edit user from tenant2
        $this->putJson("/api/tenant/users/{$user2->id}/permissions", [
            'extra_permissions' => [PermissionName::PATIENTS_CREATE->value],
        ], ['X-Tenant-ID' => $tenant1->id])->assertNotFound();
    }
}

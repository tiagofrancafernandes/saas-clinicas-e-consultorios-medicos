<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Central\UserRole;
use App\Models\Central\Tenant;
use App\Models\Tenant\Patient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PatientSearchTest extends TestCase
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

    public function testPatientsEndpointSupportsSearchAndLimit(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);
        Sanctum::actingAs($superAdmin);

        $tenant = Tenant::query()->findOrFail(
            $this->postJson('/api/central/tenants', [
                'name' => 'Clinica Busca',
                'slug' => 'clinica-busca-' . strtolower(Str::random(4)),
            ])->json('data.id')
        );

        $tenant->run(function (): void {
            Patient::create(['id' => (string) Str::uuid(), 'name' => 'Ana Paula Silva', 'phone' => '5541999990001', 'email' => 'ana@example.com']);
            Patient::create(['id' => (string) Str::uuid(), 'name' => 'Bruno Costa', 'phone' => '5541999990002', 'email' => 'bruno@example.com']);
            Patient::create(['id' => (string) Str::uuid(), 'name' => 'Carlos Eduardo', 'phone' => '5541988880003', 'email' => 'carlos@example.com']);
        });

        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->syncRoles([UserRole::CLINIC_ADMIN->value]);
        Sanctum::actingAs($admin);

        // 1. Search by partial name (case-insensitive)
        $this->getJson('/api/tenant/patients?search=paula', ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ana Paula Silva');

        // 2. Search by partial phone
        $this->getJson('/api/tenant/patients?search=98888', ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Carlos Eduardo');

        // 3. Respect limit parameter
        $this->getJson('/api/tenant/patients?limit=2', ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}

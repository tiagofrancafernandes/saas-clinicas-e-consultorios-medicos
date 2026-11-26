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

final class PatientInternalNoteTest extends TestCase
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

    public function testInternalNoteIsHiddenFromPatientPayloadsAndRequiresUserPassword(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);
        Sanctum::actingAs($superAdmin);

        $tenant = Tenant::query()->findOrFail(
            $this->postJson('/api/central/tenants', [
                'name' => 'Clinica Sigilo',
                'slug' => 'clinica-sigilo-' . strtolower(Str::random(4)),
            ])->json('data.id')
        );

        $patientId = (string) Str::uuid();
        $tenant->run(function () use ($patientId): void {
            Patient::create([
                'id' => $patientId,
                'name' => 'Carlos Sigiloso',
                'phone' => '5541984402684',
                'internal_note' => 'Segredo clínico confidencial',
            ]);
        });

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => 'minha-senha-segura',
        ]);
        $admin->syncRoles([UserRole::CLINIC_ADMIN->value]);
        Sanctum::actingAs($admin);

        // Must never appear in index or show payloads
        $indexRes = $this->getJson('/api/tenant/patients', ['X-Tenant-ID' => $tenant->id])->assertOk();
        $this->assertArrayNotHasKey('internal_note', $indexRes->json('data.0'));

        $showRes = $this->getJson("/api/tenant/patients/{$patientId}", ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('has_internal_note', true);
        $this->assertArrayNotHasKey('internal_note', $showRes->json('data'));

        // Wrong password returns 403
        $this->postJson("/api/tenant/patients/{$patientId}/internal-note/reveal", [
            'password' => 'senha-errada',
        ], ['X-Tenant-ID' => $tenant->id])->assertForbidden();

        // Valid password reveals internal_note
        $this->postJson("/api/tenant/patients/{$patientId}/internal-note/reveal", [
            'password' => 'minha-senha-segura',
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('internal_note', 'Segredo clínico confidencial');

        // Updating with wrong password returns 403
        $this->putJson("/api/tenant/patients/{$patientId}/internal-note", [
            'password' => 'senha-errada',
            'internal_note' => 'Tentativa indevida',
        ], ['X-Tenant-ID' => $tenant->id])->assertForbidden();

        // Updating with valid password succeeds
        $this->putJson("/api/tenant/patients/{$patientId}/internal-note", [
            'password' => 'minha-senha-segura',
            'internal_note' => 'Nota atualizada com senha',
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('has_internal_note', true);
    }
}

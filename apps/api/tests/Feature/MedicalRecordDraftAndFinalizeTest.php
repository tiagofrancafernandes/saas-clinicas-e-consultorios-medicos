<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Central\UserRole;
use App\Enums\Tenant\MedicalRecordStatus;
use App\Enums\Tenant\MedicalRecordType;
use App\Enums\Tenant\RecordVisibility;
use App\Models\Central\Tenant;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MedicalRecordDraftAndFinalizeTest extends TestCase
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

    public function testDraftAutoSaveFinalizationAndAddendumWorkflow(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);
        Sanctum::actingAs($superAdmin);

        $tenant = Tenant::query()->findOrFail(
            $this->postJson('/api/central/tenants', [
                'name' => 'Clinica Prontuario',
                'slug' => 'clinica-pront-' . strtolower(Str::random(4)),
            ])->json('data.id')
        );

        $profUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $profUser->syncRoles([UserRole::PROFESSIONAL->value]);

        $otherProfUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherProfUser->syncRoles([UserRole::PROFESSIONAL->value]);

        $patientId = (string) Str::uuid();
        $profId = (string) Str::uuid();
        $otherProfId = (string) Str::uuid();

        $tenant->run(function () use ($patientId, $profId, $otherProfId, $profUser, $otherProfUser): void {
            Patient::create([
                'id' => $patientId,
                'name' => 'Paciente Sessão',
                'phone' => '5541999991111',
            ]);

            Professional::create([
                'id' => $profId,
                'user_id' => (string) $profUser->id,
                'name' => 'Dra. Psicóloga',
                'specialty' => 'Psicologia',
                'license_number' => 'CRP-12345',
                'is_active' => true,
            ]);

            Professional::create([
                'id' => $otherProfId,
                'user_id' => (string) $otherProfUser->id,
                'name' => 'Dr. Outro',
                'specialty' => 'Clínica Geral',
                'license_number' => 'CRM-67890',
                'is_active' => true,
            ]);
        });

        Sanctum::actingAs($profUser);

        // 0. Professional without create_on_behalf cannot create on behalf of another professional (403)
        $this->postJson("/api/tenant/patients/{$patientId}/medical-records", [
            'professional_id' => $otherProfId,
            'title' => 'Tentativa indevida',
            'content' => 'Não autorizado',
        ], ['X-Tenant-ID' => $tenant->id])->assertStatus(403);

        // 1. Create a DRAFT record without sending professional_id (auto-resolved from logged-in user)
        $createRes = $this->postJson("/api/tenant/patients/{$patientId}/medical-records", [
            'title' => 'Sessão Terapêutica #1',
            'type' => MedicalRecordType::PSYCHOTHERAPY->value,
            'status' => MedicalRecordStatus::DRAFT->value,
            'visibility' => RecordVisibility::PUBLIC_CLINIC->value,
            'content' => 'Início da sessão...',
            'structured_data' => [
                'demand' => 'Ansiedade no trabalho',
                'interventions' => '',
            ],
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.status', MedicalRecordStatus::DRAFT->value)
            ->assertJsonPath('data.professional_id', $profId)
            ->assertJsonPath('data.created_by_user_id', $profUser->id);

        $recordId = (string) $createRes->json('data.id');

        // 2. Other professional cannot see someone else's DRAFT even if visibility is PUBLIC_CLINIC
        Sanctum::actingAs($otherProfUser);
        $this->getJson("/api/tenant/medical-records?requester_professional_id={$otherProfId}", ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // 3. Author updates DRAFT incrementally (auto-save)
        Sanctum::actingAs($profUser);
        $this->putJson("/api/tenant/medical-records/{$recordId}", [
            'content' => 'Sessão concluída com boa adesão.',
            'structured_data' => [
                'demand' => 'Ansiedade no trabalho',
                'interventions' => 'Reestruturação cognitiva',
            ],
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.structured_data.interventions', 'Reestruturação cognitiva');

        // 4. Author finalizes the record
        $this->postJson("/api/tenant/medical-records/{$recordId}/finalize", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.status', MedicalRecordStatus::FINALIZED->value);

        // 5. Finalized record cannot be edited directly (422)
        $this->putJson("/api/tenant/medical-records/{$recordId}", [
            'title' => 'Tentativa de alteração',
        ], ['X-Tenant-ID' => $tenant->id])->assertStatus(422);

        // 6. Other professional can now see the FINALIZED PUBLIC_CLINIC record
        Sanctum::actingAs($otherProfUser);
        $this->getJson("/api/tenant/medical-records?requester_professional_id={$otherProfId}", ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // 7. Author adds an addendum linked to the finalized record
        Sanctum::actingAs($profUser);
        $this->postJson("/api/tenant/patients/{$patientId}/medical-records", [
            'professional_id' => $profId,
            'title' => 'Adendo pós-sessão',
            'content' => 'Paciente enviou relato complementar por mensagem.',
            'parent_record_id' => $recordId,
            'visibility' => RecordVisibility::PUBLIC_CLINIC->value,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.type', MedicalRecordType::ADDENDUM->value)
            ->assertJsonPath('data.status', MedicalRecordStatus::FINALIZED->value);

        // 8. Clinic Admin can create a record on behalf of another professional and created_by_user_id records the admin
        $adminUser = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Diretor Clínico']);
        $adminUser->syncRoles([UserRole::CLINIC_ADMIN->value]);
        Sanctum::actingAs($adminUser);

        $this->postJson("/api/tenant/patients/{$patientId}/medical-records", [
            'professional_id' => $profId,
            'title' => 'Registro inserido pela diretoria',
            'content' => 'Inserido em nome da Dra. Psicóloga.',
            'visibility' => RecordVisibility::PUBLIC_CLINIC->value,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.professional_id', $profId)
            ->assertJsonPath('data.created_by_user_id', $adminUser->id)
            ->assertJsonPath('data.created_by.name', 'Diretor Clínico');
    }
}

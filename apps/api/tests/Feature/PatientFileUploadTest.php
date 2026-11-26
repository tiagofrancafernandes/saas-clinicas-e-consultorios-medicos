<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Central\UserRole;
use App\Models\Central\Tenant;
use App\Models\Tenant\Patient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PatientFileUploadTest extends TestCase
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

    public function testProfessionalCanUploadListDownloadAndDeletePatientFile(): void
    {
        Storage::fake('local');

        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);
        Sanctum::actingAs($superAdmin);

        $tenant = Tenant::query()->findOrFail(
            $this->postJson('/api/central/tenants', [
                'name' => 'Clinica Arquivos',
                'slug' => 'clinica-arq-' . strtolower(Str::random(4)),
            ])->json('data.id')
        );

        $patientId = (string) Str::uuid();
        $tenant->run(function () use ($patientId): void {
            Patient::create([
                'id' => $patientId,
                'name' => 'Paciente Exame',
                'phone' => '5541999993333',
            ]);
        });

        $professional = User::factory()->create(['tenant_id' => $tenant->id]);
        $professional->syncRoles([UserRole::PROFESSIONAL->value]);
        Sanctum::actingAs($professional);

        $upload = UploadedFile::fake()->create('hemograma.pdf', 120, 'application/pdf');

        $createRes = $this->post("/api/tenant/patients/{$patientId}/files", [
            'file' => $upload,
            'collection' => 'exams',
            'visibility' => 'SHARED_TEAM',
        ], [
            'Accept' => 'application/json',
            'X-Tenant-ID' => $tenant->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.original_filename', 'hemograma.pdf')
            ->assertJsonPath('data.collection', 'exams');

        $fileId = (string) $createRes->json('data.id');

        $this->getJson("/api/tenant/patients/{$patientId}/files", ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->get("/api/tenant/patients/{$patientId}/files/{$fileId}/download", ['X-Tenant-ID' => $tenant->id])
            ->assertOk();

        $this->deleteJson("/api/tenant/patients/{$patientId}/files/{$fileId}", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk();

        $this->getJson("/api/tenant/patients/{$patientId}/files", ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}

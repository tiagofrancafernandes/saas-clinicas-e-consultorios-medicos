<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\CentralAuditLog;
use App\Models\Central\Tenant;
use App\Models\Tenant\File;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AuditFilesAndWaitlistTest extends TestCase
{
    use DatabaseMigrations;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $tenantId = (string) Str::uuid();
        $this->tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'Audit & Files Test Clinic',
            'slug' => 'clinic-'.substr($tenantId, 0, 6),
            'timezone' => 'America/Sao_Paulo',
        ]);
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

    public function test_central_audit_log_records_tenant_creation(): void
    {
        $payload = [
            'name' => 'Audited Medical Group',
            'slug' => 'audited-medical-group',
            'timezone' => 'America/Sao_Paulo',
        ];

        $response = $this->postJson('/api/central/tenants', $payload);
        $response->assertStatus(201);

        $newTenantId = $response->json('data.id');

        $audit = CentralAuditLog::where('tenant_id', $newTenantId)
            ->where('event', 'TENANT_CREATED')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('TENANT_CREATED', $audit->event);
        $this->assertSame('Audited Medical Group', $audit->payload['name']);
    }

    public function test_patient_file_attachment_happy_and_sad_path(): void
    {
        tenancy()->initialize($this->tenant);

        $patient = Patient::create([
            'id' => (string) Str::uuid(),
            'name' => 'Clarice Starling',
            'cpf' => '66677788899',
            'email' => 'clarice@fbi.test',
            'phone' => '+5511999994444',
            'timezone' => 'America/Sao_Paulo',
        ]);

        tenancy()->end();

        // 1. Happy path: Attach file metadata to patient
        $attachResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/tenant/patients/{$patient->id}/files", [
                'original_filename' => 'eeg_scan_report.pdf',
                'file_path' => 'patients/exams/eeg_scan_report.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 1048576,
                'collection' => 'exams',
                'visibility' => 'SHARED_TEAM',
            ]);

        $attachResponse->assertStatus(201)
            ->assertJsonPath('data.original_filename', 'eeg_scan_report.pdf')
            ->assertJsonPath('data.collection', 'exams');

        // 2. Happy path: List patient files
        $listResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/tenant/patients/{$patient->id}/files");

        $listResponse->assertStatus(200)
            ->assertJsonPath('meta.total_files', 1);

        // 3. Sad path: Non-existent patient returns 404
        $fakeId = (string) Str::uuid();
        $notFoundResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/tenant/patients/{$fakeId}/files", [
                'original_filename' => 'test.pdf',
                'file_path' => 'path/test.pdf',
            ]);

        $notFoundResponse->assertStatus(404);

        // 4. Sad path: Missing required metadata and no file returns 422
        $invalidResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/tenant/patients/{$patient->id}/files", []);

        $invalidResponse->assertStatus(422);
    }

    public function test_waitlist_registration_and_listing_happy_and_sad_path(): void
    {
        tenancy()->initialize($this->tenant);

        $professional = Professional::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dr. Hannibal Lecter',
            'specialty' => 'Psychiatry',
            'license_number' => 'CRM/SP 999111',
            'color_code' => '#dc2626',
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'id' => (string) Str::uuid(),
            'name' => 'Will Graham',
            'cpf' => '88899900011',
            'email' => 'will@forensic.test',
            'phone' => '+5511999995555',
            'timezone' => 'America/Sao_Paulo',
        ]);

        tenancy()->end();

        // 1. Happy path: Add patient to waitlist
        $entryResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/tenant/waitlist', [
                'patient_id' => $patient->id,
                'professional_id' => $professional->id,
                'preferred_date' => '2026-10-15',
                'preferred_shift' => 'MORNING',
            ]);

        $entryResponse->assertStatus(201)
            ->assertJsonPath('data.status', 'WAITING')
            ->assertJsonPath('data.preferred_shift', 'MORNING');

        // 2. Happy path: List waitlist candidates
        $listResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/tenant/waitlist?status=WAITING');

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // 3. Sad path: Non-existent doctor returns 404
        $fakeDoctorId = (string) Str::uuid();
        $notFoundDoctorResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/tenant/waitlist', [
                'patient_id' => $patient->id,
                'professional_id' => $fakeDoctorId,
                'preferred_date' => '2026-10-15',
            ]);

        $notFoundDoctorResponse->assertStatus(404);

        // 4. Sad path: Invalid date format returns 422
        $invalidDateResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/tenant/waitlist', [
                'patient_id' => $patient->id,
                'professional_id' => $professional->id,
                'preferred_date' => 'invalid-date',
            ]);

        $invalidDateResponse->assertStatus(422);
    }
}

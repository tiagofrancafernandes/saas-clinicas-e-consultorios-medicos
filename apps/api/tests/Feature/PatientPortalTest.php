<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Central\UserRole;
use App\Enums\Tenant\AppointmentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\File;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PatientPortalTest extends TestCase
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

    public function testStaffRolesAreBlockedFromPatientPortalEndpoints(): void
    {
        $tenant = $this->createTenantWithSchema();

        foreach ([UserRole::SUPER_ADMIN, UserRole::CLINIC_ADMIN, UserRole::PROFESSIONAL, UserRole::RECEPTIONIST] as $role) {
            $user = User::factory()->create(['tenant_id' => $tenant->id]);
            $user->syncRoles([$role->value]);
            Sanctum::actingAs($user);

            $this->getJson('/api/tenant/portal/summary', ['X-Tenant-ID' => $tenant->id])
                ->assertForbidden();
        }
    }

    public function testPatientAndRolelessUserCanAccessSummaryAndAppointments(): void
    {
        $tenant = $this->createTenantWithSchema();

        $patientUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'maria@example.com',
        ]);
        $patientUser->syncRoles([UserRole::PATIENT->value]);

        $rolelessUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'sempapel@example.com',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'id' => (string) Str::uuid(),
                'name' => 'Dra. Ana Silva',
                'specialty' => 'Cardiologia',
                'license_number' => 'CRM-PR-12345',
                'active' => true,
            ]);

            $maria = Patient::create([
                'id' => (string) Str::uuid(),
                'name' => 'Maria Souza',
                'phone' => '5511999991111',
                'email' => 'maria@example.com',
            ]);

            $other = Patient::create([
                'id' => (string) Str::uuid(),
                'name' => 'João Outro',
                'phone' => '5511999992222',
                'email' => 'outro@example.com',
            ]);

            Appointment::create([
                'id' => (string) Str::uuid(),
                'patient_id' => $maria->id,
                'professional_id' => $professional->id,
                'start_time_utc' => CarbonImmutable::now('UTC')->addDays(2),
                'end_time_utc' => CarbonImmutable::now('UTC')->addDays(2)->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
                'status' => AppointmentStatus::SCHEDULED,
            ]);

            Appointment::create([
                'id' => (string) Str::uuid(),
                'patient_id' => $other->id,
                'professional_id' => $professional->id,
                'start_time_utc' => CarbonImmutable::now('UTC')->addDays(3),
                'end_time_utc' => CarbonImmutable::now('UTC')->addDays(3)->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
                'status' => AppointmentStatus::SCHEDULED,
            ]);
        });

        Sanctum::actingAs($patientUser);
        $summary = $this->getJson('/api/tenant/portal/summary', ['X-Tenant-ID' => $tenant->id]);
        $summary->assertOk()
            ->assertJsonPath('patient.name', 'Maria Souza')
            ->assertJsonPath('upcoming_count', 1);

        $appointments = $this->getJson('/api/tenant/portal/appointments?tab=upcoming', ['X-Tenant-ID' => $tenant->id]);
        $appointments->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs($rolelessUser);
        $this->getJson('/api/tenant/portal/summary', ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('upcoming_count', 0);
    }

    public function testPatientCanConfirmAndCancelOwnAppointmentWithinWindow(): void
    {
        $tenant = $this->createTenantWithSchema(['cancellation_window_hours' => 4]);

        $patientUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'maria@example.com',
        ]);
        $patientUser->syncRoles([UserRole::PATIENT->value]);

        $appointmentId = '';

        $tenant->run(function () use (&$appointmentId): void {
            $professional = Professional::create([
                'id' => (string) Str::uuid(),
                'name' => 'Dr. Carlos',
                'specialty' => 'Clínica Geral',
                'license_number' => 'CRM-PR-54321',
                'active' => true,
            ]);

            $maria = Patient::create([
                'id' => (string) Str::uuid(),
                'name' => 'Maria Souza',
                'phone' => '5511999991111',
                'email' => 'maria@example.com',
            ]);

            $appointment = Appointment::create([
                'id' => (string) Str::uuid(),
                'patient_id' => $maria->id,
                'professional_id' => $professional->id,
                'start_time_utc' => CarbonImmutable::now('UTC')->addHours(12),
                'end_time_utc' => CarbonImmutable::now('UTC')->addHours(12)->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
                'status' => AppointmentStatus::SCHEDULED,
            ]);

            $appointmentId = $appointment->id;
        });

        Sanctum::actingAs($patientUser);

        $this->postJson("/api/tenant/portal/appointments/{$appointmentId}/confirm", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::CONFIRMED->value);

        $this->postJson("/api/tenant/portal/appointments/{$appointmentId}/cancel", [
            'reason' => 'Imprevisto pessoal',
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::CANCELLED->value);
    }

    public function testPatientCannotDownloadAnotherPatientsDocument(): void
    {
        Storage::fake('local');
        $tenant = $this->createTenantWithSchema();

        $patientUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'maria@example.com',
        ]);
        $patientUser->syncRoles([UserRole::PATIENT->value]);

        $mariaFileId = '';
        $otherFileId = '';

        $tenant->run(function () use (&$mariaFileId, &$otherFileId): void {
            $maria = Patient::create([
                'id' => (string) Str::uuid(),
                'name' => 'Maria Souza',
                'phone' => '5511999991111',
                'email' => 'maria@example.com',
            ]);

            $other = Patient::create([
                'id' => (string) Str::uuid(),
                'name' => 'Outro Paciente',
                'phone' => '5511999993333',
                'email' => 'other@example.com',
            ]);

            Storage::disk('local')->put("patients/{$maria->id}/exame.pdf", 'pdf-content');
            Storage::disk('local')->put("patients/{$other->id}/sigiloso.pdf", 'secret');

            $mariaFile = File::create([
                'id' => (string) Str::uuid(),
                'fileable_type' => Patient::class,
                'fileable_id' => $maria->id,
                'collection' => 'exams',
                'visibility' => 'private',
                'disk' => 'local',
                'file_path' => "patients/{$maria->id}/exame.pdf",
                'original_filename' => 'exame.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 11,
            ]);

            $otherFile = File::create([
                'id' => (string) Str::uuid(),
                'fileable_type' => Patient::class,
                'fileable_id' => $other->id,
                'collection' => 'exams',
                'visibility' => 'private',
                'disk' => 'local',
                'file_path' => "patients/{$other->id}/sigiloso.pdf",
                'original_filename' => 'sigiloso.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 6,
            ]);

            $mariaFileId = $mariaFile->id;
            $otherFileId = $otherFile->id;
        });

        Sanctum::actingAs($patientUser);

        $this->getJson('/api/tenant/portal/documents', ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->get("/api/tenant/portal/documents/{$mariaFileId}/download", ['X-Tenant-ID' => $tenant->id])
            ->assertOk();

        $this->get("/api/tenant/portal/documents/{$otherFileId}/download", ['X-Tenant-ID' => $tenant->id])
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function createTenantWithSchema(array $settings = []): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Clínica Vida',
            'slug' => 'clinica-' . Str::random(6),
            'timezone' => 'America/Sao_Paulo',
            'subscription_status' => 'ACTIVE',
            'settings' => $settings,
        ]);
    }
}

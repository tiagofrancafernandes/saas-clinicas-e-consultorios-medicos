<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Central\UserRole;
use App\Enums\Tenant\AppointmentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AppointmentStatusTransitionTest extends TestCase
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

    public function testAppointmentStatusTransitionsAndFinalizedGuard(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);
        Sanctum::actingAs($superAdmin);

        $tenant = Tenant::query()->findOrFail(
            $this->postJson('/api/central/tenants', [
                'name' => 'Clinica Status',
                'slug' => 'clinica-status-' . strtolower(Str::random(4)),
            ])->json('data.id')
        );

        $appointmentId = (string) Str::uuid();
        $tenant->run(function () use ($appointmentId): void {
            $patient = Patient::create([
                'id' => (string) Str::uuid(),
                'name' => 'Paciente Status',
                'phone' => '5541999997777',
            ]);

            $prof = Professional::create([
                'id' => (string) Str::uuid(),
                'name' => 'Dr. Status',
                'specialty' => 'Clínica Geral',
                'license_number' => 'CRM-11111',
                'is_active' => true,
            ]);

            Appointment::create([
                'id' => $appointmentId,
                'professional_id' => $prof->id,
                'patient_id' => $patient->id,
                'start_time_utc' => CarbonImmutable::now('UTC')->addDay(),
                'end_time_utc' => CarbonImmutable::now('UTC')->addDay()->addMinutes(30),
                'status' => AppointmentStatus::SCHEDULED,
                'price_in_cents' => 10000,
                'payment_status' => 'PENDING',
                'origin_timezone' => 'America/Sao_Paulo',
            ]);
        });

        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->syncRoles([UserRole::CLINIC_ADMIN->value]);
        Sanctum::actingAs($admin);

        foreach ([
            AppointmentStatus::CONFIRMED,
            AppointmentStatus::ARRIVED,
            AppointmentStatus::IN_CONSULTATION,
            AppointmentStatus::COMPLETED,
        ] as $nextStatus) {
            $this->patchJson("/api/tenant/appointments/{$appointmentId}/status", [
                'status' => $nextStatus->value,
            ], ['X-Tenant-ID' => $tenant->id])
                ->assertOk()
                ->assertJsonPath('data.status', $nextStatus->value);
        }

        // Finalized appointment cannot change status (422)
        $this->patchJson("/api/tenant/appointments/{$appointmentId}/status", [
            'status' => AppointmentStatus::SCHEDULED->value,
        ], ['X-Tenant-ID' => $tenant->id])->assertStatus(422);
    }
}

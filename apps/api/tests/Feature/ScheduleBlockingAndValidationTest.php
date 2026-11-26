<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Central\UserRole;
use App\Enums\Tenant\AppointmentStatus;
use App\Enums\Tenant\NotificationLogStatus;
use App\Enums\Tenant\WhatsAppCategory;
use App\Models\Central\Tenant;
use App\Models\Tenant\NotificationLog;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use App\Models\Tenant\ProfessionalAvailability;
use App\Models\Tenant\WhatsAppInstance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ScheduleBlockingAndValidationTest extends TestCase
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

    public function testPastDateValidationUnavailabilityBlockingConfirmAndNotifications(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);
        Sanctum::actingAs($superAdmin);

        $tenant = Tenant::query()->findOrFail(
            $this->postJson('/api/central/tenants', [
                'name' => 'Clinica Agenda',
                'slug' => 'clinica-agenda-' . strtolower(Str::random(4)),
            ])->json('data.id')
        );

        $professionalId = (string) Str::uuid();
        $patientId = (string) Str::uuid();

        $futureDay = CarbonImmutable::now('America/Sao_Paulo')->addDays(2);
        $dayOfWeek = (int) $futureDay->dayOfWeek;

        $tenant->run(function () use ($professionalId, $patientId, $dayOfWeek): void {
            Professional::create([
                'id' => $professionalId,
                'name' => 'Dra. Marina Campos',
                'specialty' => 'Cardiologia',
                'license_number' => 'CRM/PR 10101',
                'color_code' => '#0ea5e9',
                'is_active' => true,
            ]);

            ProfessionalAvailability::create([
                'id' => (string) Str::uuid(),
                'professional_id' => $professionalId,
                'day_of_week' => $dayOfWeek,
                'start_time' => '08:00',
                'end_time' => '12:00',
                'slot_duration_minutes' => 30,
                'is_active' => true,
            ]);

            Patient::create([
                'id' => $patientId,
                'name' => 'João Paciente',
                'phone' => '5541984402684',
            ]);

            WhatsAppInstance::create([
                'id' => (string) Str::uuid(),
                'label' => 'Default',
                'phone_number' => '5541984402684',
                'instance_name' => 'Clinica Agenda',
                'token' => 'SECRET_TOKEN',
                'category' => WhatsAppCategory::DEFAULT,
                'is_default' => true,
                'active' => true,
            ]);
        });

        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->syncRoles([UserRole::CLINIC_ADMIN->value]);
        Sanctum::actingAs($admin);

        Http::fake([
            '*/message/sendText/*' => Http::response(['key' => ['id' => 'WA_NOTIF_1']], 201),
        ]);

        // 1. Past date & invalid format rejected (422)
        $this->postJson('/api/tenant/appointments', [
            'professional_id' => $professionalId,
            'patient_id' => $patientId,
            'start_time_utc' => CarbonImmutable::now('UTC')->subHour()->toIso8601String(),
            'end_time_utc' => CarbonImmutable::now('UTC')->subMinutes(30)->toIso8601String(),
        ], ['X-Tenant-ID' => $tenant->id])->assertStatus(422);

        $this->postJson('/api/tenant/appointments', [
            'professional_id' => $professionalId,
            'patient_id' => $patientId,
            'start_time_utc' => 'invalid-date',
            'end_time_utc' => 'invalid-date',
        ], ['X-Tenant-ID' => $tenant->id])->assertStatus(422);

        // 2. Past date slots excluded from availability endpoint
        $pastDate = CarbonImmutable::now('America/Sao_Paulo')->subDays(2)->toDateString();
        $this->getJson("/api/tenant/availability/slots?professional_id={$professionalId}&date={$pastDate}", ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('meta.total_available', 0);

        // 3. Create schedule block (Férias / Falta) from 09:00 to 10:00 local
        $dateStr = $futureDay->toDateString();
        $blockRes = $this->postJson('/api/tenant/unavailabilities', [
            'professional_id' => $professionalId,
            'start_at' => "{$dateStr} 09:00:00",
            'end_at' => "{$dateStr} 10:00:00",
            'timezone' => 'America/Sao_Paulo',
            'reason' => 'Férias',
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.reason', 'Férias');

        $blockId = (string) $blockRes->json('data.id');

        // 4. Booking inside blocked period returns 409 Conflict
        $this->postJson('/api/tenant/appointments', [
            'professional_id' => $professionalId,
            'patient_id' => $patientId,
            'start_time_utc' => "{$dateStr} 09:00:00",
            'end_time_utc' => "{$dateStr} 09:30:00",
            'origin_timezone' => 'America/Sao_Paulo',
        ], ['X-Tenant-ID' => $tenant->id])->assertStatus(409);

        // 5. Book outside blocked period with notify_patient: true (default template)
        $bookRes = $this->postJson('/api/tenant/appointments', [
            'professional_id' => $professionalId,
            'patient_id' => $patientId,
            'start_time_utc' => "{$dateStr} 08:00:00",
            'end_time_utc' => "{$dateStr} 08:30:00",
            'origin_timezone' => 'America/Sao_Paulo',
            'notify_patient' => true,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertCreated()
            ->assertJsonPath('data.status', AppointmentStatus::SCHEDULED->value);

        $appointmentId = (string) $bookRes->json('data.id');

        // 6. Confirm appointment
        $this->postJson("/api/tenant/appointments/{$appointmentId}/confirm", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::CONFIRMED->value);

        // 7. Reschedule with custom message
        $this->postJson("/api/tenant/appointments/{$appointmentId}/reschedule", [
            'new_start_time_utc' => "{$dateStr} 10:30:00",
            'new_end_time_utc' => "{$dateStr} 11:00:00",
            'origin_timezone' => 'America/Sao_Paulo',
            'notify_patient' => true,
            'notification_message' => 'Sua consulta foi remarcada para 10:30.',
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::SCHEDULED->value);

        // 8. Cancel with notify_patient: true (default template)
        $this->postJson("/api/tenant/appointments/{$appointmentId}/cancel", [
            'reason' => 'Imprevisto',
            'notify_patient' => true,
        ], ['X-Tenant-ID' => $tenant->id])
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::CANCELLED->value);

        // 9. Verify 3 NotificationLog entries were created (BOOKED, RESCHEDULED, CANCELLED)
        $tenant->run(function () use ($patientId, $appointmentId): void {
            $logs = NotificationLog::query()
                ->where('patient_id', $patientId)
                ->where('appointment_id', $appointmentId)
                ->orderBy('created_at')
                ->get();

            $this->assertCount(3, $logs);
            $this->assertSame(['APPOINTMENT_BOOKED', 'APPOINTMENT_RESCHEDULED', 'APPOINTMENT_CANCELLED'], $logs->pluck('type')->all());
            $this->assertSame(NotificationLogStatus::SENT, $logs->first()->status);
            $this->assertSame('Sua consulta foi remarcada para 10:30.', $logs->get(1)->payload['text']);
        });

        // 10. Remove block
        $this->deleteJson("/api/tenant/unavailabilities/{$blockId}", [], ['X-Tenant-ID' => $tenant->id])
            ->assertOk();
    }
}

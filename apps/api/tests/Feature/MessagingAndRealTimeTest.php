<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Tenant\AppointmentStatus;
use App\Events\Tenant\PatientSummonedToRoom;
use App\Jobs\Central\ScanUpcomingAppointmentsJob;
use App\Jobs\Tenant\DispatchAppointmentReminderJob;
use App\Models\Central\Tenant;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\ClinicRoom;
use App\Models\Tenant\NotificationLog;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use App\Services\Messaging\WhatsAppDriver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MessagingAndRealTimeTest extends TestCase
{
    use DatabaseMigrations;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $tenantId = (string) Str::uuid();
        $this->tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'Realtime Test Clinic',
            'slug' => 'realtime-'.substr($tenantId, 0, 6),
            'timezone' => 'America/Sao_Paulo',
            'settings' => ['reminder_notice_hours' => 24],
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

    public function test_scan_upcoming_appointments_scans_tenants_and_dispatches_reminder_jobs(): void
    {
        Queue::fake();

        tenancy()->initialize($this->tenant);

        $professional = Professional::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dr. Marcus Welby',
            'specialty' => 'Internal Medicine',
            'license_number' => 'CRM/SP 554433',
            'color_code' => '#2563eb',
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'id' => (string) Str::uuid(),
            'name' => 'John Watson',
            'cpf' => '11122233344',
            'email' => 'watson@baker.test',
            'phone' => '+5511999998888',
            'timezone' => 'America/Sao_Paulo',
        ]);

        // Appointment within notice window (now + 24h + 5min)
        $targetTime = CarbonImmutable::now()->addHours(24)->addMinutes(5);

        Appointment::create([
            'id' => (string) Str::uuid(),
            'professional_id' => $professional->id,
            'patient_id' => $patient->id,
            'start_time_utc' => $targetTime,
            'end_time_utc' => $targetTime->addMinutes(30),
            'status' => AppointmentStatus::SCHEDULED,
            'billing_type' => 'private',
            'origin_timezone' => 'America/Sao_Paulo',
        ]);

        // Another appointment outside window (now + 48h)
        Appointment::create([
            'id' => (string) Str::uuid(),
            'professional_id' => $professional->id,
            'patient_id' => $patient->id,
            'start_time_utc' => CarbonImmutable::now()->addHours(48),
            'end_time_utc' => CarbonImmutable::now()->addHours(48)->addMinutes(30),
            'status' => AppointmentStatus::SCHEDULED,
            'billing_type' => 'private',
            'origin_timezone' => 'America/Sao_Paulo',
        ]);

        tenancy()->end();

        // Execute central scan job
        (new ScanUpcomingAppointmentsJob)->handle();

        // Verify only 1 reminder was queued
        Queue::assertPushed(DispatchAppointmentReminderJob::class, 1);
    }

    public function test_dispatch_appointment_reminder_job_creates_notification_log(): void
    {
        tenancy()->initialize($this->tenant);

        $professional = Professional::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dr. Sarah Connor',
            'specialty' => 'Pediatrics',
            'license_number' => 'CRM/SP 998877',
            'color_code' => '#10b981',
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'id' => (string) Str::uuid(),
            'name' => 'Kyle Reese',
            'cpf' => '55566677788',
            'email' => 'kyle@resistance.test',
            'phone' => '+5511988882222',
            'timezone' => 'America/Sao_Paulo',
        ]);

        $appointment = Appointment::create([
            'id' => (string) Str::uuid(),
            'professional_id' => $professional->id,
            'patient_id' => $patient->id,
            'start_time_utc' => CarbonImmutable::now()->addDay(),
            'end_time_utc' => CarbonImmutable::now()->addDay()->addMinutes(30),
            'status' => AppointmentStatus::SCHEDULED,
            'billing_type' => 'private',
            'origin_timezone' => 'America/Sao_Paulo',
        ]);

        $job = new DispatchAppointmentReminderJob($appointment, 'WHATSAPP');
        $job->handle(new WhatsAppDriver);

        // Verify log was stored in tenant schema
        $log = NotificationLog::where('appointment_id', $appointment->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('WHATSAPP', $log->channel);
        $this->assertSame('PRE_REMINDER', $log->type);
        $this->assertSame('+5511988882222', $log->recipient);
        $this->assertStringStartsWith('wamid.', $log->external_message_id);
        $this->assertSame('SENT', $log->status);

        tenancy()->end();
    }

    public function test_patient_summon_to_consulting_room_happy_and_sad_path(): void
    {
        Event::fake([PatientSummonedToRoom::class]);

        tenancy()->initialize($this->tenant);

        $room = ClinicRoom::create([
            'id' => (string) Str::uuid(),
            'name' => 'Consulting Room 03',
            'description' => 'Neurology Suite',
            'is_active' => true,
        ]);

        $professional = Professional::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dr. Robert Langdon',
            'specialty' => 'Neurology',
            'license_number' => 'CRM/SP 654321',
            'color_code' => '#f59e0b',
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sophie Neveu',
            'cpf' => '77788899900',
            'email' => 'sophie@louvre.test',
            'phone' => '+5511977773333',
            'timezone' => 'America/Sao_Paulo',
        ]);

        $appointment = Appointment::create([
            'id' => (string) Str::uuid(),
            'professional_id' => $professional->id,
            'patient_id' => $patient->id,
            'clinic_room_id' => $room->id,
            'start_time_utc' => CarbonImmutable::now(),
            'end_time_utc' => CarbonImmutable::now()->addMinutes(30),
            'status' => AppointmentStatus::ARRIVED,
            'billing_type' => 'private',
            'origin_timezone' => 'America/Sao_Paulo',
        ]);

        tenancy()->end();

        // 1. Happy path: Summon patient to room
        $response = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/tenant/appointments/{$appointment->id}/summon", [
                'room_name' => 'Consulting Room 03',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.room_name', 'Consulting Room 03')
            ->assertJsonPath('data.status', 'IN_CONSULTATION');

        Event::assertDispatched(PatientSummonedToRoom::class, function (PatientSummonedToRoom $event) use ($appointment): bool {
            return $event->appointment->id === $appointment->id && $event->roomName === 'Consulting Room 03';
        });

        // 2. Sad path: Summoning an already cancelled appointment returns 422
        tenancy()->initialize($this->tenant);
        $cancelledAppointment = Appointment::create([
            'id' => (string) Str::uuid(),
            'professional_id' => $professional->id,
            'patient_id' => $patient->id,
            'start_time_utc' => CarbonImmutable::now()->addDays(3),
            'end_time_utc' => CarbonImmutable::now()->addDays(3)->addMinutes(30),
            'status' => AppointmentStatus::CANCELLED,
            'billing_type' => 'private',
            'origin_timezone' => 'America/Sao_Paulo',
        ]);
        tenancy()->end();

        $sadResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/tenant/appointments/{$cancelledAppointment->id}/summon");

        $sadResponse->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot summon patient for an appointment that is finalized.']);

        // 3. Sad path: Non-existent appointment returns 404
        $fakeId = (string) Str::uuid();
        $notFoundResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/tenant/appointments/{$fakeId}/summon");

        $notFoundResponse->assertStatus(404);
    }
}

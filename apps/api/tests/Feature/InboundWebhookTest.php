<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Appointment\BookAppointmentAction;
use App\Enums\Tenant\AppointmentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\NotificationLog;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InboundWebhookTest extends TestCase
{
    use DatabaseMigrations;

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

    public function test_happy_path_webhook_confirms_appointment_and_updates_log(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Webhook Clinic',
            'slug' => 'webhook-clinic-'.Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $externalMessageId = 'WHATSAPP_MSG_'.Str::random(16);

        $tenant->run(function () use ($externalMessageId): void {
            $professional = Professional::create([
                'name' => 'Dr. Leonard McCoy',
                'specialty' => 'Internal Medicine',
                'license_number' => 'CRM-55551',
            ]);

            $patient = Patient::create([
                'name' => 'Spock',
                'phone' => '+5511999995555',
            ]);

            $start = CarbonImmutable::parse('2026-12-01 10:00:00', 'UTC');

            $appointment = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => $start,
                'end_time_utc' => $start->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            NotificationLog::create([
                'appointment_id' => $appointment->id,
                'channel' => 'WHATSAPP',
                'type' => 'CONFIRMATION_INVITE',
                'recipient' => $patient->phone,
                'external_message_id' => $externalMessageId,
                'status' => 'SENT',
                'sent_at_utc' => now(),
            ]);
        });

        // Simulate incoming webhook from provider
        $payload = [
            'context_id' => $externalMessageId,
            'payload' => 'CONFIRM',
        ];

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'confirmed');

        $tenant->run(function () use ($externalMessageId): void {
            $log = NotificationLog::where('external_message_id', $externalMessageId)->firstOrFail();
            $this->assertSame('REPLIED', $log->status);

            $appointment = $log->appointment;
            $this->assertNotNull($appointment);
            $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->status);
        });
    }

    public function test_happy_path_webhook_cancels_appointment_and_updates_log(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cancel Webhook Clinic',
            'slug' => 'cancel-wh-'.Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $externalMessageId = 'WHATSAPP_MSG_'.Str::random(16);

        $tenant->run(function () use ($externalMessageId): void {
            $professional = Professional::create([
                'name' => 'Dr. Julian Bashir',
                'specialty' => 'Cardiology',
                'license_number' => 'CRM-55552',
            ]);

            $patient = Patient::create([
                'name' => 'Kira Nerys',
                'phone' => '+5511999996666',
            ]);

            $start = CarbonImmutable::parse('2026-12-02 11:00:00', 'UTC');

            $appointment = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => $start,
                'end_time_utc' => $start->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            NotificationLog::create([
                'appointment_id' => $appointment->id,
                'channel' => 'WHATSAPP',
                'type' => 'PRE_REMINDER',
                'recipient' => $patient->phone,
                'external_message_id' => $externalMessageId,
                'status' => 'SENT',
                'sent_at_utc' => now(),
            ]);
        });

        $payload = [
            'context_id' => $externalMessageId,
            'payload' => 'CANCEL',
        ];

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'cancelled');

        $tenant->run(function () use ($externalMessageId): void {
            $log = NotificationLog::where('external_message_id', $externalMessageId)->firstOrFail();
            $this->assertSame('REPLIED', $log->status);

            $appointment = $log->appointment;
            $this->assertNotNull($appointment);
            $this->assertSame(AppointmentStatus::CANCELLED, $appointment->status);
        });
    }

    public function test_sad_path_webhook_handles_missing_message_or_unknown_id_gracefully(): void
    {
        // 1. Missing message context / payload -> ignored
        $response1 = $this->postJson('/api/webhooks/whatsapp', []);
        $response1->assertStatus(200)
            ->assertJsonPath('status', 'ignored');

        // 2. Unknown external message ID -> log_not_found
        $response2 = $this->postJson('/api/webhooks/whatsapp', [
            'context_id' => 'UNKNOWN_ID_9999',
            'payload' => 'CONFIRM',
        ]);
        $response2->assertStatus(200)
            ->assertJsonPath('status', 'log_not_found');
    }
}

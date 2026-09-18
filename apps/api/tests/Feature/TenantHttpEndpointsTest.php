<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Auth\GenerateAppointmentTokenAction;
use App\Enums\Tenant\AppointmentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use App\Models\Tenant\ProfessionalAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TenantHttpEndpointsTest extends TestCase
{
    use DatabaseMigrations;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $tenantId = (string) Str::uuid();
        $this->tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'Endpoints Test Clinic',
            'slug' => 'clinic-' . substr($tenantId, 0, 6),
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

    public function testTenantIdentificationFailsWithoutHeaderOrWithInvalidId(): void
    {
        // Sad path: Missing header
        $responseNoHeader = $this->getJson('/api/tenant/appointments');
        $responseNoHeader->assertStatus(400)
            ->assertJsonFragment(['message' => 'Tenant identification required. Provide X-Tenant-ID header or tenant query parameter.']);

        // Sad path: Invalid tenant ID
        $responseInvalid = $this->withHeaders(['X-Tenant-ID' => (string) Str::uuid()])
            ->getJson('/api/tenant/appointments');
        $responseInvalid->assertStatus(404)
            ->assertJsonFragment(['message' => 'Tenant not found or invalid tenant identifier.']);
    }

    public function testAvailabilitySlotsEndpointHappyAndSadPath(): void
    {
        tenancy()->initialize($this->tenant);

        $professional = Professional::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dr. Paul Availability',
            'specialty' => 'General Medicine',
            'license_number' => 'CRM/SP 909090',
            'color_code' => '#10b981',
            'is_active' => true,
        ]);

        ProfessionalAvailability::create([
            'id' => (string) Str::uuid(),
            'professional_id' => $professional->id,
            'day_of_week' => 4, // Thursday (2026-10-15 is Thursday)
            'start_time' => '14:00',
            'end_time' => '16:00',
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);

        tenancy()->end();

        // Happy path: Valid request returns generated slots
        $response = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/tenant/availability/slots?professional_id={$professional->id}&date=2026-10-15&slot_duration=30");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['start_time_utc', 'end_time_utc'],
                ],
                'meta' => ['professional_id', 'date', 'total_available'],
            ]);

        $slots = $response->json('data');
        $this->assertCount(4, $slots);

        // Sad path: Non-existent professional returns 404
        $fakeProfId = (string) Str::uuid();
        $responseNotFound = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/tenant/availability/slots?professional_id={$fakeProfId}&date=2026-10-15");
        $responseNotFound->assertStatus(404);

        // Sad path: Missing date returns 422
        $responseInvalid = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/tenant/availability/slots?professional_id={$professional->id}");
        $responseInvalid->assertStatus(422);
    }

    public function testAppointmentBookingCollisionAndCancellationEndpoints(): void
    {
        tenancy()->initialize($this->tenant);

        $professional = Professional::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dr. Sarah Surgeon',
            'specialty' => 'General Surgery',
            'license_number' => 'CRM/SP 303030',
            'color_code' => '#3b82f6',
            'is_active' => true,
        ]);

        ProfessionalAvailability::create([
            'id' => (string) Str::uuid(),
            'professional_id' => $professional->id,
            'day_of_week' => 4,
            'start_time' => '14:00',
            'end_time' => '17:00',
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'id' => (string) Str::uuid(),
            'name' => 'Arthur Dent',
            'cpf' => '12345678901',
            'email' => 'arthur@earth.test',
            'phone' => '+5511999991111',
            'timezone' => 'America/Sao_Paulo',
        ]);

        tenancy()->end();

        // 1. Happy path: Book appointment
        $bookPayload = [
            'professional_id' => $professional->id,
            'patient_id' => $patient->id,
            'start_time_utc' => '2026-10-15T17:00:00Z',
            'end_time_utc' => '2026-10-15T17:30:00Z',
            'origin_timezone' => 'America/Sao_Paulo',
            'notes' => 'Initial consultation via API',
        ];

        $bookResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/tenant/appointments', $bookPayload);

        $bookResponse->assertStatus(201)
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.status', 'SCHEDULED');

        $appointmentId = $bookResponse->json('data.id');

        // 2. Sad path: Colliding booking rejected with 409 Conflict
        $collisionResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/tenant/appointments', $bookPayload);

        $collisionResponse->assertStatus(409)
            ->assertJsonFragment(['message' => 'The selected professional already has an appointment during this time.']);

        // 3. Happy path: Reschedule appointment to free slot
        $rescheduleResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/tenant/appointments/{$appointmentId}/reschedule", [
                'new_start_time_utc' => '2026-10-15T17:30:00Z',
                'new_end_time_utc' => '2026-10-15T18:00:00Z',
            ]);

        $rescheduleResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'SCHEDULED');

        // 4. Happy path: Cancel appointment
        $cancelResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/tenant/appointments/{$appointmentId}/cancel", [
                'reason' => 'Patient has flu',
            ]);

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'CANCELLED');

        // 5. Sad path: Cancelling already cancelled appointment fails with 422
        $cancelAgainResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/tenant/appointments/{$appointmentId}/cancel");

        $cancelAgainResponse->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot cancel an appointment that has already been finalized (CANCELLED).']);
    }

    public function testAppointmentTokenInspectionAndConsumptionEndpoints(): void
    {
        tenancy()->initialize($this->tenant);

        $professional = Professional::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dr. Gregory House',
            'specialty' => 'Infectious Disease',
            'license_number' => 'CRM/SP 123123',
            'color_code' => '#f59e0b',
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'id' => (string) Str::uuid(),
            'name' => 'James Wilson',
            'cpf' => '98765432100',
            'email' => 'wilson@princeton.test',
            'phone' => '+5511988887777',
            'timezone' => 'America/Sao_Paulo',
        ]);

        $appointment = Appointment::create([
            'id' => (string) Str::uuid(),
            'professional_id' => $professional->id,
            'patient_id' => $patient->id,
            'start_time_utc' => CarbonImmutable::now()->addDays(2),
            'end_time_utc' => CarbonImmutable::now()->addDays(2)->addMinutes(30),
            'status' => AppointmentStatus::SCHEDULED,
            'billing_type' => 'private',
            'origin_timezone' => 'America/Sao_Paulo',
        ]);

        $tokenResult = GenerateAppointmentTokenAction::execute($appointment, 'CONFIRMATION');
        $rawToken = $tokenResult['raw_token'];

        tenancy()->end();

        // 1. Happy path: Inspect token details
        $showResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/tenant/tokens/{$rawToken}");

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.intended_action', 'CONFIRMATION')
            ->assertJsonPath('data.is_valid', true);

        // 2. Happy path: Consume token to confirm
        $consumeResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/tenant/tokens/{$rawToken}/consume", [
                'expected_action' => 'CONFIRMATION',
            ]);

        $consumeResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'CONFIRMED');

        // 3. Sad path: Already used token returns 422
        $reuseResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/tenant/tokens/{$rawToken}/consume");

        $reuseResponse->assertStatus(422)
            ->assertJsonFragment(['message' => 'This appointment action token has already been used.']);

        // 4. Sad path: Non-existent token returns 404
        $fakeToken = Str::random(64);
        $notFoundResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/tenant/tokens/{$fakeToken}/consume");

        $notFoundResponse->assertStatus(404);
    }

    public function testPatientOtpAuthenticationEndpointsHappyAndSadPath(): void
    {
        tenancy()->initialize($this->tenant);

        $patient = Patient::create([
            'id' => (string) Str::uuid(),
            'name' => 'Lisa Cuddy',
            'cpf' => '45678901234',
            'email' => 'cuddy@princeton.test',
            'phone' => '+5511977776666',
            'timezone' => 'America/Sao_Paulo',
        ]);

        tenancy()->end();

        // 1. Sad path: Unknown phone number returns 404
        $unknownPhoneResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/tenant/auth/otp/request', ['phone' => '+5511999990000']);
        $unknownPhoneResponse->assertStatus(404);

        // 2. Happy path: Request OTP returns 200 with debug code in testing
        $otpRequestResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/tenant/auth/otp/request', ['phone' => '+5511977776666']);

        $otpRequestResponse->assertStatus(200)
            ->assertJsonStructure(['message', 'expires_in_seconds', 'debug_otp']);

        $otpCode = $otpRequestResponse->json('debug_otp');
        $this->assertNotEmpty($otpCode);

        // 3. Sad path: Incorrect code returns 401 Unauthorized
        $wrongCodeResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/tenant/auth/otp/verify', [
                'phone' => '+5511977776666',
                'code' => '000000',
            ]);
        $wrongCodeResponse->assertStatus(401);

        // 4. Happy path: Correct code generates Sanctum Bearer access token
        $verifyResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/tenant/auth/otp/verify', [
                'phone' => '+5511977776666',
                'code' => $otpCode,
            ]);

        $verifyResponse->assertStatus(200)
            ->assertJsonStructure(['access_token', 'token_type', 'patient']);

        $token = $verifyResponse->json('access_token');
        $this->assertNotEmpty($token);

        // 5. Happy path: Authenticated endpoint /auth/me returns patient profile
        $meResponse = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->id,
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/tenant/auth/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('patient.id', $patient->id)
            ->assertJsonPath('patient.name', 'Lisa Cuddy');
    }
}

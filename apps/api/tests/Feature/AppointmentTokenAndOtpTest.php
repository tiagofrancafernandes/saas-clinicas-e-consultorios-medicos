<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Appointment\BookAppointmentAction;
use App\Actions\Auth\ConsumeAppointmentTokenAction;
use App\Actions\Auth\GenerateAppointmentTokenAction;
use App\Enums\Tenant\AppointmentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use App\Services\Auth\PatientOtpService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class AppointmentTokenAndOtpTest extends TestCase
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

    public function testHappyPathGenerateAndConsumeConfirmationToken(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Token Clinic',
            'slug' => 'token-clinic-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. Walter Bishop',
                'specialty' => 'Science',
                'license_number' => 'CRM-12121',
            ]);

            $patient = Patient::create([
                'name' => 'Olivia Dunham',
                'phone' => '+5511999991234',
            ]);

            $start = CarbonImmutable::parse('2026-11-20 10:00:00', 'UTC');

            $appointment = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => $start,
                'end_time_utc' => $start->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            $this->assertSame(AppointmentStatus::SCHEDULED, $appointment->status);

            // Generate token
            $tokenData = GenerateAppointmentTokenAction::execute($appointment, 'CONFIRMATION', 48);

            $this->assertNotEmpty($tokenData['raw_token']);
            $this->assertNotEmpty($tokenData['token_hash']);

            // Consume token
            $confirmedAppt = ConsumeAppointmentTokenAction::execute($tokenData['raw_token'], 'CONFIRMATION');

            $this->assertSame(AppointmentStatus::CONFIRMED, $confirmedAppt->status);

            $tokenData['model']->refresh();
            $this->assertNotNull($tokenData['model']->used_at_utc);
            $this->assertTrue($tokenData['model']->isUsed());
        });
    }

    public function testSadPathRejectsUsedExpiredOrInvalidTokens(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Invalid Token Clinic',
            'slug' => 'inv-token-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. Peter Bishop',
                'specialty' => 'Physics',
                'license_number' => 'CRM-13131',
            ]);

            $patient = Patient::create([
                'name' => 'Astrid Farnsworth',
                'phone' => '+5511999994321',
            ]);

            $start = CarbonImmutable::parse('2026-11-21 14:00:00', 'UTC');

            $appointment = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => $start,
                'end_time_utc' => $start->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            // 1. Non-existent token
            try {
                ConsumeAppointmentTokenAction::execute('non_existent_token_string');
                $this->fail('Expected exception for non-existent token was not thrown.');
            } catch (RuntimeException $e) {
                $this->assertSame('The provided appointment action token does not exist.', $e->getMessage());
            }

            // 2. Already used token
            $tokenData = GenerateAppointmentTokenAction::execute($appointment, 'CONFIRMATION');
            ConsumeAppointmentTokenAction::execute($tokenData['raw_token']);

            try {
                ConsumeAppointmentTokenAction::execute($tokenData['raw_token']);
                $this->fail('Expected exception for already used token was not thrown.');
            } catch (RuntimeException $e) {
                $this->assertSame('This appointment action token has already been used.', $e->getMessage());
            }

            // 3. Expired token
            $expiredTokenData = GenerateAppointmentTokenAction::execute($appointment, 'CHECKIN', -1); // expired 1h ago

            try {
                ConsumeAppointmentTokenAction::execute($expiredTokenData['raw_token']);
                $this->fail('Expected exception for expired token was not thrown.');
            } catch (RuntimeException $e) {
                $this->assertSame('This appointment action token has expired.', $e->getMessage());
            }

            // 4. Mismatched action
            $rescheduleTokenData = GenerateAppointmentTokenAction::execute($appointment, 'RESCHEDULE');

            try {
                ConsumeAppointmentTokenAction::execute($rescheduleTokenData['raw_token'], 'CONFIRMATION');
                $this->fail('Expected exception for mismatched action was not thrown.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('is intended for RESCHEDULE, not CONFIRMATION', $e->getMessage());
            }
        });
    }

    public function testHappyPathPatientOtpVerificationIssuesScopedSanctumToken(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'OTP Clinic',
            'slug' => 'otp-clinic-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $patient = Patient::create([
                'name' => 'Lincoln Lee',
                'phone' => '+5511987654321',
            ]);

            // Request OTP
            $otpCode = PatientOtpService::requestOtp($patient->phone);
            $this->assertMatchesRegularExpression('/^\d{6}$/', $otpCode);

            // Verify with correct OTP
            $plainTextToken = PatientOtpService::verifyOtpAndGenerateToken($patient, $otpCode);
            $this->assertNotEmpty($plainTextToken);

            // Verify Sanctum abilities
            $tokenRecord = $patient->tokens()->latest()->first();
            $this->assertNotNull($tokenRecord);
            $this->assertTrue($tokenRecord->can('patient:read'));
            $this->assertTrue($tokenRecord->can('patient:reschedule'));
            $this->assertTrue($tokenRecord->can('patient:cancel'));
            $this->assertFalse($tokenRecord->can('admin:write'));
        });
    }

    public function testSadPathRejectsInvalidOrExpiredOtp(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Invalid OTP Clinic',
            'slug' => 'inv-otp-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $patient = Patient::create([
                'name' => 'Broyles Phillip',
                'phone' => '+5511912345678',
            ]);

            // 1. Verify before requesting OTP
            try {
                PatientOtpService::verifyOtpAndGenerateToken($patient, '123456');
                $this->fail('Expected exception for unrequested OTP was not thrown.');
            } catch (RuntimeException $e) {
                $this->assertSame('The OTP code has expired or was not requested.', $e->getMessage());
            }

            // 2. Request OTP and provide wrong code
            PatientOtpService::requestOtp($patient->phone);

            try {
                PatientOtpService::verifyOtpAndGenerateToken($patient, '000000');
                $this->fail('Expected exception for wrong OTP was not thrown.');
            } catch (RuntimeException $e) {
                $this->assertSame('The provided OTP code is invalid.', $e->getMessage());
            }
        });
    }
}

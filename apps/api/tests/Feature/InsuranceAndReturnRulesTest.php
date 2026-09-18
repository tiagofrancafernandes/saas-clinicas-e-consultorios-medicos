<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Appointment\BookAppointmentAction;
use App\Enums\Tenant\AppointmentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\AppointmentType;
use App\Models\Tenant\HealthInsurance;
use App\Models\Tenant\HealthInsurancePlan;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientHealthInsurance;
use App\Models\Tenant\Professional;
use App\Models\Tenant\ProfessionalInsurance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class InsuranceAndReturnRulesTest extends TestCase
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

    public function testHappyPathBookingWithAcceptedInsuranceAndRequiredAuthCode(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Insurance Clinic',
            'slug' => 'ins-clinic-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. Lisa Cuddy',
                'specialty' => 'Endocrinology',
                'license_number' => 'CRM-77889',
            ]);

            $patient = Patient::create([
                'name' => 'Michael Scott',
                'phone' => '+5511999998888',
            ]);

            $insurance = HealthInsurance::create([
                'name' => 'SulAmerica',
                'ans_code' => '445566',
            ]);

            $plan = HealthInsurancePlan::create([
                'health_insurance_id' => $insurance->id,
                'name' => 'Executivo Plus',
                'requires_prior_authorization' => true,
            ]);

            // Affiliate professional with insurance
            ProfessionalInsurance::create([
                'professional_id' => $professional->id,
                'health_insurance_id' => $insurance->id,
                'is_active' => true,
            ]);

            $patientInsurance = PatientHealthInsurance::create([
                'patient_id' => $patient->id,
                'health_insurance_id' => $insurance->id,
                'health_insurance_plan_id' => $plan->id,
                'card_number' => 'CARD-123456789',
                'expiration_date' => CarbonImmutable::now()->addYear(),
            ]);

            $start = CarbonImmutable::parse('2026-11-10 10:00:00', 'UTC');

            $appointment = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'patient_health_insurance_id' => $patientInsurance->id,
                'health_insurance_auth_code' => 'AUTH-XYZ-9988',
                'start_time_utc' => $start,
                'end_time_utc' => $start->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            $this->assertNotNull($appointment->id);
            $this->assertSame('AUTH-XYZ-9988', $appointment->health_insurance_auth_code);
            $this->assertSame($patientInsurance->id, $appointment->patient_health_insurance_id);
        });
    }

    public function testSadPathRejectsUnacceptedInsuranceOrMissingAuthCode(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Rejected Ins Clinic',
            'slug' => 'rej-ins-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. House',
                'specialty' => 'Nephrology',
                'license_number' => 'CRM-11224',
            ]);

            $patient = Patient::create([
                'name' => 'Dwight Schrute',
                'phone' => '+5511977778888',
            ]);

            $unacceptedInsurance = HealthInsurance::create([
                'name' => 'Unimed',
                'ans_code' => '112233',
            ]);

            $plan = HealthInsurancePlan::create([
                'health_insurance_id' => $unacceptedInsurance->id,
                'name' => 'Basic',
                'requires_prior_authorization' => false,
            ]);

            $patientInsurance = PatientHealthInsurance::create([
                'patient_id' => $patient->id,
                'health_insurance_id' => $unacceptedInsurance->id,
                'health_insurance_plan_id' => $plan->id,
                'card_number' => 'UNI-999888',
                'expiration_date' => CarbonImmutable::now()->addMonths(6),
            ]);

            $start = CarbonImmutable::parse('2026-11-11 14:00:00', 'UTC');

            // 1. Rejected because doctor is not affiliated with Unimed
            try {
                BookAppointmentAction::execute([
                    'professional_id' => $professional->id,
                    'patient_id' => $patient->id,
                    'patient_health_insurance_id' => $patientInsurance->id,
                    'start_time_utc' => $start,
                    'end_time_utc' => $start->addMinutes(30),
                    'origin_timezone' => 'America/Sao_Paulo',
                ]);
                $this->fail('Expected insurance rejection was not thrown.');
            } catch (RuntimeException $e) {
                $this->assertSame('The selected professional does not accept this health insurance provider.', $e->getMessage());
            }

            // Affiliate doctor now, but make plan require authorization without providing code
            ProfessionalInsurance::create([
                'professional_id' => $professional->id,
                'health_insurance_id' => $unacceptedInsurance->id,
                'is_active' => true,
            ]);

            $plan->update(['requires_prior_authorization' => true]);

            // 2. Rejected because auth code is missing
            try {
                BookAppointmentAction::execute([
                    'professional_id' => $professional->id,
                    'patient_id' => $patient->id,
                    'patient_health_insurance_id' => $patientInsurance->id,
                    'health_insurance_auth_code' => '', // Missing
                    'start_time_utc' => $start,
                    'end_time_utc' => $start->addMinutes(30),
                    'origin_timezone' => 'America/Sao_Paulo',
                ]);
                $this->fail('Expected missing auth code rejection was not thrown.');
            } catch (RuntimeException $e) {
                $this->assertSame('The selected insurance plan requires a prior authorization code.', $e->getMessage());
            }
        });
    }

    public function testHappyPathReturnAppointmentWithinDaysLimit(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Return Clinic',
            'slug' => 'return-clinic-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. Allison Cameron',
                'specialty' => 'Immunology',
                'license_number' => 'CRM-55667',
            ]);

            $patient = Patient::create([
                'name' => 'Jim Halpert',
                'phone' => '+5511966667777',
            ]);

            $returnType = AppointmentType::create([
                'name' => 'Follow-up Consultation',
                'duration_minutes' => 20,
                'is_return' => true,
                'max_return_days_limit' => 30, // Must be within 30 days
            ]);

            // Initial appointment completed 10 days ago
            $pastDate = CarbonImmutable::now()->subDays(10);
            $parent = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => $pastDate,
                'end_time_utc' => $pastDate->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);
            $parent->update([
                'status' => AppointmentStatus::COMPLETED,
                'completed_at_utc' => $pastDate->addMinutes(30),
            ]);

            $now = CarbonImmutable::now();

            $returnAppointment = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'appointment_type_id' => $returnType->id,
                'parent_appointment_id' => $parent->id,
                'start_time_utc' => $now,
                'end_time_utc' => $now->addMinutes(20),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            $this->assertNotNull($returnAppointment->id);
            $this->assertSame($parent->id, $returnAppointment->parent_appointment_id);
        });
    }

    public function testSadPathRejectsReturnAppointmentExceedingDaysLimitOrUncompletedParent(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Late Return Clinic',
            'slug' => 'late-return-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. Eric Foreman',
                'specialty' => 'Neurology',
                'license_number' => 'CRM-88990',
            ]);

            $patient = Patient::create([
                'name' => 'Pam Beesly',
                'phone' => '+5511955556666',
            ]);

            $returnType = AppointmentType::create([
                'name' => 'Quick Return',
                'duration_minutes' => 15,
                'is_return' => true,
                'max_return_days_limit' => 15, // 15 days limit
            ]);

            // 1. Parent is still SCHEDULED, not COMPLETED
            $uncompletedParent = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => CarbonImmutable::now()->subDays(5),
                'end_time_utc' => CarbonImmutable::now()->subDays(5)->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            try {
                BookAppointmentAction::execute([
                    'professional_id' => $professional->id,
                    'patient_id' => $patient->id,
                    'appointment_type_id' => $returnType->id,
                    'parent_appointment_id' => $uncompletedParent->id,
                    'start_time_utc' => CarbonImmutable::now(),
                    'end_time_utc' => CarbonImmutable::now()->addMinutes(15),
                    'origin_timezone' => 'America/Sao_Paulo',
                ]);
                $this->fail('Expected uncompleted parent rejection was not thrown.');
            } catch (RuntimeException $e) {
                $this->assertSame('The parent appointment must be completed before booking a return consultation.', $e->getMessage());
            }

            // 2. Parent was completed 25 days ago (exceeds 15 days limit)
            $uncompletedParent->update([
                'status' => AppointmentStatus::COMPLETED,
                'completed_at_utc' => CarbonImmutable::now()->subDays(25),
            ]);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('The return appointment exceeds the maximum allowed limit of 15 days from the original consultation.');

            BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'appointment_type_id' => $returnType->id,
                'parent_appointment_id' => $uncompletedParent->id,
                'start_time_utc' => CarbonImmutable::now(),
                'end_time_utc' => CarbonImmutable::now()->addMinutes(15),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);
        });
    }
}

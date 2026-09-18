<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Appointment\BookAppointmentAction;
use App\Actions\Appointment\CancelAppointmentAction;
use App\Actions\Appointment\RescheduleAppointmentAction;
use App\Actions\Package\DeductPackageCreditAction;
use App\Enums\Tenant\AppointmentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\AppointmentType;
use App\Models\Tenant\Package;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientPackage;
use App\Models\Tenant\Professional;
use App\Models\Tenant\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class CancelAndRescheduleAppointmentTest extends TestCase
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

    public function test_happy_path_cancels_appointment_and_notifies_waitlist(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cancel Test Clinic',
            'slug' => 'cancel-clinic-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. Gregory',
                'specialty' => 'Oncology',
                'license_number' => 'CRM-10101',
            ]);

            $patient = Patient::create([
                'name' => 'John Doe',
                'phone' => '+5511999991111',
            ]);

            $waitingPatient = Patient::create([
                'name' => 'Waitlist Patient',
                'phone' => '+5511988882222',
            ]);

            $date = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');

            $waitlistEntry = WaitlistEntry::create([
                'patient_id' => $waitingPatient->id,
                'professional_id' => $professional->id,
                'preferred_date' => $date->toDateString(),
                'preferred_shift' => 'MORNING',
                'status' => 'WAITING',
            ]);

            $appointment = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => $date,
                'end_time_utc' => $date->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            $cancelled = CancelAppointmentAction::execute($appointment, 'PATIENT', 'Personal emergency');

            $this->assertSame(AppointmentStatus::CANCELLED, $cancelled->status);
            $this->assertStringContainsString('Personal emergency', (string) $cancelled->notes);

            // Waitlist entry must now be NOTIFIED
            $waitlistEntry->refresh();
            $this->assertSame('NOTIFIED', $waitlistEntry->status);
            $this->assertNotNull($waitlistEntry->notified_at_utc);
        });
    }

    public function test_happy_path_cancellation_refunds_package_credit(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Package Cancel Clinic',
            'slug' => 'pkg-cancel-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. Adams',
                'specialty' => 'Physiotherapy',
                'license_number' => 'CREFITO-20202',
            ]);

            $patient = Patient::create([
                'name' => 'Arthur Dent',
                'phone' => '+5511977773333',
            ]);

            $apptType = AppointmentType::create([
                'name' => 'Physio Session',
                'duration_minutes' => 30,
                'price_in_cents' => 15000,
            ]);

            $package = Package::create([
                'appointment_type_id' => $apptType->id,
                'name' => '10 Sessions Package',
                'session_quantity' => 10,
                'total_price_in_cents' => 120000,
                'validity_days' => 90,
            ]);

            $patientPackage = PatientPackage::create([
                'patient_id' => $patient->id,
                'package_id' => $package->id,
                'total_credits' => 10,
                'remaining_credits' => 10,
                'expires_at_utc' => CarbonImmutable::now()->addDays(90),
            ]);

            $date = CarbonImmutable::parse('2026-11-06 14:00:00', 'UTC');

            $appointment = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => $date,
                'end_time_utc' => $date->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            $appointment->update(['patient_package_id' => $patientPackage->id]);
            DeductPackageCreditAction::execute($patientPackage, $appointment->id);

            $patientPackage->refresh();
            $this->assertSame(9, $patientPackage->remaining_credits);

            // Cancelling should refund credit back to 10
            CancelAppointmentAction::execute($appointment, 'CLINIC', 'Doctor unwell');

            $patientPackage->refresh();
            $this->assertSame(10, $patientPackage->remaining_credits);
        });
    }

    public function test_sad_path_cannot_cancel_finalized_appointment(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Finalized Clinic',
            'slug' => 'finalized-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. Watson',
                'specialty' => 'General Medicine',
                'license_number' => 'CRM-33333',
            ]);

            $patient = Patient::create([
                'name' => 'Sherlock Holmes',
                'phone' => '+5511966664444',
            ]);

            $date = CarbonImmutable::parse('2026-11-07 09:00:00', 'UTC');

            $appointment = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => $date,
                'end_time_utc' => $date->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            $appointment->update(['status' => AppointmentStatus::COMPLETED]);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot cancel an appointment that has already been finalized (COMPLETED).');

            CancelAppointmentAction::execute($appointment);
        });
    }

    public function test_happy_path_reschedules_to_available_slot(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Reschedule Clinic',
            'slug' => 'resched-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. McCoy',
                'specialty' => 'Surgery',
                'license_number' => 'CRM-44444',
            ]);

            $patient = Patient::create([
                'name' => 'James Kirk',
                'phone' => '+5511955555555',
            ]);

            $originalStart = CarbonImmutable::parse('2026-11-08 10:00:00', 'UTC');
            $newStart = CarbonImmutable::parse('2026-11-08 15:00:00', 'UTC');

            $appointment = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => $originalStart,
                'end_time_utc' => $originalStart->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            $rescheduled = RescheduleAppointmentAction::execute(
                $appointment,
                $newStart,
                $newStart->addMinutes(30)
            );

            $this->assertTrue($rescheduled->start_time_utc->eq($newStart));
            $this->assertSame(AppointmentStatus::SCHEDULED, $rescheduled->status);
        });
    }

    public function test_sad_path_cannot_reschedule_to_colliding_slot_or_when_finalized(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Collision Reschedule Clinic',
            'slug' => 'col-resched-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. Beverly Crusher',
                'specialty' => 'General Practice',
                'license_number' => 'CRM-66666',
            ]);

            $patient1 = Patient::create([
                'name' => 'Jean-Luc Picard',
                'phone' => '+5511944446666',
            ]);

            $patient2 = Patient::create([
                'name' => 'William Riker',
                'phone' => '+5511933337777',
            ]);

            $slotA = CarbonImmutable::parse('2026-11-09 10:00:00', 'UTC');
            $slotB = CarbonImmutable::parse('2026-11-09 11:00:00', 'UTC');

            $appointment1 = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient1->id,
                'start_time_utc' => $slotA,
                'end_time_utc' => $slotA->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            $appointment2 = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient2->id,
                'start_time_utc' => $slotB,
                'end_time_utc' => $slotB->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            // Attempting to reschedule appointment2 into appointment1's time (10:15 - 10:45)
            try {
                RescheduleAppointmentAction::execute(
                    $appointment2,
                    $slotA->addMinutes(15),
                    $slotA->addMinutes(45)
                );
                $this->fail('Expected collision exception was not thrown.');
            } catch (RuntimeException $e) {
                $this->assertSame('The selected professional already has an appointment during this new time.', $e->getMessage());
            }

            // Attempting to reschedule finalized appointment
            $appointment1->update(['status' => AppointmentStatus::COMPLETED]);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot reschedule an appointment that has already been finalized (COMPLETED).');

            RescheduleAppointmentAction::execute(
                $appointment1,
                CarbonImmutable::parse('2026-11-09 16:00:00', 'UTC'),
                CarbonImmutable::parse('2026-11-09 16:30:00', 'UTC')
            );
        });
    }
}

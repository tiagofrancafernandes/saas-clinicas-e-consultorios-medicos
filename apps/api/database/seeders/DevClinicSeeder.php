<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Central\SubscriptionStatus;
use App\Enums\Tenant\AppointmentStatus;
use App\Enums\Tenant\RecordVisibility;
use App\Models\Central\Tenant;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\AppointmentToken;
use App\Models\Tenant\AppointmentType;
use App\Models\Tenant\ClinicRoom;
use App\Models\Tenant\File;
use App\Models\Tenant\HealthInsurance;
use App\Models\Tenant\HealthInsurancePlan;
use App\Models\Tenant\MedicalRecord;
use App\Models\Tenant\Package;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientHealthInsurance;
use App\Models\Tenant\PatientPackage;
use App\Models\Tenant\Professional;
use App\Models\Tenant\ProfessionalAvailability;
use App\Models\Tenant\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class DevClinicSeeder extends Seeder
{
    public const string DEMO_TENANT_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
    public const string DEMO_ROOM_101_ID = '77777777-7777-7777-7777-777777777777';
    public const string DEMO_ROOM_102_ID = '88888888-8888-8888-8888-888888888888';
    public const string DEMO_DR_CARTER_ID = '11111111-1111-1111-1111-111111111111';
    public const string DEMO_DR_STONE_ID = '22222222-2222-2222-2222-222222222222';
    public const string DEMO_DR_HOUSE_ID = '33333333-3333-3333-3333-333333333333';
    public const string DEMO_PATIENT_CARLOS_ID = '44444444-4444-4444-4444-444444444444';
    public const string DEMO_APPOINTMENT_ID = '55555555-5555-5555-5555-555555555555';
    public const string DEMO_CONFIRMATION_TOKEN = 'demo_confirmation_token_carlos_abcdef1234567890abcdef123456789012';
    public const string DEMO_MEDICAL_RECORD_ID = '66666666-6666-6666-6666-666666666666';

    public function run(): void
    {
        $existing = Tenant::query()->find(static::DEMO_TENANT_ID);

        if ($existing) {
            return;
        }

        // 1. Create Demo Tenant (triggers Stancl schema creation and tenant migrations)
        $tenant = Tenant::create([
            'id' => static::DEMO_TENANT_ID,
            'name' => 'Clinica Santa Monica',
            'slug' => 'clinica-santa-monica',
            'tax_id' => '12345678000199',
            'timezone' => 'America/Sao_Paulo',
            'settings' => [
                'reminder_notice_hours' => 24,
                'cancellation_window_hours' => 4,
            ],
        ]);

        $tenant->domains()->create([
            'domain' => 'santa-monica.localhost',
        ]);

        $tenant->subscriptions()->create([
            'status' => SubscriptionStatus::ACTIVE,
            'billing_interval' => 'MONTHLY',
            'current_period_start_utc' => now(),
            'current_period_end_utc' => now()->addYear(),
            'is_exempt' => false,
        ]);

        // 2. Populate isolated tenant schema
        $tenant->run(function (): void {
            // Clinic rooms
            $room101 = ClinicRoom::create([
                'id' => static::DEMO_ROOM_101_ID,
                'name' => 'Room 101 - General Practice',
                'description' => 'Equipped for outpatient clinical assessments',
                'is_active' => true,
            ]);

            $room102 = ClinicRoom::create([
                'id' => static::DEMO_ROOM_102_ID,
                'name' => 'Room 102 - Neurology Suite',
                'description' => 'Dedicated neurology and electroencephalogram room',
                'is_active' => true,
            ]);

            // Professionals
            $drCarter = Professional::create([
                'id' => static::DEMO_DR_CARTER_ID,
                'name' => 'Dr. Robert Carter',
                'specialty' => 'Neurology',
                'license_number' => 'CRM/SP 123456',
                'color_code' => '#2563eb',
                'is_active' => true,
            ]);

            $drStone = Professional::create([
                'id' => static::DEMO_DR_STONE_ID,
                'name' => 'Dr. Amanda Stone',
                'specialty' => 'Dermatology',
                'license_number' => 'CRM/SP 654321',
                'color_code' => '#059669',
                'is_active' => true,
            ]);

            $drHouse = Professional::create([
                'id' => static::DEMO_DR_HOUSE_ID,
                'name' => 'Dr. Gregory House',
                'specialty' => 'Diagnostics',
                'license_number' => 'CRM/SP 789012',
                'color_code' => '#d97706',
                'is_active' => true,
            ]);

            // Weekly shifts (Monday to Friday, 08:00 - 17:00, 30m slots)
            foreach ([$drCarter, $drStone, $drHouse] as $doctor) {
                for ($day = 1; $day <= 5; $day++) {
                    ProfessionalAvailability::create([
                        'id' => (string) Str::uuid(),
                        'professional_id' => $doctor->id,
                        'day_of_week' => $day,
                        'start_time' => '08:00',
                        'end_time' => '17:00',
                        'slot_duration_minutes' => 30,
                        'is_active' => true,
                    ]);
                }
            }

            // Health insurances & plans
            $unimed = HealthInsurance::create([
                'id' => (string) Str::uuid(),
                'name' => 'Unimed Seguros',
                'ans_code' => '30001',
                'is_active' => true,
            ]);

            $unimedPlan = HealthInsurancePlan::create([
                'id' => (string) Str::uuid(),
                'health_insurance_id' => $unimed->id,
                'name' => 'Unimed Nacional Especial',
                'requires_prior_authorization' => false,
                'max_return_days_limit' => 30,
                'is_active' => true,
            ]);

            $bradesco = HealthInsurance::create([
                'id' => (string) Str::uuid(),
                'name' => 'Bradesco Saúde',
                'ans_code' => '005711',
                'is_active' => true,
            ]);

            HealthInsurancePlan::create([
                'id' => (string) Str::uuid(),
                'health_insurance_id' => $bradesco->id,
                'name' => 'Bradesco Top Nacional Plus',
                'requires_prior_authorization' => true,
                'max_return_days_limit' => 21,
                'is_active' => true,
            ]);

            // Appointment types
            $firstConsultation = AppointmentType::create([
                'id' => (string) Str::uuid(),
                'name' => 'First Consultation',
                'default_duration_minutes' => 60,
                'color_code' => '#3b82f6',
                'is_active' => true,
            ]);

            $followUp = AppointmentType::create([
                'id' => (string) Str::uuid(),
                'name' => 'Follow-up Consultation',
                'default_duration_minutes' => 30,
                'color_code' => '#10b981',
                'is_active' => true,
            ]);

            // Pre-paid Package
            $physioPackage = Package::create([
                'id' => (string) Str::uuid(),
                'appointment_type_id' => $followUp->id,
                'name' => 'Physical Therapy (10 Sessions)',
                'session_quantity' => 10,
                'total_price_in_cents' => 150000,
                'validity_days' => 90,
                'is_active' => true,
            ]);

            // Patients
            $carlos = Patient::create([
                'id' => static::DEMO_PATIENT_CARLOS_ID,
                'name' => 'Carlos Silva',
                'tax_id' => '12345678901',
                'email' => 'carlos.silva@example.com',
                'phone' => '+5511988776655',
            ]);

            PatientHealthInsurance::create([
                'id' => (string) Str::uuid(),
                'patient_id' => $carlos->id,
                'health_insurance_id' => $unimed->id,
                'health_insurance_plan_id' => $unimedPlan->id,
                'card_number' => 'UNIMED-99881122',
            ]);

            PatientPackage::create([
                'id' => (string) Str::uuid(),
                'patient_id' => $carlos->id,
                'package_id' => $physioPackage->id,
                'total_credits' => 10,
                'remaining_credits' => 7,
                'expires_at_utc' => CarbonImmutable::now()->addMonths(3),
                'payment_status' => 'PAID_OFFLINE',
            ]);

            $mariana = Patient::create([
                'id' => (string) Str::uuid(),
                'name' => 'Mariana Costa',
                'tax_id' => '23456789012',
                'email' => 'mariana.costa@example.com',
                'phone' => '+5511977665544',
            ]);

            $juliana = Patient::create([
                'id' => (string) Str::uuid(),
                'name' => 'Juliana Mendes',
                'tax_id' => '34567890123',
                'email' => 'juliana.mendes@example.com',
                'phone' => '+5511988221100',
            ]);

            // Demo Appointments
            $tomorrow = CarbonImmutable::now()->addDay()->setTime(14, 0, 0);

            $demoAppointment = Appointment::create([
                'id' => static::DEMO_APPOINTMENT_ID,
                'professional_id' => $drCarter->id,
                'patient_id' => $carlos->id,
                'clinic_room_id' => $room102->id,
                'appointment_type_id' => $firstConsultation->id,
                'start_time_utc' => $tomorrow->setTimezone('UTC'),
                'end_time_utc' => $tomorrow->addMinutes(30)->setTimezone('UTC'),
                'status' => AppointmentStatus::SCHEDULED,
                'price_in_cents' => 15000,
                'payment_status' => 'PENDING',
                'origin_timezone' => 'America/Sao_Paulo',
                'notes' => 'Patient requested initial consultation for migraine study',
            ]);

            // Fast action confirmation token (pre-seeded for demo requests & portal)
            AppointmentToken::create([
                'id' => (string) Str::uuid(),
                'appointment_id' => $demoAppointment->id,
                'token_hash' => hash('sha256', static::DEMO_CONFIRMATION_TOKEN),
                'intended_action' => 'CONFIRMATION',
                'expires_at_utc' => CarbonImmutable::now()->addDays(7),
            ]);

            // Sample Medical Record (Confidential Note)
            MedicalRecord::create([
                'id' => static::DEMO_MEDICAL_RECORD_ID,
                'patient_id' => $carlos->id,
                'professional_id' => $drCarter->id,
                'appointment_id' => $demoAppointment->id,
                'type' => 'CLINICAL_NOTE',
                'visibility' => RecordVisibility::PRIVATE,
                'title' => 'Neurological Assessment - Migraine Follow-up',
                'content' => 'Patient reports reduction in cephalalgia frequency after starting prophylactic treatment. No visual aura detected.',
            ]);

            // Sample Patient File (Exam Document)
            File::create([
                'id' => (string) Str::uuid(),
                'fileable_type' => Patient::class,
                'fileable_id' => $carlos->id,
                'collection' => 'exams',
                'visibility' => 'SHARED_TEAM',
                'disk' => 'local',
                'file_path' => 'tenants/' . static::DEMO_TENANT_ID . '/exams/mri_brain_2026.pdf',
                'original_filename' => 'mri_brain_carlos_silva.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 2048576,
            ]);

            // Waitlist entry
            WaitlistEntry::create([
                'id' => (string) Str::uuid(),
                'patient_id' => $juliana->id,
                'professional_id' => $drCarter->id,
                'appointment_type_id' => $firstConsultation->id,
                'preferred_date' => $tomorrow->format('Y-m-d'),
                'preferred_shift' => 'AFTERNOON',
                'status' => 'WAITING',
            ]);
        });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Appointment\BookAppointmentAction;
use App\Enums\Tenant\AppointmentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use App\Models\Tenant\ProfessionalAvailability;
use App\Models\Tenant\ProfessionalUnavailability;
use App\Services\Scheduling\SlotCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SlotCalculatorTest extends TestCase
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

    public function test_happy_path_calculates_all_available_slots_in_shift(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Slot Test Clinic',
            'slug' => 'slot-clinic-'.Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. Amanda Stone',
                'specialty' => 'Dermatology',
                'license_number' => 'CRM-99881',
            ]);

            // Friday shift: 08:00 to 10:00 (dayOfWeek for 2026-10-16 is Friday = 5)
            ProfessionalAvailability::create([
                'professional_id' => $professional->id,
                'day_of_week' => 5,
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'slot_duration_minutes' => 30,
                'is_active' => true,
            ]);

            $targetFriday = CarbonImmutable::parse('2026-10-16 00:00:00', 'America/Sao_Paulo');

            $slots = SlotCalculator::getAvailableSlots($professional, $targetFriday, 'America/Sao_Paulo');

            // 08:00-08:30, 08:30-09:00, 09:00-09:30, 09:30-10:00 -> 4 slots
            $this->assertCount(4, $slots);

            // UTC offset for America/Sao_Paulo in October is UTC-3 (08:00 BRT = 11:00 UTC)
            $expectedFirstStartUtc = CarbonImmutable::parse('2026-10-16 11:00:00', 'UTC');
            $expectedFirstEndUtc = CarbonImmutable::parse('2026-10-16 11:30:00', 'UTC');

            $this->assertTrue($slots[0]['start_time_utc']->eq($expectedFirstStartUtc));
            $this->assertTrue($slots[0]['end_time_utc']->eq($expectedFirstEndUtc));
        });
    }

    public function test_sad_path_returns_empty_when_no_shift_exists_on_day(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'No Shift Clinic',
            'slug' => 'no-shift-'.Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. Paul Taylor',
                'specialty' => 'Pediatrics',
                'license_number' => 'CRM-77112',
            ]);

            // Only available on Mondays (day 1)
            ProfessionalAvailability::create([
                'professional_id' => $professional->id,
                'day_of_week' => 1,
                'start_time' => '08:00:00',
                'end_time' => '12:00:00',
                'slot_duration_minutes' => 30,
                'is_active' => true,
            ]);

            // Querying a Sunday (day 0)
            $targetSunday = CarbonImmutable::parse('2026-10-18 00:00:00', 'America/Sao_Paulo');

            $slots = SlotCalculator::getAvailableSlots($professional, $targetSunday, 'America/Sao_Paulo');

            $this->assertEmpty($slots);
        });
    }

    public function test_sad_path_excludes_slots_overlapping_unavailability_and_existing_appointments(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Exclusion Test Clinic',
            'slug' => 'exclusion-'.Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. Greg House',
                'specialty' => 'Diagnostics',
                'license_number' => 'CRM-00112',
            ]);

            $patient = Patient::create([
                'name' => 'Wilson Cuddy',
                'phone' => '+5511999990000',
            ]);

            // Shift on Monday (2026-10-19): 08:00 to 10:00 -> 4 potential slots:
            // S1: 08:00-08:30 (11:00-11:30 UTC)
            // S2: 08:30-09:00 (11:30-12:00 UTC)
            // S3: 09:00-09:30 (12:00-12:30 UTC)
            // S4: 09:30-10:00 (12:30-13:00 UTC)
            ProfessionalAvailability::create([
                'professional_id' => $professional->id,
                'day_of_week' => 1,
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'slot_duration_minutes' => 30,
                'is_active' => true,
            ]);

            // 1. Unavailability on 08:30 to 09:00 BRT (11:30 to 12:00 UTC) - Excludes S2
            ProfessionalUnavailability::create([
                'professional_id' => $professional->id,
                'start_time_utc' => CarbonImmutable::parse('2026-10-19 11:30:00', 'UTC'),
                'end_time_utc' => CarbonImmutable::parse('2026-10-19 12:00:00', 'UTC'),
                'reason' => 'Department Meeting',
            ]);

            // 2. Active appointment on 09:30 to 10:00 BRT (12:30 to 13:00 UTC) - Excludes S4
            BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => CarbonImmutable::parse('2026-10-19 12:30:00', 'UTC'),
                'end_time_utc' => CarbonImmutable::parse('2026-10-19 13:00:00', 'UTC'),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            // 3. Cancelled appointment on 08:00 to 08:30 BRT (11:00 to 11:30 UTC) - Should NOT exclude S1
            $cancelledAppt = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => CarbonImmutable::parse('2026-10-19 11:00:00', 'UTC'),
                'end_time_utc' => CarbonImmutable::parse('2026-10-19 11:30:00', 'UTC'),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);
            $cancelledAppt->update(['status' => AppointmentStatus::CANCELLED]);

            $targetMonday = CarbonImmutable::parse('2026-10-19 00:00:00', 'America/Sao_Paulo');
            $slots = SlotCalculator::getAvailableSlots($professional, $targetMonday, 'America/Sao_Paulo');

            // Remaining available slots should be S1 (08:00-08:30) and S3 (09:00-09:30) -> exactly 2 slots
            $this->assertCount(2, $slots);

            $this->assertTrue($slots[0]['start_time_utc']->eq(CarbonImmutable::parse('2026-10-19 11:00:00', 'UTC')));
            $this->assertTrue($slots[1]['start_time_utc']->eq(CarbonImmutable::parse('2026-10-19 12:00:00', 'UTC')));
        });
    }
}

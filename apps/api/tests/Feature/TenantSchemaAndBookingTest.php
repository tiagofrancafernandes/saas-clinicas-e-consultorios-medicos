<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Appointment\BookAppointmentAction;
use App\Models\Central\Tenant;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class TenantSchemaAndBookingTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // Clean up any dynamic tenant schemas created during test run
        $schemas = DB::select("SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'tenant_%'");
        foreach ($schemas as $schema) {
            DB::statement("DROP SCHEMA IF EXISTS \"{$schema->schema_name}\" CASCADE");
        }

        parent::tearDown();
    }

    public function test_tenant_creation_provisions_isolated_postgresql_schema_with_all_tables(): void
    {
        $tenantId = (string) Str::uuid();
        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'Alfa Medical Clinic',
            'slug' => 'alfa-medical-'.substr($tenantId, 0, 6),
            'timezone' => 'America/Sao_Paulo',
            'settings' => ['reminder_notice_hours' => 24],
        ]);

        $schemaName = 'tenant_'.$tenant->id;

        // 1. Verify schema exists in PostgreSQL
        $schemaExists = DB::selectOne(
            'SELECT schema_name FROM information_schema.schemata WHERE schema_name = :schema',
            ['schema' => $schemaName]
        );

        $this->assertNotNull($schemaExists, "Schema {$schemaName} should exist in PostgreSQL.");

        // 2. Verify all domain tables were migrated inside the tenant schema
        $tables = DB::select(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = :schema',
            ['schema' => $schemaName]
        );

        $tableNames = array_map(static fn (object $table): string => (string) $table->table_name, $tables);

        $expectedTables = [
            'clinic_rooms',
            'professionals',
            'professional_availabilities',
            'professional_unavailabilities',
            'health_insurances',
            'health_insurance_plans',
            'professional_insurances',
            'appointment_types',
            'patients',
            'patient_health_insurances',
            'packages',
            'patient_packages',
            'patient_credit_ledger',
            'appointments',
            'appointment_tokens',
            'files',
            'medical_records',
            'professional_delegations',
            'notification_logs',
            'waitlist_entries',
        ];

        foreach ($expectedTables as $expectedTable) {
            $this->assertContains(
                $expectedTable,
                $tableNames,
                "Tenant schema {$schemaName} is missing table {$expectedTable}."
            );
        }
    }

    public function test_book_appointment_action_prevents_slot_collision(): void
    {
        $tenantId = (string) Str::uuid();
        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'Beta Health Center',
            'slug' => 'beta-health-'.substr($tenantId, 0, 6),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. Robert Carter',
                'specialty' => 'Neurology',
                'license_number' => 'CRM-55443',
            ]);

            $patient = Patient::create([
                'name' => 'Alice Walker',
                'phone' => '+5511988887777',
            ]);

            $start = CarbonImmutable::parse('2026-10-15 14:00:00');
            $end = CarbonImmutable::parse('2026-10-15 14:30:00');

            // Book first valid appointment
            $firstAppointment = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => $start,
                'end_time_utc' => $end,
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            $this->assertInstanceOf(Appointment::class, $firstAppointment);
            $this->assertSame($professional->id, $firstAppointment->professional_id);

            // Attempt colliding appointment (14:15 - 14:45)
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('The selected professional already has an appointment during this time.');

            BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => $start->addMinutes(15),
                'end_time_utc' => $end->addMinutes(15),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);
        });
    }

    public function test_book_appointment_action_allows_adjacent_slots(): void
    {
        $tenantId = (string) Str::uuid();
        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'Gamma Dental Clinic',
            'slug' => 'gamma-dental-'.substr($tenantId, 0, 6),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $professional = Professional::create([
                'name' => 'Dr. Clara Oswald',
                'specialty' => 'Dentistry',
                'license_number' => 'CRO-11223',
            ]);

            $patient = Patient::create([
                'name' => 'David Noble',
                'phone' => '+5511977776666',
            ]);

            $start = CarbonImmutable::parse('2026-10-15 09:00:00');
            $end = CarbonImmutable::parse('2026-10-15 09:30:00');

            $firstAppointment = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => $start,
                'end_time_utc' => $end,
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            $secondAppointment = BookAppointmentAction::execute([
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => $end,
                'end_time_utc' => $end->addMinutes(30),
                'origin_timezone' => 'America/Sao_Paulo',
            ]);

            $this->assertNotSame($firstAppointment->id, $secondAppointment->id);
            $this->assertSame(2, Appointment::count());
        });
    }
}

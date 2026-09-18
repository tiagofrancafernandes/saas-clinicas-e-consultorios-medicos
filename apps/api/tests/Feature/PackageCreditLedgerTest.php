<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Package\DeductPackageCreditAction;
use App\Actions\Package\RefundPackageCreditAction;
use App\Models\Central\Tenant;
use App\Models\Tenant\AppointmentType;
use App\Models\Tenant\Package;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientPackage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class PackageCreditLedgerTest extends TestCase
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

    public function testHappyPathDeductsAndRefundsCreditWithLedgerEntries(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ledger Clinic',
            'slug' => 'ledger-clinic-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $patient = Patient::create([
                'name' => 'Sara Connor',
                'phone' => '+5511988880000',
            ]);

            $type = AppointmentType::create([
                'name' => 'Physical Therapy',
                'duration_minutes' => 45,
                'price_in_cents' => 20000,
            ]);

            $package = Package::create([
                'appointment_type_id' => $type->id,
                'name' => '5 Sessions',
                'session_quantity' => 5,
                'total_price_in_cents' => 80000,
                'validity_days' => 60,
            ]);

            $patientPackage = PatientPackage::create([
                'patient_id' => $patient->id,
                'package_id' => $package->id,
                'total_credits' => 5,
                'remaining_credits' => 5,
                'expires_at_utc' => CarbonImmutable::now()->addDays(60),
            ]);

            // Deduct 1 credit
            $updated = DeductPackageCreditAction::execute($patientPackage);
            $this->assertSame(4, $updated->remaining_credits);

            $this->assertDatabaseHas('patient_credit_ledger', [
                'patient_package_id' => $patientPackage->id,
                'credits_delta' => -1,
                'operation_type' => 'APPOINTMENT_DEDUCTION',
            ]);

            // Refund 1 credit
            $refunded = RefundPackageCreditAction::execute($updated);
            $this->assertSame(5, $refunded->remaining_credits);

            $this->assertDatabaseHas('patient_credit_ledger', [
                'patient_package_id' => $patientPackage->id,
                'credits_delta' => 1,
                'operation_type' => 'REFUND',
            ]);
        });
    }

    public function testSadPathCannotDeductFromExpiredPackage(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Expired Clinic',
            'slug' => 'expired-clinic-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $patient = Patient::create([
                'name' => 'Kyle Reese',
                'phone' => '+5511977771111',
            ]);

            $type = AppointmentType::create([
                'name' => 'Pilates',
                'duration_minutes' => 50,
            ]);

            $package = Package::create([
                'appointment_type_id' => $type->id,
                'name' => 'Old Package',
                'session_quantity' => 5,
                'total_price_in_cents' => 50000,
            ]);

            $patientPackage = PatientPackage::create([
                'patient_id' => $patient->id,
                'package_id' => $package->id,
                'total_credits' => 5,
                'remaining_credits' => 3,
                'expires_at_utc' => CarbonImmutable::now()->subDay(), // Expired yesterday
            ]);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('The selected package has expired.');

            DeductPackageCreditAction::execute($patientPackage);
        });
    }

    public function testSadPathCannotDeductWhenCreditsExhausted(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Exhausted Clinic',
            'slug' => 'exhausted-' . Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $patient = Patient::create([
                'name' => 'Miles Dyson',
                'phone' => '+5511966662222',
            ]);

            $type = AppointmentType::create([
                'name' => 'Consultation',
                'duration_minutes' => 30,
            ]);

            $package = Package::create([
                'appointment_type_id' => $type->id,
                'name' => 'Single Credit Package',
                'session_quantity' => 1,
                'total_price_in_cents' => 15000,
            ]);

            $patientPackage = PatientPackage::create([
                'patient_id' => $patient->id,
                'package_id' => $package->id,
                'total_credits' => 1,
                'remaining_credits' => 0, // Exhausted
                'expires_at_utc' => CarbonImmutable::now()->addDays(30),
            ]);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('The selected package has no remaining credits.');

            DeductPackageCreditAction::execute($patientPackage);
        });
    }
}

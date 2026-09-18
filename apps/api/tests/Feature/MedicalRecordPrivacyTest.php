<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Tenant\RecordVisibility;
use App\Models\Central\Tenant;
use App\Models\Tenant\MedicalRecord;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use App\Models\Tenant\ProfessionalDelegation;
use App\Services\MedicalRecord\MedicalRecordAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MedicalRecordPrivacyTest extends TestCase
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

    public function test_happy_path_author_and_shared_team_can_access_records(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Records Clinic',
            'slug' => 'rec-clinic-'.Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $author = Professional::create([
                'name' => 'Dr. Stephen Strange',
                'specialty' => 'Neurosurgery',
                'license_number' => 'CRM-77701',
                'is_active' => true,
            ]);

            $colleague = Professional::create([
                'name' => 'Dr. Christine Palmer',
                'specialty' => 'Emergency Medicine',
                'license_number' => 'CRM-77702',
                'is_active' => true,
            ]);

            $patient = Patient::create([
                'name' => 'Peter Parker',
                'phone' => '+5511999990001',
            ]);

            $privateRecord = MedicalRecord::create([
                'patient_id' => $patient->id,
                'professional_id' => $author->id,
                'type' => 'PSYCH_EVAL',
                'visibility' => RecordVisibility::PRIVATE,
                'title' => 'Confidential Notes',
                'content' => 'Deeply sensitive patient psych evaluation.',
            ]);

            $sharedRecord = MedicalRecord::create([
                'patient_id' => $patient->id,
                'professional_id' => $author->id,
                'type' => 'CLINICAL_NOTE',
                'visibility' => RecordVisibility::SHARED_TEAM,
                'title' => 'General Checkup',
                'content' => 'Vitals stable.',
            ]);

            // Author can access their own private record
            $this->assertTrue(MedicalRecordAccessService::canAccess($privateRecord, $author));

            // Colleague can access shared team record
            $this->assertTrue(MedicalRecordAccessService::canAccess($sharedRecord, $colleague));
        });
    }

    public function test_happy_path_delegated_colleague_can_access_private_record(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Delegation Clinic',
            'slug' => 'del-clinic-'.Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $grantor = Professional::create([
                'name' => 'Dr. Charles Xavier',
                'specialty' => 'Psychiatry',
                'license_number' => 'CRM-88801',
                'is_active' => true,
            ]);

            $grantee = Professional::create([
                'name' => 'Dr. Jean Grey',
                'specialty' => 'Psychiatry',
                'license_number' => 'CRM-88802',
                'is_active' => true,
            ]);

            $patient = Patient::create([
                'name' => 'Logan Howlett',
                'phone' => '+5511999990002',
            ]);

            $privateRecord = MedicalRecord::create([
                'patient_id' => $patient->id,
                'professional_id' => $grantor->id,
                'type' => 'PSYCHIATRIC_REPORT',
                'visibility' => RecordVisibility::PRIVATE,
                'title' => 'Trauma History',
                'content' => 'Restricted trauma assessment notes.',
            ]);

            // Create active delegation (valid from yesterday to next week)
            ProfessionalDelegation::create([
                'grantor_professional_id' => $grantor->id,
                'grantee_professional_id' => $grantee->id,
                'patient_id' => $patient->id,
                'starts_at_utc' => CarbonImmutable::now()->subDay(),
                'ends_at_utc' => CarbonImmutable::now()->addDays(7),
                'reason' => 'Vacation coverage',
            ]);

            // Active delegation grants access
            $this->assertTrue(MedicalRecordAccessService::canAccess($privateRecord, $grantee));

            // Authorize should not throw
            MedicalRecordAccessService::authorizeAccess($privateRecord, $grantee);

            // Filtered query should include private record
            $accessibleRecords = MedicalRecordAccessService::getAccessibleRecordsQuery($patient, $grantee)->get();
            $this->assertTrue($accessibleRecords->contains('id', $privateRecord->id));
        });
    }

    public function test_sad_path_unauthorized_or_expired_delegation_denies_access_to_private_record(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Denied Clinic',
            'slug' => 'denied-clinic-'.Str::random(5),
            'timezone' => 'America/Sao_Paulo',
        ]);

        $tenant->run(function (): void {
            $author = Professional::create([
                'name' => 'Dr. Bruce Banner',
                'specialty' => 'Genetics',
                'license_number' => 'CRM-99901',
                'is_active' => true,
            ]);

            $unauthorizedDoctor = Professional::create([
                'name' => 'Dr. Otto Octavius',
                'specialty' => 'Robotics',
                'license_number' => 'CRM-99902',
                'is_active' => true,
            ]);

            $patient = Patient::create([
                'name' => 'Tony Stark',
                'phone' => '+5511999990003',
            ]);

            $privateRecord = MedicalRecord::create([
                'patient_id' => $patient->id,
                'professional_id' => $author->id,
                'type' => 'CONFIDENTIAL',
                'visibility' => RecordVisibility::PRIVATE,
                'title' => 'Gamma Exposure',
                'content' => 'Confidential blood work.',
            ]);

            // 1. Without delegation, access must be strictly denied
            $this->assertFalse(MedicalRecordAccessService::canAccess($privateRecord, $unauthorizedDoctor));

            try {
                MedicalRecordAccessService::authorizeAccess($privateRecord, $unauthorizedDoctor);
                $this->fail('Expected AuthorizationException was not thrown.');
            } catch (AuthorizationException $e) {
                $this->assertStringContainsString('Access denied', $e->getMessage());
            }

            // 2. With EXPIRED delegation (ended 2 hours ago), access is also denied
            ProfessionalDelegation::create([
                'grantor_professional_id' => $author->id,
                'grantee_professional_id' => $unauthorizedDoctor->id,
                'patient_id' => $patient->id,
                'starts_at_utc' => CarbonImmutable::now()->subDays(5),
                'ends_at_utc' => CarbonImmutable::now()->subHours(2), // Expired
                'reason' => 'Emergency consult expired',
            ]);

            $this->assertFalse(MedicalRecordAccessService::canAccess($privateRecord, $unauthorizedDoctor));

            $this->expectException(AuthorizationException::class);
            MedicalRecordAccessService::authorizeAccess($privateRecord, $unauthorizedDoctor);
        });
    }
}

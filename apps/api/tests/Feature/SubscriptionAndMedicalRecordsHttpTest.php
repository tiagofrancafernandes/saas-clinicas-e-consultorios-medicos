<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Central\SubscriptionStatus;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SubscriptionAndMedicalRecordsHttpTest extends TestCase
{
    use DatabaseMigrations;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $tenantId = (string) Str::uuid();
        $this->tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'Subscription Test Clinic',
            'slug' => 'sub-clinic-' . substr($tenantId, 0, 6),
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

    public function testSuspendedSubscriptionBlocksMutationsButAllowsReads(): void
    {
        // Set tenant subscription to SUSPENDED
        Subscription::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'status' => SubscriptionStatus::SUSPENDED,
            'current_period_start_utc' => CarbonImmutable::now()->subMonth(),
            'current_period_end_utc' => CarbonImmutable::now()->subDay(),
            'is_exempt' => false,
        ]);

        tenancy()->initialize($this->tenant);
        $professional = Professional::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dr. Freezing',
            'specialty' => 'Pediatrics',
            'license_number' => 'CRM/SP 888777',
            'color_code' => '#ef4444',
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'id' => (string) Str::uuid(),
            'name' => 'Suspended Patient',
            'cpf' => '00011122233',
            'email' => 'susp@clinic.test',
            'phone' => '+5511999997777',
            'timezone' => 'America/Sao_Paulo',
        ]);
        tenancy()->end();

        // 1. Happy path: GET requests are allowed even when suspended
        $readResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/tenant/appointments');

        $readResponse->assertStatus(200);

        // 2. Sad path: POST mutation is blocked with 403 Forbidden
        $mutateResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/tenant/appointments', [
                'professional_id' => $professional->id,
                'patient_id' => $patient->id,
                'start_time_utc' => '2026-10-15T10:00:00Z',
                'end_time_utc' => '2026-10-15T10:30:00Z',
            ]);

        $mutateResponse->assertStatus(403)
            ->assertJsonFragment(['message' => 'Clinic subscription is suspended or cancelled. Write operations are locked.'])
            ->assertJsonFragment(['subscription_status' => 'SUSPENDED']);
    }

    public function testMedicalRecordConfidentialityAndDelegationHttpFlow(): void
    {
        tenancy()->initialize($this->tenant);

        $drAuthor = Professional::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dr. Eleanor Vance',
            'specialty' => 'Psychiatry',
            'license_number' => 'CRM/SP 111111',
            'color_code' => '#6366f1',
            'is_active' => true,
        ]);

        $drCovering = Professional::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dr. Theo Crain',
            'specialty' => 'Psychiatry',
            'license_number' => 'CRM/SP 222222',
            'color_code' => '#14b8a6',
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'id' => (string) Str::uuid(),
            'name' => 'Luke Sanderson',
            'cpf' => '33344455566',
            'email' => 'luke@hill.test',
            'phone' => '+5511977771111',
            'timezone' => 'America/Sao_Paulo',
        ]);

        tenancy()->end();

        // 1. Happy path: Author creates PRIVATE clinical note
        $createResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson("/api/tenant/patients/{$patient->id}/medical-records", [
                'professional_id' => $drAuthor->id,
                'title' => 'Intake Evaluation - Confidential',
                'content' => 'Patient exhibits acute anxiety symptoms. Psychotherapy recommended.',
                'visibility' => 'PRIVATE',
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.title', 'Intake Evaluation - Confidential');

        $recordId = $createResponse->json('data.id');

        // 2. Happy path: Author views record
        $authorViewResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/tenant/patients/{$patient->id}/medical-records/{$recordId}?requester_professional_id={$drAuthor->id}");

        $authorViewResponse->assertStatus(200)
            ->assertJsonPath('data.id', $recordId);

        // 3. Sad path: Covering doctor without delegation is rejected with 403 Forbidden
        $unauthorizedResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/tenant/patients/{$patient->id}/medical-records/{$recordId}?requester_professional_id={$drCovering->id}");

        $unauthorizedResponse->assertStatus(403)
            ->assertJsonFragment(['message' => 'Access denied: this medical record is confidential and no active delegation was found.']);

        // 4. Happy path: Author grants temporary delegation to covering doctor
        $delegationResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/tenant/delegations', [
                'grantor_professional_id' => $drAuthor->id,
                'grantee_professional_id' => $drCovering->id,
                'patient_id' => $patient->id,
                'starts_at_utc' => CarbonImmutable::now()->subHour()->toIso8601String(),
                'ends_at_utc' => CarbonImmutable::now()->addHours(24)->toIso8601String(),
                'reason' => 'Weekend coverage',
            ]);

        $delegationResponse->assertStatus(201);

        // 5. Happy path: Now covering doctor has authorized access via active delegation
        $delegatedViewResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson("/api/tenant/patients/{$patient->id}/medical-records/{$recordId}?requester_professional_id={$drCovering->id}");

        $delegatedViewResponse->assertStatus(200)
            ->assertJsonPath('data.id', $recordId);
    }
}

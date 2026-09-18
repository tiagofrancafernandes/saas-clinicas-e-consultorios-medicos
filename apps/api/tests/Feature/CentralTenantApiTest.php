<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CentralTenantApiTest extends TestCase
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

    public function test_can_list_tenants(): void
    {
        $response = $this->getJson('/api/central/tenants');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);
    }

    public function test_can_create_and_provision_tenant_via_api(): void
    {
        $payload = [
            'name' => 'Clinica Esperanca',
            'slug' => 'clinica-esperanca',
            'tax_id' => '98765432000188',
            'timezone' => 'America/Sao_Paulo',
            'settings' => [
                'reminder_notice_hours' => 48,
            ],
        ];

        $response = $this->postJson('/api/central/tenants', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Clinica Esperanca')
            ->assertJsonPath('data.slug', 'clinica-esperanca');

        $tenantId = (string) $response->json('data.id');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'slug' => 'clinica-esperanca',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenantId,
            'status' => 'ACTIVE',
        ]);

        // Verify PostgreSQL schema was automatically created and migrated
        $schemaName = 'tenant_'.$tenantId;
        $schemaExists = DB::selectOne(
            'SELECT schema_name FROM information_schema.schemata WHERE schema_name = :schema',
            ['schema' => $schemaName]
        );

        $this->assertNotNull($schemaExists);
    }
}

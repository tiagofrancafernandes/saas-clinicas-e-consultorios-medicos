<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('slug', 80)->unique();
            $table->string('tax_id', 32)->nullable();
            $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->jsonb('settings')->nullable();
            $table->jsonb('data')->nullable();
            $table->timestampsTz();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('status', 32)->default('ACTIVE')->index();
            $table->string('billing_interval', 32)->default('MONTHLY');

            $table->timestampTz('current_period_start_utc');
            $table->timestampTz('current_period_end_utc')->index();
            $table->timestampTz('grace_period_ends_utc')->nullable();

            $table->boolean('is_exempt')->default(false);
            $table->string('gateway_driver', 32)->nullable();
            $table->string('gateway_subscription_id', 128)->nullable()->index();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tenants');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_insurances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('ans_code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('health_insurance_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('health_insurance_id')->constrained('health_insurances')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('plan_code', 50)->nullable();
            $table->boolean('requires_prior_authorization')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('professional_insurances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->foreignUuid('health_insurance_id')->constrained('health_insurances')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['professional_id', 'health_insurance_id']);
        });

        Schema::create('appointment_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedInteger('price_in_cents')->default(0);
            $table->boolean('is_return')->default(false);
            $table->unsignedSmallInteger('max_return_days_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_types');
        Schema::dropIfExists('professional_insurances');
        Schema::dropIfExists('health_insurance_plans');
        Schema::dropIfExists('health_insurances');
    }
};

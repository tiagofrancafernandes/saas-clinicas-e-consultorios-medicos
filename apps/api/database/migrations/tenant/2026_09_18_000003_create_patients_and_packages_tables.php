<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('phone', 30)->index();
            $table->string('email', 150)->nullable()->index();
            $table->string('tax_id', 32)->nullable()->index();
            $table->date('birth_date')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('patient_health_insurances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('health_insurance_id')->constrained('health_insurances')->restrictOnDelete();
            $table->foreignUuid('health_insurance_plan_id')->constrained('health_insurance_plans')->restrictOnDelete();
            $table->string('card_number', 64);
            $table->date('expiration_date')->nullable();
            $table->timestampsTz();

            $table->index(['patient_id', 'health_insurance_id']);
        });

        Schema::create('packages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('appointment_type_id')->constrained('appointment_types')->restrictOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('session_quantity');
            $table->unsignedInteger('total_price_in_cents');
            $table->unsignedSmallInteger('validity_days')->default(90);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('patient_packages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('package_id')->constrained('packages')->restrictOnDelete();
            $table->unsignedSmallInteger('total_credits');
            $table->unsignedSmallInteger('remaining_credits');
            $table->timestampTz('expires_at_utc')->index();
            $table->string('payment_status', 32)->default('PAID_OFFLINE');
            $table->timestampsTz();

            $table->index(['patient_id', 'remaining_credits', 'expires_at_utc']);
        });

        Schema::create('patient_credit_ledger', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_package_id')->constrained('patient_packages')->cascadeOnDelete();
            $table->uuid('appointment_id')->nullable()->index();
            $table->integer('credits_delta');
            $table->string('operation_type', 32);
            $table->text('description')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_credit_ledger');
        Schema::dropIfExists('patient_packages');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('patient_health_insurances');
        Schema::dropIfExists('patients');
    }
};

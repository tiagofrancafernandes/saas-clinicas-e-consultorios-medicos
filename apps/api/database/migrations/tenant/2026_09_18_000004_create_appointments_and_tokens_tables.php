<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained('professionals')->restrictOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUuid('clinic_room_id')->nullable()->constrained('clinic_rooms')->nullOnDelete();
            $table->foreignUuid('appointment_type_id')->nullable()->constrained('appointment_types')->nullOnDelete();
            $table->uuid('parent_appointment_id')->nullable();
            $table->foreignUuid('patient_package_id')->nullable()->constrained('patient_packages')->nullOnDelete();
            $table->foreignUuid('patient_health_insurance_id')->nullable()->constrained('patient_health_insurances')->nullOnDelete();

            $table->timestampTz('start_time_utc')->index();
            $table->timestampTz('end_time_utc')->index();

            $table->string('status', 32)->default('SCHEDULED')->index();

            $table->timestampTz('arrived_at_utc')->nullable();
            $table->timestampTz('started_at_utc')->nullable();
            $table->timestampTz('completed_at_utc')->nullable()->index();

            $table->unsignedInteger('price_in_cents')->default(0);
            $table->string('payment_status', 32)->default('PENDING')->index();
            $table->string('payment_method', 32)->default('NONE');
            $table->string('health_insurance_auth_code', 64)->nullable();

            $table->string('origin_timezone', 64)->default('America/Sao_Paulo');
            $table->text('notes')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->foreignUuid('created_by_user_id')->nullable();

            $table->timestampsTz();

            $table->index(['professional_id', 'status', 'start_time_utc', 'end_time_utc'], 'idx_appts_overlap_lookup');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreign('parent_appointment_id')->references('id')->on('appointments')->nullOnDelete();
        });

        Schema::create('appointment_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('intended_action', 32);
            $table->timestampTz('expires_at_utc')->index();
            $table->timestampTz('used_at_utc')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_tokens');
        Schema::dropIfExists('appointments');
    }
};

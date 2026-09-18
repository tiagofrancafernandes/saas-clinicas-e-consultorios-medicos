<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_rooms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 80);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('professionals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('name', 120);
            $table->string('specialty', 100);
            $table->string('license_number', 50);
            $table->string('color_code', 10)->default('#3B82F6');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('professional_availabilities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_duration_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['professional_id', 'day_of_week', 'is_active'], 'idx_prof_avail_lookup');
        });

        Schema::create('professional_unavailabilities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->timestampTz('start_time_utc')->index();
            $table->timestampTz('end_time_utc')->index();
            $table->string('reason', 255)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_unavailabilities');
        Schema::dropIfExists('professional_availabilities');
        Schema::dropIfExists('professionals');
        Schema::dropIfExists('clinic_rooms');
    }
};

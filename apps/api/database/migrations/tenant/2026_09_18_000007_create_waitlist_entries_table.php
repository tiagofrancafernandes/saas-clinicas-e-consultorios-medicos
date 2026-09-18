<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->foreignUuid('appointment_type_id')->nullable()->constrained('appointment_types')->nullOnDelete();

            $table->date('preferred_date')->index();
            $table->string('preferred_shift', 32)->default('ANY');
            $table->string('status', 32)->default('WAITING')->index();

            $table->timestampTz('notified_at_utc')->nullable();
            $table->timestampsTz();

            $table->index(['professional_id', 'preferred_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};

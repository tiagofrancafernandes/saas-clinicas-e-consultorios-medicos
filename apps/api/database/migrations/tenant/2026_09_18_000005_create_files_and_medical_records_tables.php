<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('fileable');
            $table->string('collection', 50)->default('default')->index();
            $table->string('visibility', 32)->default('SHARED_TEAM')->index();
            $table->string('disk', 32)->default('s3');
            $table->string('file_path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignUuid('uploaded_by_user_id')->nullable();

            $table->timestampTz('purged_at')->nullable()->index();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['fileable_type', 'fileable_id', 'collection']);
        });

        Schema::create('medical_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('professional_id')->constrained('professionals')->restrictOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();

            $table->string('type', 32)->default('CLINICAL_NOTE')->index();
            $table->string('visibility', 32)->default('PRIVATE')->index();
            $table->string('title', 150);
            $table->text('content');

            $table->timestampsTz();
        });

        Schema::create('professional_delegations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('grantor_professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->foreignUuid('grantee_professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->cascadeOnDelete();

            $table->timestampTz('starts_at_utc')->index();
            $table->timestampTz('ends_at_utc')->index();
            $table->string('reason', 255)->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_delegations');
        Schema::dropIfExists('medical_records');
        Schema::dropIfExists('files');
    }
};

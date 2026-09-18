<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->cascadeOnDelete();

            $table->string('channel', 32)->index();
            $table->string('type', 32)->index();
            $table->string('recipient', 150)->index();
            $table->string('external_message_id', 150)->nullable()->index();
            $table->string('status', 32)->default('QUEUED')->index();

            $table->jsonb('payload')->nullable();
            $table->jsonb('response_metadata')->nullable();
            $table->timestampTz('sent_at_utc')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};

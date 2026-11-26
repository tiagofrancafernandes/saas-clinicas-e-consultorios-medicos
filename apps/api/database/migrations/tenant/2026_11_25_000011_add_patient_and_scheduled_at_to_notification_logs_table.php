<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->foreignUuid('patient_id')->nullable()->after('appointment_id')->constrained('patients')->cascadeOnDelete();
            $table->timestampTz('scheduled_at_utc')->nullable()->after('response_metadata')->index();
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->dropForeign(['patient_id']);
            $table->dropColumn(['patient_id', 'scheduled_at_utc']);
        });
    }
};

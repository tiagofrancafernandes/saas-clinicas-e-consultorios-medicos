<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    public function up(): void
    {
        DB::statement(
            "CREATE INDEX IF NOT EXISTS notification_logs_due_scheduled_idx
             ON notification_logs (scheduled_at_utc)
             WHERE status = 'SCHEDULED'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS notification_logs_due_scheduled_idx');
    }
};

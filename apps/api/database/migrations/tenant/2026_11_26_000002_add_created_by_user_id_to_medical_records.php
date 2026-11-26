<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('appointment_id')->index();
        });

        DB::statement('ALTER TABLE professionals ALTER COLUMN user_id TYPE VARCHAR(64) USING user_id::text');
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table): void {
            $table->dropColumn(['created_by_user_id']);
        });
    }
};

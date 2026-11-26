<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table): void {
            $table->string('status', 32)->default('FINALIZED')->after('visibility')->index();
            $table->jsonb('structured_data')->nullable()->after('content');
            $table->uuid('parent_record_id')->nullable()->after('appointment_id')->index();
            $table->timestampTz('finalized_at_utc')->nullable()->after('structured_data');
            $table->unsignedBigInteger('finalized_by_user_id')->nullable()->after('finalized_at_utc');

            $table->foreign('parent_record_id')
                ->references('id')
                ->on('medical_records')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table): void {
            $table->dropForeign(['parent_record_id']);
            $table->dropColumn([
                'status',
                'structured_data',
                'parent_record_id',
                'finalized_at_utc',
                'finalized_by_user_id',
            ]);
        });
    }
};

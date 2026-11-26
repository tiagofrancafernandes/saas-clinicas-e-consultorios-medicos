<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 32)->default('PATIENT')->after('password');
            $table->uuid('tenant_id')->nullable()->after('role');

            $table->index(['role', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role', 'tenant_id']);
            $table->dropColumn(['role', 'tenant_id']);
        });
    }
};

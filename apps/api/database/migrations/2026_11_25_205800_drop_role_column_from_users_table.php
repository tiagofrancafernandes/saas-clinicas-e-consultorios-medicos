<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['role', 'tenant_id']);
                $table->dropColumn('role');
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['tenant_id']);
                $table->string('role', 32)->default('PATIENT')->after('password');
                $table->index(['role', 'tenant_id']);
            });
        }
    }
};

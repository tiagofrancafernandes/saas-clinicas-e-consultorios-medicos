<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_instances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('label', 120);
            $table->string('phone_number', 32);
            $table->string('instance_name', 120);
            $table->text('token');
            $table->string('category', 32)->default('DEFAULT');
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->string('attendant_name', 120)->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamp('last_connected_at_utc')->nullable();
            $table->timestamps();

            $table->index(['category', 'active']);
            $table->index(['is_default', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_instances');
    }
};

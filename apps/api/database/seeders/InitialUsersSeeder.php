<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Central\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

final class InitialUsersSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'admin@tiagofranca.com'],
            [
                'name' => 'Super Admin',
                'password' => 'power@123',
                'tenant_id' => null,
                'email_verified_at' => now(),
            ]
        );

        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);
    }
}

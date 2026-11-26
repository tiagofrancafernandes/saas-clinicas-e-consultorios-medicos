<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Central\UserRole;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app(PermissionRegistrar::class)->setPermissionClass(Permission::class);
        app(PermissionRegistrar::class)->setRoleClass(Role::class);

        // Grant full access to SUPER_ADMIN via Laravel Gate
        Gate::before(static function (mixed $user, string $ability): ?bool {
            if ($user instanceof User && $user->hasRole(UserRole::SUPER_ADMIN->value)) {
                return true;
            }

            return null;
        });
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Central\PermissionName;
use App\Enums\Central\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        $rolePermissions = [
            UserRole::SUPER_ADMIN->value => array_map(
                static fn (PermissionName $p): string => $p->value,
                PermissionName::cases()
            ),
            UserRole::CLINIC_ADMIN->value => [
                PermissionName::USERS_VIEW->value,
                PermissionName::USERS_CREATE->value,
                PermissionName::USERS_UPDATE->value,
                PermissionName::USERS_PERMISSIONS_ASSIGN->value,
                PermissionName::PATIENTS_VIEW->value,
                PermissionName::PATIENTS_CREATE->value,
                PermissionName::PATIENTS_UPDATE->value,
                PermissionName::APPOINTMENTS_VIEW->value,
                PermissionName::APPOINTMENTS_CREATE->value,
                PermissionName::APPOINTMENTS_CANCEL->value,
                PermissionName::APPOINTMENTS_SUMMON->value,
                PermissionName::WAITLIST_VIEW->value,
                PermissionName::WAITLIST_MANAGE->value,
                PermissionName::MEDICAL_RECORDS_VIEW->value,
                PermissionName::DELEGATIONS_MANAGE->value,
                PermissionName::FILES_VIEW->value,
                PermissionName::FILES_UPLOAD->value,
                PermissionName::CLINIC_SETTINGS_MANAGE->value,
            ],
            UserRole::RECEPTIONIST->value => [
                PermissionName::PATIENTS_VIEW->value,
                PermissionName::PATIENTS_CREATE->value,
                PermissionName::PATIENTS_UPDATE->value,
                PermissionName::APPOINTMENTS_VIEW->value,
                PermissionName::APPOINTMENTS_CREATE->value,
                PermissionName::APPOINTMENTS_CANCEL->value,
                PermissionName::APPOINTMENTS_SUMMON->value,
                PermissionName::WAITLIST_VIEW->value,
                PermissionName::WAITLIST_MANAGE->value,
                PermissionName::FILES_VIEW->value,
                PermissionName::FILES_UPLOAD->value,
            ],
            UserRole::PROFESSIONAL->value => [
                // Note: PATIENTS_CREATE and PATIENTS_UPDATE are NOT granted by default
                PermissionName::PATIENTS_VIEW->value,
                PermissionName::APPOINTMENTS_VIEW->value,
                PermissionName::MEDICAL_RECORDS_VIEW->value,
                PermissionName::MEDICAL_RECORDS_CREATE->value,
                PermissionName::DELEGATIONS_MANAGE->value,
                PermissionName::FILES_VIEW->value,
                PermissionName::FILES_UPLOAD->value,
            ],
            UserRole::PATIENT->value => [
                PermissionName::APPOINTMENTS_VIEW->value,
                PermissionName::FILES_VIEW->value,
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Central\PermissionName;
use App\Enums\Central\UserRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

final class UserManagementController
{
    public function index(): JsonResponse
    {
        $tenantId = (string) tenant('id');

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => $this->formatUser($user));

        return new JsonResponse([
            'data' => $users,
        ], Response::HTTP_OK);
    }

    public function permissionsCatalog(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        $roles = Role::query()
            ->whereIn('name', [
                UserRole::CLINIC_ADMIN->value,
                UserRole::PROFESSIONAL->value,
                UserRole::RECEPTIONIST->value,
                UserRole::PATIENT->value,
            ])
            ->with('permissions')
            ->get()
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'default_permissions' => $role->permissions->pluck('name')->values()->all(),
            ]);

        return new JsonResponse([
            'permissions' => $permissions,
            'roles' => $roles,
        ], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = (string) tenant('id');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'min:6'],
            'role' => [
                'required',
                Rule::enum(UserRole::class),
                Rule::notIn([UserRole::SUPER_ADMIN->value]),
            ],
            'extra_permissions' => ['sometimes', 'array'],
            'extra_permissions.*' => ['string', Rule::enum(PermissionName::class)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'tenant_id' => $tenantId,
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$validated['role']]);

        if (! empty($validated['extra_permissions'])) {
            $user->syncPermissions($validated['extra_permissions']);
        }

        return new JsonResponse([
            'message' => 'User created successfully.',
            'data' => $this->formatUser($user->fresh()),
        ], Response::HTTP_CREATED);
    }

    public function updatePermissions(Request $request, int $userId): JsonResponse
    {
        $tenantId = (string) tenant('id');

        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->find($userId);

        if ($user === null) {
            return new JsonResponse(['message' => 'User not found in this clinic.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'role' => [
                'sometimes',
                Rule::enum(UserRole::class),
                Rule::notIn([UserRole::SUPER_ADMIN->value]),
            ],
            'extra_permissions' => ['required', 'array'],
            'extra_permissions.*' => ['string', Rule::enum(PermissionName::class)],
        ]);

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        $user->syncPermissions($validated['extra_permissions']);

        return new JsonResponse([
            'message' => 'User permissions updated successfully.',
            'data' => $this->formatUser($user->fresh()),
        ], Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'roles' => $user->getRoleNames()->values()->all(),
            'role_permissions' => $user->getPermissionsViaRoles()->pluck('name')->values()->all(),
            'extra_permissions' => $user->getDirectPermissions()->pluck('name')->values()->all(),
            'all_permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Central\PermissionName;
use App\Enums\Central\UserRole;
use App\Models\Tenant\Professional;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

final class UserManagementController
{
    public function index(): JsonResponse
    {
        $tenantId = (string) tenant('id');

        $professionalsByUserId = Professional::query()
            ->whereNotNull('user_id')
            ->get(['id', 'user_id', 'name', 'specialty', 'license_number', 'is_active'])
            ->keyBy('user_id');

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => $this->formatUser($user, $professionalsByUserId->get((string) $user->id)));

        return new JsonResponse([
            'data' => $users,
        ], Response::HTTP_OK);
    }

    public function show(int $userId): JsonResponse
    {
        $user = $this->findClinicUser($userId);

        if ($user === null) {
            return new JsonResponse(['message' => 'User not found in this clinic.'], Response::HTTP_NOT_FOUND);
        }

        $professional = Professional::query()
            ->where('user_id', (string) $user->id)
            ->first(['id', 'user_id', 'name', 'specialty', 'license_number', 'is_active']);

        return new JsonResponse([
            'data' => $this->formatUser($user, $professional),
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
            'specialty' => ['nullable', 'string', 'max:120'],
            'license_number' => ['nullable', 'string', 'max:64'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'tenant_id' => $tenantId,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$validated['role']]);

        if (! empty($validated['extra_permissions'])) {
            $user->syncPermissions($validated['extra_permissions']);
        }

        $professional = null;

        if ($validated['role'] === UserRole::PROFESSIONAL->value) {
            $professional = Professional::create([
                'id' => (string) Str::uuid(),
                'user_id' => (string) $user->id,
                'name' => $user->name,
                'specialty' => $validated['specialty'] ?? 'Clínico Geral',
                'license_number' => $validated['license_number'] ?? 'CRM-PENDENTE',
                'is_active' => true,
            ]);
        }

        return new JsonResponse([
            'message' => 'User created successfully.',
            'data' => $this->formatUser($user->fresh(), $professional),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, int $userId): JsonResponse
    {
        $user = $this->findClinicUser($userId);

        if ($user === null) {
            return new JsonResponse(['message' => 'User not found in this clinic.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique(User::class, 'email')->ignore($user->id)],
            'role' => [
                'sometimes',
                Rule::enum(UserRole::class),
                Rule::notIn([UserRole::SUPER_ADMIN->value]),
            ],
            'specialty' => ['nullable', 'string', 'max:120'],
            'license_number' => ['nullable', 'string', 'max:64'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        $professional = Professional::query()->where('user_id', (string) $user->id)->first();

        if ($user->hasRole(UserRole::PROFESSIONAL->value)) {
            if ($professional !== null) {
                $professional->update([
                    'name' => $user->name,
                    'specialty' => $validated['specialty'] ?? $professional->specialty,
                    'license_number' => $validated['license_number'] ?? $professional->license_number,
                ]);
            } elseif (! empty($validated['specialty']) || ! empty($validated['license_number'])) {
                $professional = Professional::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => (string) $user->id,
                    'name' => $user->name,
                    'specialty' => $validated['specialty'] ?? 'Clínico Geral',
                    'license_number' => $validated['license_number'] ?? 'CRM-PENDENTE',
                    'is_active' => (bool) $user->is_active,
                ]);
            }
        }

        return new JsonResponse([
            'message' => 'User updated successfully.',
            'data' => $this->formatUser($user->fresh(), $professional?->fresh()),
        ], Response::HTTP_OK);
    }

    public function updatePermissions(Request $request, int $userId): JsonResponse
    {
        $user = $this->findClinicUser($userId);

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

        $professional = Professional::query()->where('user_id', (string) $user->id)->first();

        return new JsonResponse([
            'message' => 'User permissions updated successfully.',
            'data' => $this->formatUser($user->fresh(), $professional),
        ], Response::HTTP_OK);
    }

    public function toggleActive(Request $request, int $userId): JsonResponse
    {
        $user = $this->findClinicUser($userId);

        if ($user === null) {
            return new JsonResponse(['message' => 'User not found in this clinic.'], Response::HTTP_NOT_FOUND);
        }

        /** @var User|null $actor */
        $actor = $request->user();

        if ($actor !== null && $actor->id === $user->id) {
            return new JsonResponse([
                'message' => 'You cannot deactivate your own account.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $nextActive = $request->has('is_active')
            ? $request->boolean('is_active')
            : ! (bool) $user->is_active;

        $user->update(['is_active' => $nextActive]);

        if (! $nextActive) {
            $user->tokens()->delete();
        }

        $professional = Professional::query()->where('user_id', (string) $user->id)->first();

        if ($professional !== null) {
            $professional->update(['is_active' => $nextActive]);
        }

        return new JsonResponse([
            'message' => $nextActive ? 'User activated successfully.' : 'User deactivated successfully.',
            'data' => $this->formatUser($user->fresh(), $professional?->fresh()),
        ], Response::HTTP_OK);
    }

    public function sendPasswordReset(int $userId): JsonResponse
    {
        $user = $this->findClinicUser($userId);

        if ($user === null) {
            return new JsonResponse(['message' => 'User not found in this clinic.'], Response::HTTP_NOT_FOUND);
        }

        $token = tenancy()->central(static fn (): string => Password::broker()->createToken($user));
        $user->sendPasswordResetNotification($token);

        return new JsonResponse([
            'message' => 'Password reset email sent successfully.',
        ], Response::HTTP_OK);
    }

    private function findClinicUser(int $userId): ?User
    {
        $tenantId = (string) tenant('id');

        return User::query()
            ->where('tenant_id', $tenantId)
            ->find($userId);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(User $user, ?Professional $professional = null): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'is_active' => (bool) $user->is_active,
            'created_at' => $user->created_at?->toIso8601String(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'roles' => $user->getRoleNames()->values()->all(),
            'role_permissions' => $user->getPermissionsViaRoles()->pluck('name')->values()->all(),
            'extra_permissions' => $user->getDirectPermissions()->pluck('name')->values()->all(),
            'all_permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'professional' => $professional !== null ? [
                'id' => $professional->id,
                'name' => $professional->name,
                'specialty' => $professional->specialty,
                'license_number' => $professional->license_number,
                'is_active' => (bool) $professional->is_active,
            ] : null,
        ];
    }
}

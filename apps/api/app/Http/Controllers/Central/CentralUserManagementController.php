<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Enums\Central\PermissionName;
use App\Enums\Central\UserRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class CentralUserManagementController
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->orderBy('name');

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', (string) $request->query('tenant_id'));
        }

        $users = $query->get()->map(fn (User $user): array => $this->formatUser($user));

        return new JsonResponse(['data' => $users], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'tenant_id' => ['nullable', 'uuid', 'exists:tenants,id'],
            'extra_permissions' => ['sometimes', 'array'],
            'extra_permissions.*' => ['string', Rule::enum(PermissionName::class)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'tenant_id' => $validated['tenant_id'] ?? null,
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
        $user = User::query()->find($userId);

        if ($user === null) {
            return new JsonResponse(['message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'role' => ['sometimes', Rule::enum(UserRole::class)],
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

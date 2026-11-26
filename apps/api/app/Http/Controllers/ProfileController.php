<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Central\PermissionName;
use App\Enums\Central\UserRole;
use App\Models\Central\Tenant;
use App\Models\Tenant\Professional;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class ProfileController
{
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique(User::class, 'email')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'remove_avatar' => ['sometimes', 'boolean'],
        ]);

        $wantsIdentityChange = (isset($validated['name']) && $validated['name'] !== $user->name)
            || (isset($validated['email']) && $validated['email'] !== $user->email);

        if ($wantsIdentityChange && ! $this->canUpdateIdentity($user)) {
            return new JsonResponse([
                'message' => 'You do not have permission to change your name or email.',
            ], Response::HTTP_FORBIDDEN);
        }

        $avatarPath = $user->avatar_path;

        if ($request->boolean('remove_avatar') && $avatarPath !== null) {
            Storage::disk('public')->delete($avatarPath);
            $avatarPath = null;
        }

        if ($request->hasFile('avatar')) {
            if ($avatarPath !== null) {
                Storage::disk('public')->delete($avatarPath);
            }
            $avatarPath = $request->file('avatar')->store("avatars/{$user->id}", 'public');
        }

        $user->update([
            'name' => $validated['name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'avatar_path' => $avatarPath,
        ]);

        $fresh = $user->fresh();
        $professionalId = null;

        if ($fresh !== null && $fresh->tenant_id !== null) {
            $tenant = Tenant::query()->find($fresh->tenant_id);

            if ($tenant !== null) {
                $professionalId = $tenant->run(
                    static fn (): ?string => Professional::query()
                        ->where('user_id', (string) $fresh->id)
                        ->value('id')
                );
            }
        }

        return new JsonResponse([
            'message' => 'Profile updated successfully.',
            'data' => [
                'id' => $fresh?->id,
                'name' => $fresh?->name,
                'email' => $fresh?->email,
                'tenant_id' => $fresh?->tenant_id,
                'is_active' => (bool) $fresh?->is_active,
                'avatar_url' => $fresh?->avatar_path ? Storage::disk('public')->url($fresh->avatar_path) : null,
                'professional_id' => $professionalId,
                'roles' => $fresh?->getRoleNames()->values()->all() ?? [],
                'direct_permissions' => $fresh?->getDirectPermissions()->pluck('name')->values()->all() ?? [],
                'all_permissions' => $fresh?->getAllPermissions()->pluck('name')->values()->all() ?? [],
            ],
        ], Response::HTTP_OK);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return new JsonResponse([
                'message' => 'Current password is incorrect.',
                'errors' => [
                    'current_password' => ['Current password is incorrect.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->update([
            'password' => $validated['password'],
        ]);

        return new JsonResponse([
            'message' => 'Password updated successfully.',
        ], Response::HTTP_OK);
    }

    private function canUpdateIdentity(User $user): bool
    {
        return $user->can(PermissionName::PROFILE_IDENTITY_UPDATE->value)
            || $user->hasAnyRole([UserRole::SUPER_ADMIN->value, UserRole::CLINIC_ADMIN->value]);
    }
}

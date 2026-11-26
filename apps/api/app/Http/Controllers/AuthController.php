<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Central\Tenant;
use App\Models\Tenant\Professional;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class AuthController
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            return new JsonResponse([
                'message' => 'Invalid credentials.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->is_active) {
            return new JsonResponse([
                'message' => 'This account is inactive. Please contact your clinic administrator.',
            ], Response::HTTP_FORBIDDEN);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return new JsonResponse([
            'token' => $token,
            'user' => $this->formatUser($user),
        ], Response::HTTP_OK);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return new JsonResponse($this->formatUser($user), Response::HTTP_OK);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return new JsonResponse([
            'message' => 'Logged out successfully.',
        ], Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        $professionalId = null;

        if ($user->tenant_id !== null) {
            $tenant = Tenant::query()->find($user->tenant_id);

            if ($tenant !== null) {
                $professionalId = $tenant->run(
                    static fn (): ?string => Professional::query()
                        ->where('user_id', (string) $user->id)
                        ->value('id')
                );
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'is_active' => (bool) $user->is_active,
            'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
            'professional_id' => $professionalId,
            'roles' => $user->getRoleNames()->values()->all(),
            'direct_permissions' => $user->getDirectPermissions()->pluck('name')->values()->all(),
            'all_permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }
}

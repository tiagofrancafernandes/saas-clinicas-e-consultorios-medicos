<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Central\UserRole;
use App\Models\Tenant\Patient;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePatientPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if ($actor instanceof Patient) {
            return $next($request);
        }

        if (! $actor instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $roles = $actor->getRoleNames();

        if ($roles->isEmpty() || $roles->contains(UserRole::PATIENT->value)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Access restricted to patients or roleless accounts.',
        ], Response::HTTP_FORBIDDEN);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Central\Subscription;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckTenantSubscription
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Safe read methods are always permitted
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $tenantId = tenant('id');

        if (! $tenantId) {
            return $next($request);
        }

        $centralConnection = config('tenancy.database.central_connection', 'pgsql');

        /** @var Subscription|null $subscription */
        $subscription = Subscription::on($centralConnection)
            ->where('tenant_id', $tenantId)
            ->latest()
            ->first();

        if ($subscription === null) {
            return $next($request);
        }

        if ($subscription->is_exempt) {
            return $next($request);
        }

        if (! $subscription->status->allowsWrites()) {
            return new JsonResponse([
                'message' => 'Clinic subscription is suspended or cancelled. Write operations are locked.',
                'subscription_status' => $subscription->status->value,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}

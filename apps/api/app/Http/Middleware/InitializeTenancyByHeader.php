<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Middleware\IdentificationMiddleware;
use Stancl\Tenancy\Resolvers\RequestDataTenantResolver;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class InitializeTenancyByHeader extends IdentificationMiddleware
{
    public function __construct(
        Tenancy $tenancy,
        RequestDataTenantResolver $resolver
    ) {
        $this->tenancy = $tenancy;
        $this->resolver = $resolver;
    }

    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->method() === 'OPTIONS') {
            return $next($request);
        }

        $tenantId = $request->header('X-Tenant-ID')
            ?? $request->header('X-Tenant')
            ?? $request->query('tenant');

        if (! is_string($tenantId) || trim($tenantId) === '') {
            return new JsonResponse([
                'message' => 'Tenant identification required. Provide X-Tenant-ID header or tenant query parameter.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $tenant = $this->resolver->resolve($tenantId);
            $this->tenancy->initialize($tenant);
        } catch (TenantCouldNotBeIdentifiedException|Throwable) {
            return new JsonResponse([
                'message' => 'Tenant not found or invalid tenant identifier.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}

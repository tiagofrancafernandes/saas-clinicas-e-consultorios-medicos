<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Enums\Central\SubscriptionStatus;
use App\Models\Central\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class TenantController
{
    public function index(): JsonResponse
    {
        $tenants = Tenant::query()
            ->with('subscriptions')
            ->latest()
            ->get();

        return new JsonResponse([
            'data' => $tenants,
        ], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:tenants,slug'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'settings' => ['nullable', 'array'],
        ]);

        $tenantId = (string) Str::uuid();

        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'tax_id' => $validated['tax_id'] ?? null,
            'timezone' => $validated['timezone'] ?? 'America/Sao_Paulo',
            'settings' => $validated['settings'] ?? [],
        ]);

        // Create initial trial/courtesy subscription
        $tenant->subscriptions()->create([
            'status' => SubscriptionStatus::ACTIVE,
            'billing_interval' => 'MONTHLY',
            'current_period_start_utc' => now(),
            'current_period_end_utc' => now()->addDays(30),
            'is_exempt' => false,
        ]);

        return new JsonResponse([
            'message' => 'Tenant created and provisioned successfully.',
            'data' => $tenant->fresh(['subscriptions']),
        ], Response::HTTP_CREATED);
    }

    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::query()
            ->with('subscriptions')
            ->find($id);

        if ($tenant === null) {
            return new JsonResponse([
                'message' => 'Tenant not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'data' => $tenant,
        ], Response::HTTP_OK);
    }
}

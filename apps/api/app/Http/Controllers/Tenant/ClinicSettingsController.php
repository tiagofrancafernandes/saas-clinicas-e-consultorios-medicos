<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Central\PermissionName;
use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ClinicSettingsController
{
    public function show(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $subscription = $tenant->subscriptions()->latest()->first();

        /** @var User|null $user */
        $user = $request->user();
        $canManage = $user !== null && $user->can(PermissionName::CLINIC_SETTINGS_MANAGE->value);

        $data = [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'timezone' => $tenant->timezone,
            'subscription' => [
                'status' => $subscription?->status?->value ?? 'ACTIVE',
                'billing_interval' => $subscription?->billing_interval?->value ?? 'MONTHLY',
                'current_period_end_utc' => $subscription?->current_period_end_utc?->toIso8601String(),
            ],
            'can_manage' => $canManage,
        ];

        if ($canManage) {
            $data['tax_id'] = $tenant->tax_id;
            $data['settings'] = [
                'reminder_notice_hours' => (int) ($tenant->settings['reminder_notice_hours'] ?? 24),
                'cancellation_window_hours' => (int) ($tenant->settings['cancellation_window_hours'] ?? 4),
                'contact_phone' => (string) ($tenant->settings['contact_phone'] ?? ''),
                'address' => (string) ($tenant->settings['address'] ?? ''),
            ];
        }

        return new JsonResponse(['data' => $data], Response::HTTP_OK);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'timezone' => ['required', 'string', 'max:64'],
            'settings' => ['sometimes', 'array'],
            'settings.reminder_notice_hours' => ['sometimes', 'integer', 'min:1', 'max:168'],
            'settings.cancellation_window_hours' => ['sometimes', 'integer', 'min:0', 'max:72'],
            'settings.contact_phone' => ['nullable', 'string', 'max:32'],
            'settings.address' => ['nullable', 'string', 'max:255'],
        ]);

        $mergedSettings = array_merge(
            is_array($tenant->settings) ? $tenant->settings : [],
            $validated['settings'] ?? []
        );

        $tenant->update([
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'settings' => $mergedSettings,
        ]);

        return new JsonResponse([
            'message' => 'Clinic settings updated successfully.',
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'timezone' => $tenant->timezone,
                'settings' => $tenant->settings,
            ],
        ], Response::HTTP_OK);
    }
}

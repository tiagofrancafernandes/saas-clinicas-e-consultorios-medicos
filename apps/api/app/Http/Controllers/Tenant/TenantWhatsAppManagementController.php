<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Tenant\WhatsAppCategory;
use App\Models\Tenant\WhatsAppInstance;
use App\Services\Evolution\EvolutionApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TenantWhatsAppManagementController
{
    public function index(): JsonResponse
    {
        $instances = WhatsAppInstance::query()
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();

        return new JsonResponse(['data' => $instances], Response::HTTP_OK);
    }

    public function update(Request $request, string $instanceId): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:120'],
            'category' => ['sometimes', Rule::enum(WhatsAppCategory::class)],
            'is_default' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'attendant_name' => ['nullable', 'string', 'max:120'],
        ]);

        $instance = DB::transaction(function () use ($instanceId, $validated): ?WhatsAppInstance {
            $target = WhatsAppInstance::query()->find($instanceId);

            if ($target === null) {
                return null;
            }

            if (($validated['is_default'] ?? false) === true) {
                WhatsAppInstance::query()
                    ->where('id', '!=', $target->id)
                    ->update(['is_default' => false]);
            }

            $target->fill($validated);
            $target->save();

            return $target->fresh();
        });

        if ($instance === null) {
            return new JsonResponse(['message' => 'WhatsApp instance not found.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'message' => 'WhatsApp instance updated successfully.',
            'data' => $instance,
        ], Response::HTTP_OK);
    }

    public function testConnection(string $instanceId, EvolutionApiClient $client): JsonResponse
    {
        $instance = WhatsAppInstance::query()->find($instanceId);

        if ($instance === null) {
            return new JsonResponse(['message' => 'WhatsApp instance not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $state = $client->checkConnectionState($instance);
            $instance->update(['last_connected_at_utc' => now()]);

            return new JsonResponse([
                'connected' => true,
                'data' => $state,
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return new JsonResponse([
                'connected' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    public function sendTest(Request $request, string $instanceId, EvolutionApiClient $client): JsonResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:32'],
            'text' => ['required', 'string', 'max:1000'],
        ]);

        $instance = WhatsAppInstance::query()->find($instanceId);

        if ($instance === null) {
            return new JsonResponse(['message' => 'WhatsApp instance not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $result = $client->sendText($instance, $validated['number'], $validated['text']);

            return new JsonResponse([
                'message' => 'Test message sent successfully.',
                'data' => $result,
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }
    }
}

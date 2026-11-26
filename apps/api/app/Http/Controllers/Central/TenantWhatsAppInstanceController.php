<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Enums\Tenant\WhatsAppCategory;
use App\Models\Central\CentralAuditLog;
use App\Models\Central\Tenant;
use App\Models\Tenant\WhatsAppInstance;
use App\Services\Evolution\EvolutionApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TenantWhatsAppInstanceController
{
    public function index(string $tenantId): JsonResponse
    {
        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return new JsonResponse(['message' => 'Tenant not found.'], Response::HTTP_NOT_FOUND);
        }

        $instances = $tenant->run(
            fn (): array => WhatsAppInstance::query()
                ->orderByDesc('is_default')
                ->orderBy('label')
                ->get()
                ->toArray()
        );

        return new JsonResponse(['data' => $instances], Response::HTTP_OK);
    }

    public function store(Request $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return new JsonResponse(['message' => 'Tenant not found.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'phone_number' => ['required', 'string', 'max:32'],
            'instance_name' => ['required', 'string', 'max:120'],
            'token' => ['required', 'string', 'max:512'],
            'category' => ['required', Rule::enum(WhatsAppCategory::class)],
            'is_default' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'attendant_name' => ['nullable', 'string', 'max:120'],
            'settings' => ['nullable', 'array'],
        ]);

        $instance = $tenant->run(function () use ($validated): WhatsAppInstance {
            return DB::transaction(function () use ($validated): WhatsAppInstance {
                $isDefault = (bool) ($validated['is_default'] ?? false);

                if ($isDefault) {
                    WhatsAppInstance::query()->update(['is_default' => false]);
                }

                return WhatsAppInstance::create([
                    'id' => (string) Str::uuid(),
                    'label' => $validated['label'],
                    'phone_number' => $validated['phone_number'],
                    'instance_name' => $validated['instance_name'],
                    'token' => $validated['token'],
                    'category' => $validated['category'],
                    'is_default' => $isDefault,
                    'active' => (bool) ($validated['active'] ?? true),
                    'attendant_name' => $validated['attendant_name'] ?? null,
                    'settings' => $validated['settings'] ?? [],
                ]);
            });
        });

        CentralAuditLog::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'event' => 'WHATSAPP_INSTANCE_CREATED',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'payload' => [
                'instance_id' => $instance->id,
                'label' => $instance->label,
                'instance_name' => $instance->instance_name,
                'category' => $instance->category->value,
                'is_default' => $instance->is_default,
            ],
        ]);

        return new JsonResponse([
            'message' => 'WhatsApp instance created successfully.',
            'data' => $tenant->run(fn (): array => $instance->toArray()),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, string $tenantId, string $instanceId): JsonResponse
    {
        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return new JsonResponse(['message' => 'Tenant not found.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:120'],
            'phone_number' => ['sometimes', 'string', 'max:32'],
            'instance_name' => ['sometimes', 'string', 'max:120'],
            'token' => ['sometimes', 'string', 'max:512'],
            'category' => ['sometimes', Rule::enum(WhatsAppCategory::class)],
            'is_default' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'attendant_name' => ['nullable', 'string', 'max:120'],
            'settings' => ['nullable', 'array'],
        ]);

        $instance = $tenant->run(function () use ($instanceId, $validated): ?WhatsAppInstance {
            return DB::transaction(function () use ($instanceId, $validated): ?WhatsAppInstance {
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
        });

        if ($instance === null) {
            return new JsonResponse(['message' => 'WhatsApp instance not found.'], Response::HTTP_NOT_FOUND);
        }

        CentralAuditLog::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'event' => 'WHATSAPP_INSTANCE_UPDATED',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'payload' => [
                'instance_id' => $instance->id,
                'is_default' => $instance->is_default,
                'active' => $instance->active,
            ],
        ]);

        return new JsonResponse([
            'message' => 'WhatsApp instance updated successfully.',
            'data' => $tenant->run(fn (): array => $instance->toArray()),
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, string $tenantId, string $instanceId): JsonResponse
    {
        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return new JsonResponse(['message' => 'Tenant not found.'], Response::HTTP_NOT_FOUND);
        }

        $deleted = $tenant->run(function () use ($instanceId): bool {
            $target = WhatsAppInstance::query()->find($instanceId);

            if ($target === null) {
                return false;
            }

            return (bool) $target->delete();
        });

        if (! $deleted) {
            return new JsonResponse(['message' => 'WhatsApp instance not found.'], Response::HTTP_NOT_FOUND);
        }

        CentralAuditLog::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'event' => 'WHATSAPP_INSTANCE_DELETED',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'payload' => ['instance_id' => $instanceId],
        ]);

        return new JsonResponse(['message' => 'WhatsApp instance deleted successfully.'], Response::HTTP_OK);
    }

    public function testConnection(string $tenantId, string $instanceId, EvolutionApiClient $client): JsonResponse
    {
        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return new JsonResponse(['message' => 'Tenant not found.'], Response::HTTP_NOT_FOUND);
        }

        $instance = $tenant->run(fn () => WhatsAppInstance::query()->find($instanceId));

        if ($instance === null) {
            return new JsonResponse(['message' => 'WhatsApp instance not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $state = $client->checkConnectionState($instance);

            $tenant->run(function () use ($instance): void {
                $instance->update(['last_connected_at_utc' => now()]);
            });

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

    public function sendTest(Request $request, string $tenantId, string $instanceId, EvolutionApiClient $client): JsonResponse
    {
        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return new JsonResponse(['message' => 'Tenant not found.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'number' => ['required', 'string', 'max:32'],
            'text' => ['required', 'string', 'max:1000'],
        ]);

        $instance = $tenant->run(fn () => WhatsAppInstance::query()->find($instanceId));

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

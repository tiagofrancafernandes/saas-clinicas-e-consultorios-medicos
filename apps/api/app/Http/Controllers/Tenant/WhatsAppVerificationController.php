<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Services\Evolution\EvolutionApiClient;
use App\Services\Messaging\WhatsAppInstanceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class WhatsAppVerificationController
{
    public function status(WhatsAppInstanceResolver $resolver): JsonResponse
    {
        $defaultInstance = $resolver->resolveDefaultActive();

        return new JsonResponse([
            'enabled' => $defaultInstance !== null,
            'default_instance_label' => $defaultInstance?->label,
        ], Response::HTTP_OK);
    }

    public function checkNumber(
        Request $request,
        WhatsAppInstanceResolver $resolver,
        EvolutionApiClient $client
    ): JsonResponse {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $defaultInstance = $resolver->resolveDefaultActive();

        if ($defaultInstance === null) {
            return new JsonResponse([
                'message' => 'No active default WhatsApp instance configured for this tenant.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $results = $client->checkWhatsAppNumbers($defaultInstance, [$validated['phone']]);
            $first = $results[0] ?? [];

            return new JsonResponse([
                'exists' => (bool) ($first['exists'] ?? false),
                'jid' => $first['jid'] ?? null,
                'number' => $first['number'] ?? $validated['phone'],
                'instance_label' => $defaultInstance->label,
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return new JsonResponse([
                'message' => 'Failed to verify WhatsApp number: ' . $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Professional;
use App\Services\Scheduling\SlotCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AvailabilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'professional_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'slot_duration' => ['nullable', 'integer', 'min:10', 'max:120'],
        ]);

        $professional = Professional::query()->find($validated['professional_id']);

        if (! $professional) {
            return response()->json([
                'message' => 'Professional not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $duration = ! empty($validated['slot_duration']) ? (int) $validated['slot_duration'] : null;
        $timezone = is_string(tenant('timezone')) ? (string) tenant('timezone') : 'America/Sao_Paulo';
        $date = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $validated['date'] . ' 00:00:00', $timezone);

        $slots = SlotCalculator::getAvailableSlots($professional, $date, $timezone, $duration);

        return response()->json([
            'data' => $slots,
            'meta' => [
                'professional_id' => $professional->id,
                'date' => $validated['date'],
                'slot_duration' => $duration ?? 30,
                'total_available' => count($slots),
            ],
        ]);
    }
}

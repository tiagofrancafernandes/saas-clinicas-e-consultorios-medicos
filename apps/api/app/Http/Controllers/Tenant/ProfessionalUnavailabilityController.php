<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Professional;
use App\Models\Tenant\ProfessionalUnavailability;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ProfessionalUnavailabilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $timezone = is_string($request->query('timezone')) && $request->query('timezone') !== ''
            ? (string) $request->query('timezone')
            : (is_string(tenant('timezone')) ? (string) tenant('timezone') : 'America/Sao_Paulo');

        $query = ProfessionalUnavailability::query()
            ->with('professional')
            ->orderBy('start_time_utc');

        if ($request->filled('professional_id') && Str::isUuid((string) $request->query('professional_id'))) {
            $query->where('professional_id', (string) $request->query('professional_id'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $rangeStartUtc = CarbonImmutable::parse((string) $request->query('start_date'), $timezone)->startOfDay()->setTimezone('UTC');
            $rangeEndUtc = CarbonImmutable::parse((string) $request->query('end_date'), $timezone)->endOfDay()->setTimezone('UTC');
            $query->where('start_time_utc', '<=', $rangeEndUtc)
                ->where('end_time_utc', '>=', $rangeStartUtc);
        }

        return new JsonResponse([
            'data' => $query->get(),
        ], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'professional_id' => ['required', 'uuid'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $professional = Professional::query()->find($validated['professional_id']);

        if ($professional === null) {
            return new JsonResponse(['message' => 'Professional not found.'], Response::HTTP_NOT_FOUND);
        }

        $timezone = (string) ($validated['timezone'] ?? (is_string(tenant('timezone')) ? tenant('timezone') : 'America/Sao_Paulo'));
        $startUtc = CarbonImmutable::parse((string) $validated['start_at'], $timezone)->setTimezone('UTC');
        $endUtc = CarbonImmutable::parse((string) $validated['end_at'], $timezone)->setTimezone('UTC');

        $block = ProfessionalUnavailability::create([
            'id' => (string) Str::uuid(),
            'professional_id' => $professional->id,
            'start_time_utc' => $startUtc,
            'end_time_utc' => $endUtc,
            'reason' => trim((string) $validated['reason']),
        ]);

        return new JsonResponse([
            'message' => 'Schedule block created successfully.',
            'data' => $block->load('professional'),
        ], Response::HTTP_CREATED);
    }

    public function destroy(string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return new JsonResponse(['message' => 'Schedule block not found.'], Response::HTTP_NOT_FOUND);
        }

        $block = ProfessionalUnavailability::query()->find($id);

        if ($block === null) {
            return new JsonResponse(['message' => 'Schedule block not found.'], Response::HTTP_NOT_FOUND);
        }

        $block->delete();

        return new JsonResponse([
            'message' => 'Schedule block removed successfully.',
        ], Response::HTTP_OK);
    }
}

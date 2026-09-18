<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use App\Models\Tenant\ProfessionalDelegation;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ProfessionalDelegationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grantor_professional_id' => ['required', 'uuid'],
            'grantee_professional_id' => ['required', 'uuid', 'different:grantor_professional_id'],
            'patient_id' => ['nullable', 'uuid'],
            'starts_at_utc' => ['required', 'date'],
            'ends_at_utc' => ['required', 'date', 'after:starts_at_utc'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $grantor = Professional::query()->find($validated['grantor_professional_id']);

        if (! $grantor) {
            return response()->json([
                'message' => 'Grantor professional not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $grantee = Professional::query()->find($validated['grantee_professional_id']);

        if (! $grantee) {
            return response()->json([
                'message' => 'Grantee professional not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if (! empty($validated['patient_id'])) {
            $patient = Patient::query()->find($validated['patient_id']);

            if (! $patient) {
                return response()->json([
                    'message' => 'Target patient not found.',
                ], Response::HTTP_NOT_FOUND);
            }
        }

        $delegation = ProfessionalDelegation::create([
            'id' => (string) Str::uuid(),
            'grantor_professional_id' => $grantor->id,
            'grantee_professional_id' => $grantee->id,
            'patient_id' => $validated['patient_id'] ?? null,
            'starts_at_utc' => CarbonImmutable::parse($validated['starts_at_utc']),
            'ends_at_utc' => CarbonImmutable::parse($validated['ends_at_utc']),
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'message' => 'Professional delegation granted successfully.',
            'data' => $delegation->load(['grantor', 'grantee']),
        ], Response::HTTP_CREATED);
    }
}

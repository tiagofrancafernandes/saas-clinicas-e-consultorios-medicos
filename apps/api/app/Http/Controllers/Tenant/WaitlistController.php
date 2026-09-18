<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use App\Models\Tenant\WaitlistEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class WaitlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WaitlistEntry::query()
            ->with(['patient', 'professional', 'appointmentType'])
            ->orderBy('created_at', 'asc');

        if ($request->has('professional_id')) {
            $query->where('professional_id', $request->query('professional_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('preferred_date')) {
            $query->where('preferred_date', $request->query('preferred_date'));
        }

        $entries = $query->paginate(20);

        return response()->json($entries);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'uuid'],
            'professional_id' => ['required', 'uuid'],
            'preferred_date' => ['required', 'date_format:Y-m-d'],
            'preferred_shift' => ['nullable', 'string', 'in:MORNING,AFTERNOON,ANY'],
            'appointment_type_id' => ['nullable', 'uuid'],
        ]);

        $patient = Patient::query()->find($validated['patient_id']);

        if (! $patient) {
            return response()->json([
                'message' => 'Patient not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $professional = Professional::query()->find($validated['professional_id']);

        if (! $professional) {
            return response()->json([
                'message' => 'Professional not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $entry = WaitlistEntry::create([
            'id' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'professional_id' => $professional->id,
            'preferred_date' => $validated['preferred_date'],
            'preferred_shift' => $validated['preferred_shift'] ?? 'ANY',
            'appointment_type_id' => $validated['appointment_type_id'] ?? null,
            'status' => 'WAITING',
        ]);

        return response()->json([
            'message' => 'Added to waitlist successfully.',
            'data' => $entry->load(['patient', 'professional']),
        ], Response::HTTP_CREATED);
    }
}

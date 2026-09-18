<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Tenant\RecordVisibility;
use App\Http\Controllers\Controller;
use App\Models\Tenant\MedicalRecord;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use App\Services\MedicalRecord\MedicalRecordAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class MedicalRecordController extends Controller
{
    public function index(Request $request, string $patientId): JsonResponse
    {
        $validated = $request->validate([
            'requester_professional_id' => ['required', 'uuid'],
        ]);

        $patient = Patient::query()->find($patientId);
        if (! $patient) {
            return response()->json([
                'message' => 'Patient not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $requester = Professional::query()->find($validated['requester_professional_id']);
        if (! $requester) {
            return response()->json([
                'message' => 'Requesting professional not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $records = MedicalRecordAccessService::getAccessibleRecordsQuery($patient, $requester)
            ->with('professional')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $records,
            'meta' => [
                'patient_id' => $patient->id,
                'requester_id' => $requester->id,
                'total_accessible' => $records->count(),
            ],
        ]);
    }

    public function show(Request $request, string $patientId, string $recordId): JsonResponse
    {
        $validated = $request->validate([
            'requester_professional_id' => ['required', 'uuid'],
        ]);

        $record = MedicalRecord::query()->where('patient_id', $patientId)->find($recordId);
        if (! $record) {
            return response()->json([
                'message' => 'Medical record not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $requester = Professional::query()->find($validated['requester_professional_id']);
        if (! $requester) {
            return response()->json([
                'message' => 'Requesting professional not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            MedicalRecordAccessService::authorizeAccess($record, $requester);

            return response()->json([
                'data' => $record->load('professional'),
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function store(Request $request, string $patientId): JsonResponse
    {
        $patient = Patient::query()->find($patientId);
        if (! $patient) {
            return response()->json([
                'message' => 'Patient not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'professional_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string'],
            'visibility' => ['nullable', 'string', 'in:PRIVATE,SHARED_TEAM,PUBLIC_CLINIC'],
            'type' => ['nullable', 'string', 'max:32'],
            'appointment_id' => ['nullable', 'uuid'],
        ]);

        $professional = Professional::query()->find($validated['professional_id']);
        if (! $professional) {
            return response()->json([
                'message' => 'Professional author not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $visibility = isset($validated['visibility'])
            ? RecordVisibility::from($validated['visibility'])
            : RecordVisibility::PRIVATE;

        $record = MedicalRecord::create([
            'id' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'professional_id' => $professional->id,
            'appointment_id' => $validated['appointment_id'] ?? null,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'visibility' => $visibility,
            'type' => $validated['type'] ?? 'CLINICAL_NOTE',
        ]);

        return response()->json([
            'message' => 'Medical record created successfully.',
            'data' => $record->load('professional'),
        ], Response::HTTP_CREATED);
    }
}

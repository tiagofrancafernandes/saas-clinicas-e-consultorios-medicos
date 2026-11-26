<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Central\PermissionName;
use App\Models\Tenant\MedicalRecord;
use App\Models\Tenant\Patient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class PatientController
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $limit = $request->query('limit');

        $query = Patient::query()->latest();

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($builder) use ($like): void {
                $builder
                    ->where('name', 'ilike', $like)
                    ->orWhere('phone', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('tax_id', 'ilike', $like);
            });
        }

        if ($limit !== null && is_numeric($limit)) {
            $query->limit(max(1, min(100, (int) $limit)));
        }

        return new JsonResponse([
            'data' => $query->get(),
        ], Response::HTTP_OK);
    }

    public function show(Request $request, string $patientId): JsonResponse
    {
        if (! Str::isUuid($patientId)) {
            return new JsonResponse(['message' => 'Patient not found.'], Response::HTTP_NOT_FOUND);
        }

        $patient = Patient::query()
            ->with([
                'appointments' => fn ($q) => $q->with(['professional', 'room'])->latest('start_time_utc'),
            ])
            ->find($patientId);

        if ($patient === null) {
            return new JsonResponse(['message' => 'Patient not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var User|null $user */
        $user = $request->user();
        $medicalRecords = [];

        if ($user !== null && $user->can(PermissionName::MEDICAL_RECORDS_VIEW->value)) {
            $medicalRecords = MedicalRecord::query()
                ->where('patient_id', $patient->id)
                ->with('author')
                ->latest()
                ->get();
        }

        return new JsonResponse([
            'data' => $patient,
            'medical_records' => $medicalRecords,
            'has_internal_note' => $patient->internal_note !== null && $patient->internal_note !== '',
        ], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'birth_date' => ['nullable', 'date'],
            'whatsapp_verified' => ['sometimes', 'boolean'],
        ]);

        $patient = Patient::create([
            'id' => (string) Str::uuid(),
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'tax_id' => $validated['tax_id'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'metadata' => [
                'whatsapp_verified' => (bool) ($validated['whatsapp_verified'] ?? false),
            ],
        ]);

        return new JsonResponse([
            'message' => 'Patient registered successfully.',
            'data' => $patient,
        ], Response::HTTP_CREATED);
    }

    public function revealInternalNote(Request $request, string $patientId): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            return new JsonResponse([
                'message' => 'Invalid password. Internal note access denied.',
            ], Response::HTTP_FORBIDDEN);
        }

        $patient = Str::isUuid($patientId) ? Patient::query()->find($patientId) : null;

        if ($patient === null) {
            return new JsonResponse(['message' => 'Patient not found.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'internal_note' => $patient->internal_note ?? '',
        ], Response::HTTP_OK);
    }

    public function updateInternalNote(Request $request, string $patientId): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'internal_note' => ['nullable', 'string', 'max:5000'],
        ]);

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            return new JsonResponse([
                'message' => 'Invalid password. Cannot update internal note.',
            ], Response::HTTP_FORBIDDEN);
        }

        $patient = Str::isUuid($patientId) ? Patient::query()->find($patientId) : null;

        if ($patient === null) {
            return new JsonResponse(['message' => 'Patient not found.'], Response::HTTP_NOT_FOUND);
        }

        $patient->update([
            'internal_note' => $validated['internal_note'] ?? null,
        ]);

        return new JsonResponse([
            'message' => 'Internal note updated successfully.',
            'has_internal_note' => $patient->internal_note !== null && $patient->internal_note !== '',
        ], Response::HTTP_OK);
    }
}

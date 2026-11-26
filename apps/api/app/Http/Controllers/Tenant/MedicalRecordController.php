<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Central\PermissionName;
use App\Enums\Central\UserRole;
use App\Enums\Tenant\MedicalRecordStatus;
use App\Enums\Tenant\MedicalRecordType;
use App\Enums\Tenant\RecordVisibility;
use App\Http\Controllers\Controller;
use App\Models\Tenant\MedicalRecord;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use App\Models\Tenant\ProfessionalDelegation;
use App\Models\User;
use App\Services\MedicalRecord\MedicalRecordAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class MedicalRecordController extends Controller
{
    public function list(Request $request): JsonResponse
    {
        $requester = $this->resolveRequesterProfessional($request);
        $query = MedicalRecord::query()
            ->whereNull('parent_record_id')
            ->with(['patient:id,name,phone', 'professional:id,name,specialty', 'createdBy:id,name,email', 'addendums.professional:id,name,specialty', 'addendums.createdBy:id,name,email']);

        if ($requester !== null) {
            $now = CarbonImmutable::now('UTC');
            $grantorIds = ProfessionalDelegation::query()
                ->where('grantee_professional_id', $requester->id)
                ->where('starts_at_utc', '<=', $now)
                ->where('ends_at_utc', '>=', $now)
                ->pluck('grantor_professional_id')
                ->toArray();

            $query->where(function (Builder $outer) use ($requester, $grantorIds): void {
                $outer->where('professional_id', $requester->id)
                    ->orWhere(function (Builder $finalized) use ($grantorIds): void {
                        $finalized->where('status', MedicalRecordStatus::FINALIZED->value)
                            ->where(function (Builder $vis) use ($grantorIds): void {
                                $vis->whereIn('visibility', [RecordVisibility::PUBLIC_CLINIC->value, RecordVisibility::SHARED_TEAM->value])
                                    ->orWhere(function (Builder $delegated) use ($grantorIds): void {
                                        $delegated->where('visibility', RecordVisibility::PRIVATE->value)
                                            ->whereIn('professional_id', $grantorIds);
                                    });
                            });
                    });
            });
        } else {
            // Non-professional clinic staff (e.g. Clinic Admin) can view finalized clinic/shared records
            $query->where('status', MedicalRecordStatus::FINALIZED->value)
                ->whereIn('visibility', [RecordVisibility::PUBLIC_CLINIC->value, RecordVisibility::SHARED_TEAM->value]);
        }

        if ($request->filled('patient_id') && Str::isUuid((string) $request->query('patient_id'))) {
            $query->where('patient_id', (string) $request->query('patient_id'));
        }

        if ($request->filled('professional_id') && Str::isUuid((string) $request->query('professional_id'))) {
            $query->where('professional_id', (string) $request->query('professional_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->query('type'));
        }

        if ($request->filled('visibility')) {
            $query->where('visibility', (string) $request->query('visibility'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', (string) $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', (string) $request->query('date_to'));
        }

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function (Builder $sub) use ($like): void {
                $sub->where('title', 'ilike', $like)
                    ->orWhere('content', 'ilike', $like)
                    ->orWhereHas('patient', fn (Builder $p) => $p->where('name', 'ilike', $like));
            });
        }

        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));
        $paginator = $query->orderByRaw("CASE WHEN status = 'DRAFT' THEN 0 ELSE 1 END")
            ->latest('updated_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function index(Request $request, string $patientId): JsonResponse
    {
        $validated = $request->validate([
            'requester_professional_id' => ['required', 'uuid'],
        ]);

        $patient = Str::isUuid($patientId) ? Patient::query()->find($patientId) : null;

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
            ->with(['professional', 'addendums.professional'])
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

        $record = Str::isUuid($patientId) && Str::isUuid($recordId)
            ? MedicalRecord::query()->where('patient_id', $patientId)->find($recordId)
            : null;

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
                'data' => $record->load(['professional', 'addendums.professional']),
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function showRecord(Request $request, string $recordId): JsonResponse
    {
        $record = Str::isUuid($recordId)
            ? MedicalRecord::query()->with(['patient:id,name,phone', 'professional:id,name,specialty', 'createdBy:id,name,email', 'addendums.professional:id,name,specialty', 'addendums.createdBy:id,name,email'])->find($recordId)
            : null;

        if ($record === null) {
            return response()->json(['message' => 'Medical record not found.'], Response::HTTP_NOT_FOUND);
        }

        $requester = $this->resolveRequesterProfessional($request);

        if ($requester !== null) {
            try {
                MedicalRecordAccessService::authorizeAccess($record, $requester);
            } catch (AuthorizationException $e) {
                return response()->json(['message' => $e->getMessage()], Response::HTTP_FORBIDDEN);
            }
        } elseif ($record->status === MedicalRecordStatus::DRAFT || $record->visibility === RecordVisibility::PRIVATE) {
            return response()->json(['message' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json(['data' => $record]);
    }

    public function store(Request $request, string $patientId): JsonResponse
    {
        $patient = Str::isUuid($patientId) ? Patient::query()->find($patientId) : null;

        if (! $patient) {
            return response()->json([
                'message' => 'Patient not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'professional_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:150'],
            'content' => ['nullable', 'string'],
            'visibility' => ['nullable', 'string', Rule::enum(RecordVisibility::class)],
            'type' => ['nullable', 'string', Rule::enum(MedicalRecordType::class)],
            'status' => ['nullable', 'string', Rule::enum(MedicalRecordStatus::class)],
            'structured_data' => ['nullable', 'array'],
            'appointment_id' => ['nullable', 'uuid'],
            'parent_record_id' => ['nullable', 'uuid'],
        ]);

        /** @var User|null $user */
        $user = $request->user();
        $requesterProfessional = $this->resolveRequesterProfessional($request);
        $canCreateOnBehalf = $user !== null && (
            $user->can(PermissionName::MEDICAL_RECORDS_CREATE_ON_BEHALF->value)
            || $user->hasAnyRole([UserRole::SUPER_ADMIN->value, UserRole::CLINIC_ADMIN->value])
        );

        $professional = null;

        if (! empty($validated['professional_id'])) {
            $professional = Professional::query()->find($validated['professional_id']);

            if (! $professional) {
                return response()->json([
                    'message' => 'Professional author not found.',
                ], Response::HTTP_NOT_FOUND);
            }

            if (($requesterProfessional === null || $professional->id !== $requesterProfessional->id) && ! $canCreateOnBehalf) {
                return response()->json([
                    'message' => 'You do not have permission to create medical records on behalf of another professional.',
                ], Response::HTTP_FORBIDDEN);
            }
        } else {
            $professional = $requesterProfessional;

            if ($professional === null && $user !== null && $user->hasRole(UserRole::PROFESSIONAL->value)) {
                $professional = Professional::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => (string) $user->id,
                    'name' => $user->name,
                    'specialty' => 'Clínico Geral',
                    'license_number' => 'CRM-PENDENTE',
                    'is_active' => true,
                ]);
            }
        }

        if (! $professional) {
            return response()->json([
                'message' => 'Please select a professional for this medical record.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = isset($validated['status'])
            ? MedicalRecordStatus::from($validated['status'])
            : MedicalRecordStatus::FINALIZED;

        $visibility = isset($validated['visibility'])
            ? RecordVisibility::from($validated['visibility'])
            : RecordVisibility::PRIVATE;

        $type = isset($validated['type'])
            ? MedicalRecordType::from($validated['type'])
            : MedicalRecordType::CLINICAL_NOTE;

        if (! empty($validated['parent_record_id'])) {
            $parent = MedicalRecord::query()->where('patient_id', $patient->id)->find($validated['parent_record_id']);

            if ($parent === null) {
                return response()->json(['message' => 'Parent medical record not found.'], Response::HTTP_NOT_FOUND);
            }
            $type = MedicalRecordType::ADDENDUM;
            $status = MedicalRecordStatus::FINALIZED;
        }

        /** @var User|null $user */
        $user = $request->user();

        $record = MedicalRecord::create([
            'id' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'professional_id' => $professional->id,
            'appointment_id' => $validated['appointment_id'] ?? null,
            'parent_record_id' => $validated['parent_record_id'] ?? null,
            'created_by_user_id' => $user?->id,
            'title' => $validated['title'],
            'content' => (string) ($validated['content'] ?? ''),
            'structured_data' => $validated['structured_data'] ?? null,
            'visibility' => $visibility,
            'type' => $type,
            'status' => $status,
            'finalized_at_utc' => $status === MedicalRecordStatus::FINALIZED ? CarbonImmutable::now('UTC') : null,
            'finalized_by_user_id' => $status === MedicalRecordStatus::FINALIZED ? $user?->id : null,
        ]);

        return response()->json([
            'message' => 'Medical record created successfully.',
            'data' => $record->load(['patient:id,name,phone', 'professional:id,name,specialty', 'createdBy:id,name,email', 'addendums.professional:id,name,specialty']),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, string $recordId): JsonResponse
    {
        $record = Str::isUuid($recordId) ? MedicalRecord::query()->find($recordId) : null;

        if ($record === null) {
            return response()->json(['message' => 'Medical record not found.'], Response::HTTP_NOT_FOUND);
        }

        if (! $record->status->isEditable()) {
            return response()->json([
                'message' => 'Only draft medical records can be edited. Create an addendum instead.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $requester = $this->resolveRequesterProfessional($request);

        if ($requester !== null && $record->professional_id !== $requester->id) {
            return response()->json(['message' => 'Only the author professional can edit this draft.'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'content' => ['nullable', 'string'],
            'visibility' => ['sometimes', 'string', Rule::enum(RecordVisibility::class)],
            'type' => ['sometimes', 'string', Rule::enum(MedicalRecordType::class)],
            'structured_data' => ['nullable', 'array'],
            'appointment_id' => ['nullable', 'uuid'],
        ]);

        $record->update([
            'title' => $validated['title'] ?? $record->title,
            'content' => array_key_exists('content', $validated) ? (string) ($validated['content'] ?? '') : $record->content,
            'visibility' => isset($validated['visibility']) ? RecordVisibility::from($validated['visibility']) : $record->visibility,
            'type' => isset($validated['type']) ? MedicalRecordType::from($validated['type']) : $record->type,
            'structured_data' => array_key_exists('structured_data', $validated) ? $validated['structured_data'] : $record->structured_data,
            'appointment_id' => array_key_exists('appointment_id', $validated) ? $validated['appointment_id'] : $record->appointment_id,
        ]);

        return response()->json([
            'message' => 'Draft medical record updated successfully.',
            'data' => $record->fresh(['patient:id,name,phone', 'professional:id,name,specialty', 'addendums.professional:id,name,specialty']),
        ]);
    }

    public function finalize(Request $request, string $recordId): JsonResponse
    {
        $record = Str::isUuid($recordId) ? MedicalRecord::query()->find($recordId) : null;

        if ($record === null) {
            return response()->json(['message' => 'Medical record not found.'], Response::HTTP_NOT_FOUND);
        }

        if (! $record->status->isEditable()) {
            return response()->json([
                'message' => 'Medical record is already finalized.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $requester = $this->resolveRequesterProfessional($request);

        if ($requester !== null && $record->professional_id !== $requester->id) {
            return response()->json(['message' => 'Only the author professional can finalize this draft.'], Response::HTTP_FORBIDDEN);
        }

        /** @var User|null $user */
        $user = $request->user();

        $record->update([
            'status' => MedicalRecordStatus::FINALIZED,
            'finalized_at_utc' => CarbonImmutable::now('UTC'),
            'finalized_by_user_id' => $user?->id,
        ]);

        return response()->json([
            'message' => 'Medical record finalized successfully.',
            'data' => $record->fresh(['patient:id,name,phone', 'professional:id,name,specialty', 'addendums.professional:id,name,specialty']),
        ]);
    }

    private function resolveRequesterProfessional(Request $request): ?Professional
    {
        $paramId = $request->query('requester_professional_id');

        if (is_string($paramId) && Str::isUuid($paramId)) {
            return Professional::query()->find($paramId);
        }

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return Professional::query()->where('user_id', (string) $user->id)->first();
    }
}

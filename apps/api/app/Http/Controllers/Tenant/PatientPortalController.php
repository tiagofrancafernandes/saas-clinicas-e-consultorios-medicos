<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Appointment\CancelAppointmentAction;
use App\Enums\Tenant\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\File;
use App\Models\Tenant\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PatientPortalController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $patient = $this->resolvePatient($request);
        $clinic = $this->clinicMeta();

        if ($patient === null) {
            return response()->json([
                'patient' => null,
                'clinic' => $clinic,
                'upcoming_count' => 0,
                'documents_count' => 0,
                'next_appointment' => null,
            ]);
        }

        $nowUtc = CarbonImmutable::now('UTC');

        $upcomingQuery = Appointment::query()
            ->where('patient_id', $patient->id)
            ->where('start_time_utc', '>=', $nowUtc)
            ->whereNotIn('status', [
                AppointmentStatus::CANCELLED->value,
                AppointmentStatus::COMPLETED->value,
                AppointmentStatus::NO_SHOW->value,
            ]);

        $upcomingCount = (clone $upcomingQuery)->count();

        $nextAppointment = (clone $upcomingQuery)
            ->with(['professional:id,name,specialty', 'clinicRoom:id,name', 'appointmentType:id,name,duration_minutes'])
            ->orderBy('start_time_utc')
            ->first();

        $documentsCount = File::query()
            ->where('fileable_type', Patient::class)
            ->where('fileable_id', $patient->id)
            ->count();

        return response()->json([
            'patient' => $this->formatPatient($patient),
            'clinic' => $clinic,
            'upcoming_count' => $upcomingCount,
            'documents_count' => $documentsCount,
            'next_appointment' => $nextAppointment !== null
                ? $this->formatAppointment($nextAppointment, $clinic['cancellation_window_hours'])
                : null,
        ]);
    }

    public function appointments(Request $request): JsonResponse
    {
        $patient = $this->resolvePatient($request);
        $clinic = $this->clinicMeta();

        if ($patient === null) {
            return response()->json(['data' => []]);
        }

        $tab = (string) $request->query('tab', 'upcoming');
        $nowUtc = CarbonImmutable::now('UTC');

        $query = Appointment::query()
            ->where('patient_id', $patient->id)
            ->with(['professional:id,name,specialty', 'clinicRoom:id,name', 'appointmentType:id,name,duration_minutes']);

        if ($tab === 'cancelled') {
            $query->where('status', AppointmentStatus::CANCELLED->value)
                ->orderByDesc('start_time_utc');
        } elseif ($tab === 'history') {
            $query->where(function ($q) use ($nowUtc): void {
                $q->where('start_time_utc', '<', $nowUtc)
                    ->orWhereIn('status', [
                        AppointmentStatus::COMPLETED->value,
                        AppointmentStatus::NO_SHOW->value,
                    ]);
            })->where('status', '!=', AppointmentStatus::CANCELLED->value)
                ->orderByDesc('start_time_utc');
        } else {
            $query->where('start_time_utc', '>=', $nowUtc)
                ->whereNotIn('status', [
                    AppointmentStatus::CANCELLED->value,
                    AppointmentStatus::COMPLETED->value,
                    AppointmentStatus::NO_SHOW->value,
                ])
                ->orderBy('start_time_utc');
        }

        $appointments = $query->get()->map(
            fn (Appointment $appointment): array => $this->formatAppointment(
                $appointment,
                $clinic['cancellation_window_hours']
            )
        )->values()->all();

        return response()->json(['data' => $appointments]);
    }

    public function confirmAppointment(Request $request, string $id): JsonResponse
    {
        $patient = $this->resolvePatient($request);

        if ($patient === null || ! Str::isUuid($id)) {
            return response()->json(['message' => 'Appointment not found.'], Response::HTTP_NOT_FOUND);
        }

        $appointment = Appointment::query()
            ->where('patient_id', $patient->id)
            ->find($id);

        if ($appointment === null) {
            return response()->json(['message' => 'Appointment not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($appointment->status->isFinalized()) {
            return response()->json([
                'message' => 'This appointment has already been finalized and cannot be confirmed.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $appointment->update(['status' => AppointmentStatus::CONFIRMED]);
        $appointment->load(['professional:id,name,specialty', 'clinicRoom:id,name', 'appointmentType:id,name,duration_minutes']);

        $clinic = $this->clinicMeta();

        return response()->json([
            'message' => 'Appointment confirmed successfully.',
            'data' => $this->formatAppointment($appointment, $clinic['cancellation_window_hours']),
        ]);
    }

    public function cancelAppointment(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $patient = $this->resolvePatient($request);

        if ($patient === null || ! Str::isUuid($id)) {
            return response()->json(['message' => 'Appointment not found.'], Response::HTTP_NOT_FOUND);
        }

        $appointment = Appointment::query()
            ->where('patient_id', $patient->id)
            ->find($id);

        if ($appointment === null) {
            return response()->json(['message' => 'Appointment not found.'], Response::HTTP_NOT_FOUND);
        }

        $clinic = $this->clinicMeta();

        if (! $this->canCancelAppointment($appointment, $clinic['cancellation_window_hours'])) {
            return response()->json([
                'message' => "Cancellation is only allowed at least {$clinic['cancellation_window_hours']}h before the appointment.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $cancelled = CancelAppointmentAction::execute(
                $appointment,
                $validated['reason'] ?? 'Cancelado pelo paciente no portal'
            );
            $cancelled->load(['professional:id,name,specialty', 'clinicRoom:id,name', 'appointmentType:id,name,duration_minutes']);

            return response()->json([
                'message' => 'Appointment cancelled successfully.',
                'data' => $this->formatAppointment($cancelled, $clinic['cancellation_window_hours']),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function documents(Request $request): JsonResponse
    {
        $patient = $this->resolvePatient($request);

        if ($patient === null) {
            return response()->json(['data' => []]);
        }

        $files = File::query()
            ->where('fileable_type', Patient::class)
            ->where('fileable_id', $patient->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (File $file): array => [
                'id' => $file->id,
                'collection' => $file->collection ?? 'general',
                'original_filename' => $file->original_filename,
                'mime_type' => $file->mime_type,
                'size_bytes' => $file->size_bytes,
                'created_at' => $file->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json(['data' => $files]);
    }

    public function downloadDocument(Request $request, string $fileId): StreamedResponse|JsonResponse
    {
        $patient = $this->resolvePatient($request);

        if ($patient === null || ! Str::isUuid($fileId)) {
            return response()->json(['message' => 'Document not found.'], Response::HTTP_NOT_FOUND);
        }

        $file = File::query()
            ->where('fileable_type', Patient::class)
            ->where('fileable_id', $patient->id)
            ->find($fileId);

        if ($file === null) {
            return response()->json(['message' => 'Document not found.'], Response::HTTP_NOT_FOUND);
        }

        if (! Storage::disk($file->disk)->exists($file->file_path)) {
            return response()->json(['message' => 'File content not available on storage disk.'], Response::HTTP_NOT_FOUND);
        }

        return Storage::disk($file->disk)->download($file->file_path, $file->original_filename);
    }

    public function profile(Request $request): JsonResponse
    {
        $patient = $this->resolvePatient($request);

        return response()->json([
            'patient' => $patient !== null ? $this->formatPatient($patient) : null,
            'clinic' => $this->clinicMeta(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $patient = $this->resolvePatient($request);

        if ($patient === null) {
            return response()->json(['message' => 'Patient record not found for this account.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'phone' => ['sometimes', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);

        $patient->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'patient' => $this->formatPatient($patient->fresh()),
        ]);
    }

    private function resolvePatient(Request $request): ?Patient
    {
        $actor = $request->user();

        if ($actor instanceof Patient) {
            return $actor;
        }

        if (! $actor instanceof User) {
            return null;
        }

        $byUserId = Patient::query()
            ->where('metadata->user_id', (string) $actor->id)
            ->first();

        if ($byUserId !== null) {
            return $byUserId;
        }

        return Patient::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($actor->email)])
            ->first();
    }

    /**
     * @return array{name: string, timezone: string, cancellation_window_hours: int}
     */
    private function clinicMeta(): array
    {
        $settings = is_array(tenant('settings')) ? tenant('settings') : [];

        return [
            'name' => (string) (tenant('name') ?? 'Clínica'),
            'timezone' => (string) (tenant('timezone') ?? 'America/Sao_Paulo'),
            'cancellation_window_hours' => (int) ($settings['cancellation_window_hours'] ?? 4),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPatient(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'name' => $patient->name,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'birth_date' => $patient->birth_date?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAppointment(Appointment $appointment, int $cancellationWindowHours): array
    {
        $deadline = $appointment->start_time_utc->subHours(max(0, $cancellationWindowHours));

        return [
            'id' => $appointment->id,
            'status' => $appointment->status->value,
            'start_time_utc' => $appointment->start_time_utc->toIso8601String(),
            'end_time_utc' => $appointment->end_time_utc->toIso8601String(),
            'notes' => $appointment->notes,
            'cancellation_reason' => $appointment->cancellation_reason,
            'can_confirm' => $appointment->status === AppointmentStatus::SCHEDULED
                && $appointment->start_time_utc->isFuture(),
            'can_cancel' => $this->canCancelAppointment($appointment, $cancellationWindowHours),
            'cancellation_deadline_utc' => $deadline->toIso8601String(),
            'professional' => $appointment->professional ? [
                'id' => $appointment->professional->id,
                'name' => $appointment->professional->name,
                'specialty' => $appointment->professional->specialty,
            ] : null,
            'clinic_room' => $appointment->clinicRoom ? [
                'id' => $appointment->clinicRoom->id,
                'name' => $appointment->clinicRoom->name,
            ] : null,
            'appointment_type' => $appointment->appointmentType ? [
                'id' => $appointment->appointmentType->id,
                'name' => $appointment->appointmentType->name,
                'duration_minutes' => $appointment->appointmentType->duration_minutes,
            ] : null,
        ];
    }

    private function canCancelAppointment(Appointment $appointment, int $windowHours): bool
    {
        if ($appointment->status->isFinalized()) {
            return false;
        }

        if ($appointment->start_time_utc->isPast()) {
            return false;
        }

        if ($windowHours <= 0) {
            return true;
        }

        return CarbonImmutable::now('UTC')->addHours($windowHours)->lte($appointment->start_time_utc);
    }
}

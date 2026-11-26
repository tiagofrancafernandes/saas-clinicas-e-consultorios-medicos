<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Appointment\BookAppointmentAction;
use App\Actions\Appointment\CancelAppointmentAction;
use App\Actions\Appointment\NotifyPatientOnAppointmentAction;
use App\Actions\Appointment\RescheduleAppointmentAction;
use App\Enums\Tenant\AppointmentStatus;
use App\Events\Tenant\PatientSummonedToRoom;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $timezone = is_string($request->query('timezone')) && $request->query('timezone') !== ''
            ? (string) $request->query('timezone')
            : (is_string(tenant('timezone')) ? (string) tenant('timezone') : 'America/Sao_Paulo');

        $query = Appointment::query()
            ->with(['professional', 'patient', 'clinicRoom', 'appointmentType'])
            ->orderBy('start_time_utc', 'asc');

        if ($request->filled('professional_id')) {
            $query->where('professional_id', (string) $request->query('professional_id'));
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', (string) $request->query('patient_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $rangeStart = CarbonImmutable::parse((string) $request->query('start_date'), $timezone)->startOfDay()->setTimezone('UTC');
            $rangeEnd = CarbonImmutable::parse((string) $request->query('end_date'), $timezone)->endOfDay()->setTimezone('UTC');
            $query->whereBetween('start_time_utc', [$rangeStart, $rangeEnd]);
        } elseif ($request->filled('date')) {
            $startOfDay = CarbonImmutable::parse((string) $request->query('date'), $timezone)->startOfDay()->setTimezone('UTC');
            $endOfDay = CarbonImmutable::parse((string) $request->query('date'), $timezone)->endOfDay()->setTimezone('UTC');
            $query->whereBetween('start_time_utc', [$startOfDay, $endOfDay]);
        }

        $perPage = max(1, min(200, (int) $request->integer('per_page', 25)));
        $appointments = $query->paginate($perPage);

        return response()->json($appointments);
    }

    public function show(string $id): JsonResponse
    {
        $appointment = Appointment::query()
            ->with(['professional', 'patient', 'appointmentType', 'patientPackage'])
            ->find($id);

        if (! $appointment) {
            return response()->json([
                'message' => 'Appointment not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => $appointment,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'professional_id' => ['required', 'uuid'],
            'patient_id' => ['required', 'uuid'],
            'clinic_room_id' => ['nullable', 'uuid'],
            'appointment_type_id' => ['nullable', 'uuid'],
            'parent_appointment_id' => ['nullable', 'uuid'],
            'patient_package_id' => ['nullable', 'uuid'],
            'patient_health_insurance_id' => ['nullable', 'uuid'],
            'health_insurance_auth_code' => ['nullable', 'string', 'max:64'],
            'start_time_utc' => ['required', 'date'],
            'end_time_utc' => ['required', 'date', 'after:start_time_utc'],
            'origin_timezone' => ['nullable', 'string', 'timezone:all'],
            'notes' => ['nullable', 'string'],
            'notify_patient' => ['sometimes', 'boolean'],
            'notification_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $originTimezone = (string) ($validated['origin_timezone'] ?? (is_string(tenant('timezone')) ? tenant('timezone') : 'America/Sao_Paulo'));
        $startTimeUtc = CarbonImmutable::parse((string) $validated['start_time_utc'], $originTimezone)->setTimezone('UTC');
        $endTimeUtc = CarbonImmutable::parse((string) $validated['end_time_utc'], $originTimezone)->setTimezone('UTC');

        if ($startTimeUtc->lte(CarbonImmutable::now('UTC'))) {
            return response()->json([
                'message' => 'Cannot schedule an appointment in the past.',
                'errors' => [
                    'start_time_utc' => ['The start time must be in the future.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $appointment = BookAppointmentAction::execute([
                'professional_id' => $validated['professional_id'],
                'patient_id' => $validated['patient_id'],
                'clinic_room_id' => $validated['clinic_room_id'] ?? null,
                'appointment_type_id' => $validated['appointment_type_id'] ?? null,
                'parent_appointment_id' => $validated['parent_appointment_id'] ?? null,
                'patient_package_id' => $validated['patient_package_id'] ?? null,
                'patient_health_insurance_id' => $validated['patient_health_insurance_id'] ?? null,
                'health_insurance_auth_code' => $validated['health_insurance_auth_code'] ?? null,
                'start_time_utc' => $startTimeUtc,
                'end_time_utc' => $endTimeUtc,
                'origin_timezone' => $originTimezone,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ((bool) ($validated['notify_patient'] ?? false)) {
                NotifyPatientOnAppointmentAction::execute(
                    $appointment,
                    'APPOINTMENT_BOOKED',
                    $validated['notification_message'] ?? null
                );
            }

            return response()->json([
                'message' => 'Appointment booked successfully.',
                'data' => $appointment->load(['professional', 'patient', 'clinicRoom']),
            ], Response::HTTP_CREATED);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'already has an appointment')
                || str_contains($e->getMessage(), 'already occupied')
                || str_contains($e->getMessage(), 'unavailable')
                || str_contains($e->getMessage(), 'collides')
                ? Response::HTTP_CONFLICT
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return response()->json([
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function confirm(string $id): JsonResponse
    {
        $appointment = Str::isUuid($id) ? Appointment::query()->find($id) : null;

        if (! $appointment) {
            return response()->json([
                'message' => 'Appointment not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($appointment->status->isFinalized()) {
            return response()->json([
                'message' => "Cannot confirm an appointment that has already been finalized ({$appointment->status->value}).",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $appointment->update([
            'status' => AppointmentStatus::CONFIRMED,
        ]);

        return response()->json([
            'message' => 'Appointment confirmed successfully.',
            'data' => $appointment->fresh()->load(['professional', 'patient', 'clinicRoom']),
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'notify_patient' => ['sometimes', 'boolean'],
            'notification_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $appointment = Appointment::query()->find($id);

        if (! $appointment) {
            return response()->json([
                'message' => 'Appointment not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $cancelled = CancelAppointmentAction::execute(
                $appointment,
                $validated['reason'] ?? 'Patient cancelled via portal'
            );

            if ((bool) ($validated['notify_patient'] ?? false)) {
                NotifyPatientOnAppointmentAction::execute(
                    $cancelled,
                    'APPOINTMENT_CANCELLED',
                    $validated['notification_message'] ?? null
                );
            }

            return response()->json([
                'message' => 'Appointment cancelled successfully.',
                'data' => $cancelled,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function reschedule(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'new_start_time_utc' => ['required', 'date'],
            'new_end_time_utc' => ['required', 'date', 'after:new_start_time_utc'],
            'origin_timezone' => ['nullable', 'string', 'timezone:all'],
            'clinic_room_id' => ['nullable', 'uuid'],
            'notify_patient' => ['sometimes', 'boolean'],
            'notification_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $appointment = Appointment::query()->find($id);

        if (! $appointment) {
            return response()->json([
                'message' => 'Appointment not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $originTimezone = (string) ($validated['origin_timezone'] ?? $appointment->origin_timezone ?: 'America/Sao_Paulo');
        $newStartUtc = CarbonImmutable::parse((string) $validated['new_start_time_utc'], $originTimezone)->setTimezone('UTC');
        $newEndUtc = CarbonImmutable::parse((string) $validated['new_end_time_utc'], $originTimezone)->setTimezone('UTC');

        if ($newStartUtc->lte(CarbonImmutable::now('UTC'))) {
            return response()->json([
                'message' => 'Cannot reschedule an appointment to the past.',
                'errors' => [
                    'new_start_time_utc' => ['The new start time must be in the future.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $rescheduled = RescheduleAppointmentAction::execute(
                $appointment,
                $newStartUtc,
                $newEndUtc,
                $validated['clinic_room_id'] ?? null
            );

            if ((bool) ($validated['notify_patient'] ?? false)) {
                NotifyPatientOnAppointmentAction::execute(
                    $rescheduled,
                    'APPOINTMENT_RESCHEDULED',
                    $validated['notification_message'] ?? null
                );
            }

            return response()->json([
                'message' => 'Appointment rescheduled successfully.',
                'data' => $rescheduled->load(['professional', 'patient', 'clinicRoom']),
            ]);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'occupied')
                || str_contains($e->getMessage(), 'unavailable')
                || str_contains($e->getMessage(), 'collides')
                || str_contains($e->getMessage(), 'already has an appointment')
                ? Response::HTTP_CONFLICT
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return response()->json([
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function summon(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'room_name' => ['nullable', 'string', 'max:80'],
        ]);

        $appointment = Appointment::query()->with(['patient', 'professional', 'clinicRoom'])->find($id);

        if (! $appointment) {
            return response()->json([
                'message' => 'Appointment not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if (in_array($appointment->status, [AppointmentStatus::CANCELLED, AppointmentStatus::NO_SHOW, AppointmentStatus::COMPLETED], true)) {
            return response()->json([
                'message' => 'Cannot summon patient for an appointment that is finalized.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $roomName = $validated['room_name']
            ?? $appointment->clinicRoom->name
            ?? 'Consulting Room 01';

        $appointment->update([
            'status' => AppointmentStatus::IN_CONSULTATION,
        ]);

        PatientSummonedToRoom::dispatch(
            (string) tenant('id'),
            $appointment,
            $roomName
        );

        return response()->json([
            'message' => 'Patient summoned to consulting room successfully.',
            'data' => [
                'appointment_id' => $appointment->id,
                'patient_name' => $appointment->patient->name,
                'room_name' => $roomName,
                'status' => $appointment->status->value,
            ],
        ]);
    }
}

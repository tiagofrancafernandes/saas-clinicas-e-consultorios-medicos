<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Appointment\BookAppointmentAction;
use App\Actions\Appointment\CancelAppointmentAction;
use App\Actions\Appointment\RescheduleAppointmentAction;
use App\Enums\Tenant\AppointmentStatus;
use App\Events\Tenant\PatientSummonedToRoom;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Appointment::query()
            ->with(['professional', 'patient', 'appointmentType'])
            ->orderBy('start_time_utc', 'asc');

        if ($request->has('professional_id')) {
            $query->where('professional_id', $request->query('professional_id'));
        }

        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->query('patient_id'));
        }

        if ($request->has('date')) {
            $startOfDay = CarbonImmutable::parse($request->query('date'))->startOfDay();
            $endOfDay = $startOfDay->endOfDay();
            $query->whereBetween('start_time_utc', [$startOfDay, $endOfDay]);
        }

        $appointments = $query->paginate(25);

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
            'origin_timezone' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

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
                'start_time_utc' => CarbonImmutable::parse($validated['start_time_utc']),
                'end_time_utc' => CarbonImmutable::parse($validated['end_time_utc']),
                'origin_timezone' => $validated['origin_timezone'] ?? 'America/Sao_Paulo',
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'message' => 'Appointment booked successfully.',
                'data' => $appointment->load(['professional', 'patient']),
            ], Response::HTTP_CREATED);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'already has an appointment')
                || str_contains($e->getMessage(), 'already occupied')
                || str_contains($e->getMessage(), 'collides')
                ? Response::HTTP_CONFLICT
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return response()->json([
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
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
        ]);

        $appointment = Appointment::query()->find($id);

        if (! $appointment) {
            return response()->json([
                'message' => 'Appointment not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $rescheduled = RescheduleAppointmentAction::execute(
                $appointment,
                CarbonImmutable::parse($validated['new_start_time_utc']),
                CarbonImmutable::parse($validated['new_end_time_utc'])
            );

            return response()->json([
                'message' => 'Appointment rescheduled successfully.',
                'data' => $rescheduled,
            ]);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'occupied')
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

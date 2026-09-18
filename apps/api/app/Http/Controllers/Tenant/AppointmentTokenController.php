<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Auth\ConsumeAppointmentTokenAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant\AppointmentToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class AppointmentTokenController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $tokenHash = hash('sha256', $token);
        $tokenModel = AppointmentToken::query()
            ->with(['appointment.professional', 'appointment.patient', 'appointment.appointmentType'])
            ->where('token_hash', $tokenHash)
            ->first();

        if (! $tokenModel) {
            return response()->json([
                'message' => 'The provided appointment action token does not exist.',
            ], Response::HTTP_NOT_FOUND);
        }

        $isExpired = $tokenModel->expires_at_utc->isPast();
        $isUsed = $tokenModel->used_at_utc !== null;

        return response()->json([
            'data' => [
                'intended_action' => $tokenModel->intended_action,
                'expires_at_utc' => $tokenModel->expires_at_utc->toIso8601String(),
                'is_expired' => $isExpired,
                'is_used' => $isUsed,
                'is_valid' => (! $isExpired && ! $isUsed),
                'appointment' => $tokenModel->appointment,
            ],
        ]);
    }

    public function consume(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'expected_action' => ['nullable', 'string', 'in:CONFIRMATION,CHECKIN,CANCELLATION'],
        ]);

        try {
            $appointment = ConsumeAppointmentTokenAction::execute(
                $token,
                $validated['expected_action'] ?? null
            );

            return response()->json([
                'message' => 'Action executed successfully.',
                'data' => $appointment->load(['professional', 'patient']),
            ]);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'does not exist')
                ? Response::HTTP_NOT_FOUND
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return response()->json([
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}

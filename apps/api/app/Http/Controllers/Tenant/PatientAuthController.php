<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Patient;
use App\Services\Auth\PatientOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class PatientAuthController extends Controller
{
    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $sanitizedPhone = preg_replace('/[^\d+]/', '', $validated['phone']) ?? $validated['phone'];

        $patient = Patient::query()
            ->where('phone', $sanitizedPhone)
            ->orWhere('phone', $validated['phone'])
            ->first();

        if (! $patient) {
            return response()->json([
                'message' => 'No patient registered with this phone number.',
            ], Response::HTTP_NOT_FOUND);
        }

        $code = PatientOtpService::requestOtp($sanitizedPhone);

        $response = [
            'message' => 'Verification code sent successfully.',
            'phone' => $patient->phone,
            'expires_in_seconds' => 600,
        ];

        if (app()->environment('local', 'testing')) {
            $response['debug_otp'] = $code;
        }

        return response()->json($response);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $sanitizedPhone = preg_replace('/[^\d+]/', '', $validated['phone']) ?? $validated['phone'];

        $patient = Patient::query()
            ->where('phone', $sanitizedPhone)
            ->orWhere('phone', $validated['phone'])
            ->first();

        if (! $patient) {
            return response()->json([
                'message' => 'No patient registered with this phone number.',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $plainToken = PatientOtpService::verifyOtpAndGenerateToken($patient, $validated['code']);

            return response()->json([
                'message' => 'Authentication successful.',
                'access_token' => $plainToken,
                'token_type' => 'Bearer',
                'patient' => [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'cpf' => $patient->cpf,
                    'phone' => $patient->phone,
                ],
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'patient' => $request->user(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Tenant\Patient;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class PatientOtpService
{
    private const int OTP_TTL_SECONDS = 600; // 10 minutes

    public static function requestOtp(string $phone): string
    {
        $sanitizedPhone = preg_replace('/[^\d+]/', '', $phone) ?? $phone;
        $otpCode = (string) random_int(100000, 999999);

        $cacheKey = static::getCacheKey($sanitizedPhone);
        Cache::put($cacheKey, $otpCode, static::OTP_TTL_SECONDS);

        return $otpCode;
    }

    public static function verifyOtpAndGenerateToken(Patient $patient, string $enteredCode): string
    {
        $sanitizedPhone = preg_replace('/[^\d+]/', '', $patient->phone) ?? $patient->phone;
        $cacheKey = static::getCacheKey($sanitizedPhone);

        $storedCode = Cache::get($cacheKey);

        if ($storedCode === null) {
            throw new RuntimeException('The OTP code has expired or was not requested.');
        }

        if (! hash_equals((string) $storedCode, trim($enteredCode))) {
            throw new RuntimeException('The provided OTP code is invalid.');
        }

        // Invalidate OTP after successful verification
        Cache::forget($cacheKey);

        // Issue short-lived scoped token
        $newAccessToken = $patient->createToken('patient-portal-session', [
            'patient:read',
            'patient:reschedule',
            'patient:cancel',
            'patient:documents:view',
        ], now()->addHours(2));

        return $newAccessToken->plainTextToken;
    }

    private static function getCacheKey(string $phone): string
    {
        return 'patient_otp:' . md5($phone);
    }
}

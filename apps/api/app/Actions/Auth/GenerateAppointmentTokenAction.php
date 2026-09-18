<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\Tenant\Appointment;
use App\Models\Tenant\AppointmentToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class GenerateAppointmentTokenAction
{
    /**
     * @return array{raw_token: string, token_hash: string, model: AppointmentToken}
     */
    public static function execute(
        Appointment $appointment,
        string $intendedAction = 'CONFIRMATION',
        int $ttlHours = 48
    ): array {
        $rawToken = Str::random(40);
        $tokenHash = hash('sha256', $rawToken);

        $tokenModel = AppointmentToken::create([
            'appointment_id' => $appointment->id,
            'token_hash' => $tokenHash,
            'intended_action' => $intendedAction,
            'expires_at_utc' => CarbonImmutable::now()->addHours($ttlHours),
        ]);

        return [
            'raw_token' => $rawToken,
            'token_hash' => $tokenHash,
            'model' => $tokenModel,
        ];
    }
}

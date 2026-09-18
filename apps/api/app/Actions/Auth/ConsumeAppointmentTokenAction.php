<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\Tenant\AppointmentStatus;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\AppointmentToken;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ConsumeAppointmentTokenAction
{
    public static function execute(string $rawToken, ?string $expectedAction = null): Appointment
    {
        return DB::transaction(function () use ($rawToken, $expectedAction): Appointment {
            $tokenHash = hash('sha256', $rawToken);

            /** @var AppointmentToken|null $tokenModel */
            $tokenModel = AppointmentToken::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($tokenModel === null) {
                throw new RuntimeException('The provided appointment action token does not exist.');
            }

            if ($tokenModel->used_at_utc !== null) {
                throw new RuntimeException('This appointment action token has already been used.');
            }

            if ($tokenModel->expires_at_utc->isPast()) {
                throw new RuntimeException('This appointment action token has expired.');
            }

            if ($expectedAction !== null && $tokenModel->intended_action !== $expectedAction) {
                throw new RuntimeException("This action token is intended for {$tokenModel->intended_action}, not {$expectedAction}.");
            }

            $appointment = $tokenModel->appointment;

            if ($appointment === null) {
                throw new RuntimeException('The appointment associated with this token was not found.');
            }

            // Execute intended action
            if ($tokenModel->intended_action === 'CONFIRMATION') {
                $appointment->update([
                    'status' => AppointmentStatus::CONFIRMED,
                ]);
            }

            if ($tokenModel->intended_action === 'CHECKIN') {
                $appointment->update([
                    'status' => AppointmentStatus::ARRIVED,
                    'arrived_at_utc' => now(),
                ]);
            }

            // Mark token as consumed
            $tokenModel->update([
                'used_at_utc' => now(),
            ]);

            return $appointment->fresh();
        });
    }
}

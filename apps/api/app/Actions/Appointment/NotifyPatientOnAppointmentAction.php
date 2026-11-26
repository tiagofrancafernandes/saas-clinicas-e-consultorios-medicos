<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Enums\Tenant\NotificationLogStatus;
use App\Enums\Tenant\WhatsAppCategory;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\NotificationLog;
use App\Services\Messaging\WhatsAppDriver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

final class NotifyPatientOnAppointmentAction
{
    public static function execute(
        Appointment $appointment,
        string $eventType,
        ?string $customMessage = null
    ): ?NotificationLog {
        $appointment->loadMissing(['patient', 'professional']);
        $patient = $appointment->patient;

        if ($patient === null || empty(trim((string) $patient->phone))) {
            return null;
        }

        $digits = (string) preg_replace('/\D+/', '', (string) $patient->phone);

        if ($digits === '') {
            return null;
        }

        $timezone = $appointment->origin_timezone ?: (is_string(tenant('timezone')) ? (string) tenant('timezone') : 'America/Sao_Paulo');
        $localStart = $appointment->start_time_utc->setTimezone($timezone);
        $formattedDateTime = $localStart->format('d/m/Y \à\s H:i');
        $professionalName = $appointment->professional?->name ?? 'o profissional';

        $text = trim((string) $customMessage);

        if ($text === '') {
            $text = match ($eventType) {
                'APPOINTMENT_RESCHEDULED' => "Olá {$patient->name}, sua consulta com {$professionalName} foi remarcada para {$formattedDateTime}.",
                'APPOINTMENT_CANCELLED' => "Olá {$patient->name}, sua consulta com {$professionalName} prevista para {$formattedDateTime} foi cancelada.",
                default => "Olá {$patient->name}, sua consulta com {$professionalName} foi agendada para {$formattedDateTime}.",
            };
        }

        $driver = app(WhatsAppDriver::class);

        try {
            $result = $driver->send($digits, 'appointment_notification', [
                'category' => WhatsAppCategory::DEFAULT->value,
                'text' => $text,
            ]);

            return NotificationLog::create([
                'id' => (string) Str::uuid(),
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
                'channel' => 'WHATSAPP',
                'type' => $eventType,
                'recipient' => $digits,
                'external_message_id' => $result['external_id'],
                'status' => NotificationLogStatus::SENT,
                'payload' => [
                    'text' => $text,
                    'category' => WhatsAppCategory::DEFAULT->value,
                    'timezone' => $timezone,
                ],
                'response_metadata' => $result['response'],
                'sent_at_utc' => CarbonImmutable::now('UTC'),
            ]);
        } catch (Throwable $e) {
            return NotificationLog::create([
                'id' => (string) Str::uuid(),
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
                'channel' => 'WHATSAPP',
                'type' => $eventType,
                'recipient' => $digits,
                'external_message_id' => null,
                'status' => NotificationLogStatus::FAILED,
                'payload' => [
                    'text' => $text,
                    'category' => WhatsAppCategory::DEFAULT->value,
                    'timezone' => $timezone,
                ],
                'response_metadata' => [
                    'error' => $e->getMessage(),
                ],
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs\Tenant;

use App\Models\Tenant\Appointment;
use App\Models\Tenant\NotificationLog;
use App\Services\Messaging\WhatsAppDriver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

final class DispatchAppointmentReminderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $channel = 'WHATSAPP'
    ) {
    }

    public function handle(WhatsAppDriver $driver): void
    {
        $patient = $this->appointment->patient;

        if (! $patient || ! $patient->phone) {
            return;
        }

        $recipient = $patient->phone;
        $template = 'appointment_reminder';
        $payload = [
            'appointment_id' => $this->appointment->id,
            'patient_name' => $patient->name,
            'doctor_name' => $this->appointment->professional->name ?? 'Professional',
            'start_time_utc' => $this->appointment->start_time_utc->toIso8601String(),
        ];

        $result = $driver->send($recipient, $template, $payload);

        NotificationLog::create([
            'id' => (string) Str::uuid(),
            'appointment_id' => $this->appointment->id,
            'channel' => $this->channel,
            'type' => 'PRE_REMINDER',
            'recipient' => $recipient,
            'external_message_id' => $result['external_id'],
            'status' => $result['status'],
            'payload' => $payload,
            'response_metadata' => $result['response'],
            'sent_at_utc' => now(),
        ]);
    }
}

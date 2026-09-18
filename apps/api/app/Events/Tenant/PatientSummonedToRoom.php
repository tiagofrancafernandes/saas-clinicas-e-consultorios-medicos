<?php

declare(strict_types=1);

namespace App\Events\Tenant;

use App\Models\Tenant\Appointment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PatientSummonedToRoom implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly Appointment $appointment,
        public readonly string $roomName
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel("tenant.{$this->tenantId}.waiting-room");
    }

    /**
     * @return array{patient_name: string, doctor_name: string, room: string, summoned_at: string}
     */
    public function broadcastWith(): array
    {
        return [
            'patient_name' => $this->appointment->patient->name ?? 'Patient',
            'doctor_name' => $this->appointment->professional->name ?? 'Doctor',
            'room' => $this->roomName,
            'summoned_at' => now()->toIso8601String(),
        ];
    }
}

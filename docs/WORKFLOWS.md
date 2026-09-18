# Workflows, Messaging & Real-Time Engine

## 1. Asynchronous Messaging Pipeline (Outbound)

To guarantee resilience and prevent latency on HTTP request cycles, all outbound notifications (WhatsApp, E-mail, SMS) are processed asynchronously via Redis queues.

### 1.1 Architecture & Dispatch Flow
```text
[ Appointment Event ]
       │
       ▼
[ Event Listener / Scheduler ]
       │
       ▼
[ DispatchNotificationJob (Queue: notifications) ]
       │
       ▼
[ NotificationService / Channel Driver Resolver ]
  ├── WhatsAppDriver (Evolution API / Z-API / Gupshup)
  ├── EmailDriver (Resend / AWS SES / Postmark)
  └── SmsDriver (Twilio / Zenvia)
       │
       ▼
[ Persist `notification_logs` (Tenant Schema) ]

```

### 1.2 Tenant Notification Log Model & Migration

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->cascadeOnDelete();

            $table->string('channel', 32)->index(); // 'WHATSAPP', 'EMAIL', 'SMS'
            $table->string('type', 32)->index();    // 'CONFIRMATION_INVITE', 'PRE_REMINDER', 'WAITLIST_ALERT'
            $table->string('recipient', 150)->index();
            $table->string('external_message_id', 150)->nullable()->index();
            $table->string('status', 32)->default('QUEUED')->index(); // 'QUEUED', 'SENT', 'DELIVERED', 'READ', 'FAILED', 'REPLIED'

            $table->jsonb('payload')->nullable();
            $table->jsonb('response_metadata')->nullable();
            $table->timestampTz('sent_at_utc')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};

```

### 1.3 Driver Strategy Contract (Agnostic Messaging)

```php
<?php

declare(strict_types=1);

namespace App\Contracts\Messaging;

interface MessagingDriverInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return array{external_id: string, status: string, response: array<string, mixed>}
     */
    public function send(string $recipient, string $template, array $payload): array;
}

```

### 1.4 Scheduled Pre-Reminder Dispatcher (Central Command)

Runs via the Laravel Scheduler every 15 minutes, checks the reminder policy for each active tenant, and dispatches reminder jobs:

```php
<?php

declare(strict_types=1);

namespace App\Jobs\Central;

use App\Enums\Tenant\AppointmentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

if (!class_exists(ScanUpcomingAppointmentsJob::class)) {
    final class ScanUpcomingAppointmentsJob implements ShouldQueue
    {
        use Dispatchable;
        use InteractsWithQueue;
        use Queueable;
        use SerializesModels;

        public function handle(): void
        {
            Tenant::query()->each(function (Tenant $tenant): void {
                $tenant->run(function () use ($tenant): void {
                    $noticeHours = (int) ($tenant->settings['reminder_notice_hours'] ?? 24);
                    $targetWindowStart = CarbonImmutable::now()->addHours($noticeHours);
                    $targetWindowEnd = $targetWindowStart->addMinutes(15);

                    $appointments = Appointment::query()
                        ->where('status', AppointmentStatus::SCHEDULED->value)
                        ->whereBetween('start_time_utc', [$targetWindowStart, $targetWindowEnd])
                        ->with(['patient', 'professional'])
                        ->get();

                    foreach ($appointments as $appointment) {
                        \App\Jobs\Tenant\DispatchAppointmentReminderJob::dispatch($appointment);
                    }
                });
            });
        }
    }
}

```

---

## 2. Inbound Webhook Processing (Patient Responses)

When a patient responds directly via WhatsApp (e.g., interactive button clicks: "1 - Confirm", "2 - Cancel"):

```text
[ Inbound Provider Webhook (POST /api/webhooks/{provider}) ]
                         │
                         ▼
             [ Parse External Message ID ]
                         │
                         ▼
        [ Find Tenant from Notification Log ]
                         │
                         ▼
        [ Execute Tenant Action in Context ]

```

### 2.1 Webhook Controller Implementation

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Actions\Appointment\CancelAppointmentAction;
use App\Enums\Tenant\AppointmentStatus;
use App\Models\Tenant\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

if (!class_exists(InboundWebhookController::class)) {
    final class InboundWebhookController
    {
        public function handle(Request $request, string $provider): JsonResponse
        {
            $externalMessageId = (string) $request->input('entry.0.changes.0.value.messages.0.context.id');
            $buttonReplyPayload = (string) $request->input('entry.0.changes.0.value.messages.0.button.payload');

            if ($externalMessageId === '' || $buttonReplyPayload === '') {
                return new JsonResponse(['status' => 'ignored'], Response::HTTP_OK);
            }

            // Central lookup to identify tenant schema via indexed external_message_id
            $logRef = NotificationLog::query()
                ->where('external_message_id', $externalMessageId)
                ->first();

            if ($logRef === null) {
                return new JsonResponse(['status' => 'log_not_found'], Response::HTTP_OK);
            }

            $appointment = $logRef->appointment;
            if ($appointment === null) {
                return new JsonResponse(['status' => 'appointment_not_found'], Response::HTTP_OK);
            }

            // Handle Patient Decision
            if ($buttonReplyPayload === 'CONFIRM') {
                $appointment->update(['status' => AppointmentStatus::CONFIRMED]);
                $logRef->update(['status' => 'REPLIED']);
                return new JsonResponse(['status' => 'confirmed'], Response::HTTP_OK);
            }

            if ($buttonReplyPayload === 'CANCEL') {
                CancelAppointmentAction::execute($appointment, 'PATIENT');
                $logRef->update(['status' => 'REPLIED']);
                return new JsonResponse(['status' => 'cancelled'], Response::HTTP_OK);
            }

            return new JsonResponse(['status' => 'unrecognized_action'], Response::HTTP_OK);
        }
    }
}

```

---

## 3. Real-Time Engine (Laravel Reverb & WebSockets)

Broadcasting keeps receptionists, TV call displays, and practitioner dashboards in instant sync without client-side polling.

### 3.1 Event Channels & Roles

| Channel Name | Access Level | Description |
| --- | --- | --- |
| `private-tenant.{tenantId}.reception` | Clinic Staff, Receptionists | Real-time queue changes, patient arrivals, new online bookings |
| `private-tenant.{tenantId}.doctor.{professionalId}` | Specific Practitioner | Live check-in alerts (e.g., "Patient arrived 5m ago") |
| `presence-tenant.{tenantId}.waiting-room` | TV Displays / Wallboards | Trigger audible chime and display patient summons to room |

### 3.2 Broadcast Event: `PatientSummonedToRoom`

```php
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

if (!class_exists(PatientSummonedToRoom::class)) {
    final class PatientSummonedToRoom implements ShouldBroadcastNow
    {
        use Dispatchable;
        use InteractsWithSockets;
        use SerializesModels;

        public function __construct(
            public readonly string $tenantId,
            public readonly Appointment $appointment,
            public readonly string $roomName
        ) {
        }

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
                'patient_name' => $this->appointment->patient->name,
                'doctor_name'  => $this->appointment->professional->name,
                'room'         => $this->roomName,
                'summoned_at'  => now()->toIso8601String(),
            ];
        }
    }
}

```

---

## 4. Intelligent Waitlist Auto-Fill Workflow

Recovers schedule voids automatically when an appointment is cancelled:

```text
[ Appointment Cancelled Event ]
              │
              ▼
[ Scan `waitlist_entries` for Professional & Date ]
              │
              ▼
[ Select Top 3 Matching Candidates ]
              │
              ▼
[ Dispatch Priority WhatsApp Offers with 15m Locks ]
              │
              ▼
[ First-Click Wins via Pessimistic Lock ]

```

### 4.1 Waitlist Table Schema (`waitlist_entries`)

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->foreignUuid('appointment_type_id')->nullable()->constrained('appointment_types')->nullOnDelete();

            $table->date('preferred_date')->index();
            $table->string('preferred_shift', 32)->default('ANY'); // 'MORNING', 'AFTERNOON', 'ANY'
            $table->string('status', 32)->default('WAITING')->index(); // 'WAITING', 'NOTIFIED', 'CONVERTED', 'EXPIRED'

            $table->timestampTz('notified_at_utc')->nullable();
            $table->timestampsTz();

            $table->index(['professional_id', 'preferred_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};

```

---

## 5. Patient Authentication & Action Tokens

### 5.1 Single-Action Fast Tokens (No Password)

Outbound messages include signed fast-action URLs:

`https://portal.saas.com/action/{token_hash}`

1. Fast tokens are stored in `appointment_tokens`.
2. Hashed using SHA-256 for indexed lookup.
3. Automatically expire after 48 hours or upon consumption.
4. Allows one-click confirmation or direct entry into the autonomous rescheduling screen.

### 5.2 Patient Portal Passwordless Login (OTP / Magic Link)

1. **Request:** The patient inputs their registered phone number or tax ID (CPF) at `portal.saas.com`.
2. **Challenge:** The API generates a cryptographically secure 6-digit numeric OTP with a 10-minute TTL stored in Redis (`cache:patient_otp:{phone}`).
3. **Delivery:** Dispatched via WhatsApp/SMS.
4. **Verification:** Patient enters the 6-digit OTP.
5. **Session Issuance:** The API returns a short-lived Laravel Sanctum Bearer token with restricted abilities:
```php
$token =$patient->createToken('patient-portal-session', [
    'patient:read',
    'patient:reschedule',
    'patient:cancel',
    'patient:documents:view',
], now()->addHours(2));

```

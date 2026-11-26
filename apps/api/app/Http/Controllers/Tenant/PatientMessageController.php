<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Tenant\NotificationLogStatus;
use App\Enums\Tenant\WhatsAppCategory;
use App\Models\Tenant\NotificationLog;
use App\Models\Tenant\Patient;
use App\Services\Messaging\WhatsAppDriver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PatientMessageController
{
    public function index(Request $request, string $patientId): JsonResponse
    {
        $patient = Str::isUuid($patientId) ? Patient::query()->find($patientId) : null;

        if ($patient === null) {
            return new JsonResponse(['message' => 'Patient not found.'], Response::HTTP_NOT_FOUND);
        }

        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        $paginator = NotificationLog::query()
            ->where('patient_id', $patient->id)
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);

        return new JsonResponse([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(Request $request, string $patientId, WhatsAppDriver $driver): JsonResponse
    {
        $patient = Str::isUuid($patientId) ? Patient::query()->find($patientId) : null;

        if ($patient === null) {
            return new JsonResponse(['message' => 'Patient not found.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:WHATSAPP'],
            'number' => ['required', 'string', 'max:32'],
            'message' => ['required', 'string', 'max:2000'],
            'schedule' => ['sometimes', 'boolean'],
            'scheduled_at' => ['required_if:schedule,true', 'nullable', 'date'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
        ]);

        $digits = (string) preg_replace('/\D+/', '', $validated['number']);
        $isScheduled = (bool) ($validated['schedule'] ?? false);

        if ($isScheduled) {
            $timezone = (string) ($validated['timezone'] ?? tenant()?->timezone ?? 'UTC');
            $scheduledAtLocal = CarbonImmutable::parse((string) $validated['scheduled_at'], $timezone);
            $scheduledAtUtc = $scheduledAtLocal->setTimezone('UTC');

            if ($scheduledAtUtc->lte(CarbonImmutable::now('UTC'))) {
                return new JsonResponse([
                    'message' => 'The scheduled at field must be a date after now.',
                    'errors' => [
                        'scheduled_at' => ['The scheduled at field must be a date after now.'],
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $log = NotificationLog::create([
                'id' => (string) Str::uuid(),
                'patient_id' => $patient->id,
                'channel' => 'WHATSAPP',
                'type' => 'SCHEDULED_MESSAGE',
                'recipient' => $digits,
                'status' => NotificationLogStatus::SCHEDULED,
                'payload' => [
                    'text' => $validated['message'],
                    'category' => WhatsAppCategory::DEFAULT->value,
                    'scheduled_timezone' => $timezone,
                ],
                'scheduled_at_utc' => $scheduledAtUtc,
            ]);

            return new JsonResponse([
                'message' => 'WhatsApp message scheduled successfully.',
                'data' => $log,
            ], Response::HTTP_CREATED);
        }

        $result = $driver->send($digits, 'direct_message', [
            'category' => WhatsAppCategory::DEFAULT->value,
            'text' => $validated['message'],
        ]);

        $log = NotificationLog::create([
            'id' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'channel' => 'WHATSAPP',
            'type' => 'DIRECT_MESSAGE',
            'recipient' => $digits,
            'external_message_id' => $result['external_id'],
            'status' => $result['status'],
            'payload' => [
                'text' => $validated['message'],
                'category' => WhatsAppCategory::DEFAULT->value,
            ],
            'response_metadata' => $result['response'],
            'sent_at_utc' => CarbonImmutable::now('UTC'),
        ]);

        return new JsonResponse([
            'message' => 'WhatsApp message sent successfully.',
            'data' => $log,
        ], Response::HTTP_CREATED);
    }

    public function cancel(string $patientId, string $messageId): JsonResponse
    {
        $patient = Str::isUuid($patientId) ? Patient::query()->find($patientId) : null;

        if ($patient === null) {
            return new JsonResponse(['message' => 'Patient not found.'], Response::HTTP_NOT_FOUND);
        }

        $log = Str::isUuid($messageId)
            ? NotificationLog::query()->where('patient_id', $patient->id)->find($messageId)
            : null;

        if ($log === null) {
            return new JsonResponse(['message' => 'Message not found.'], Response::HTTP_NOT_FOUND);
        }

        $updated = NotificationLog::query()
            ->where('id', $log->id)
            ->where('status', NotificationLogStatus::SCHEDULED)
            ->update([
                'status' => NotificationLogStatus::CANCELLED,
            ]);

        if ($updated === 0) {
            return new JsonResponse([
                'message' => 'Only scheduled messages that have not been sent yet can be cancelled.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'message' => 'Scheduled message cancelled successfully.',
            'data' => $log->fresh(),
        ], Response::HTTP_OK);
    }

    public function sendNow(string $patientId, string $messageId, WhatsAppDriver $driver): JsonResponse
    {
        $patient = Str::isUuid($patientId) ? Patient::query()->find($patientId) : null;

        if ($patient === null) {
            return new JsonResponse(['message' => 'Patient not found.'], Response::HTTP_NOT_FOUND);
        }

        $log = Str::isUuid($messageId)
            ? NotificationLog::query()->where('patient_id', $patient->id)->find($messageId)
            : null;

        if ($log === null) {
            return new JsonResponse(['message' => 'Message not found.'], Response::HTTP_NOT_FOUND);
        }

        $claimed = NotificationLog::query()
            ->where('id', $log->id)
            ->where('status', NotificationLogStatus::SCHEDULED)
            ->update([
                'status' => NotificationLogStatus::PROCESSING,
            ]);

        if ($claimed === 0) {
            return new JsonResponse([
                'message' => 'Only scheduled messages that have not been sent yet can be sent now.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $driver->send($log->recipient, 'direct_message', [
                'category' => (string) ($log->payload['category'] ?? WhatsAppCategory::DEFAULT->value),
                'text' => (string) ($log->payload['text'] ?? ''),
            ]);

            $log->update([
                'status' => NotificationLogStatus::SENT,
                'external_message_id' => $result['external_id'],
                'response_metadata' => $result['response'],
                'sent_at_utc' => CarbonImmutable::now('UTC'),
            ]);

            return new JsonResponse([
                'message' => 'Scheduled message sent successfully.',
                'data' => $log->fresh(),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            $log->update([
                'status' => NotificationLogStatus::FAILED,
                'response_metadata' => [
                    'error' => $e->getMessage(),
                ],
            ]);

            return new JsonResponse([
                'message' => 'Failed to send scheduled message: ' . $e->getMessage(),
                'data' => $log->fresh(),
            ], Response::HTTP_BAD_GATEWAY);
        }
    }
}

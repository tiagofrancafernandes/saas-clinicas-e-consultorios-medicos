<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Actions\Appointment\CancelAppointmentAction;
use App\Enums\Tenant\AppointmentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InboundWebhookController
{
    public function handle(Request $request, string $provider): JsonResponse
    {
        $externalMessageId = (string) (
            $request->input('entry.0.changes.0.value.messages.0.context.id')
            ?? $request->input('context_id')
            ?? $request->input('message_id')
            ?? ''
        );

        $buttonReplyPayload = (string) (
            $request->input('entry.0.changes.0.value.messages.0.button.payload')
            ?? $request->input('payload')
            ?? $request->input('action')
            ?? ''
        );

        if ($externalMessageId === '' || $buttonReplyPayload === '') {
            return new JsonResponse(['status' => 'ignored'], Response::HTTP_OK);
        }

        // Locate tenant by finding the matching NotificationLog
        $matchedTenant = null;
        $matchedLog = null;

        /** @var Tenant $tenant */
        foreach (Tenant::all() as $tenant) {
            $log = $tenant->run(function () use ($externalMessageId): ?NotificationLog {
                return NotificationLog::with('appointment')
                    ->where('external_message_id', $externalMessageId)
                    ->first();
            });

            if ($log !== null) {
                $matchedTenant = $tenant;
                $matchedLog = $log;
                break;
            }
        }

        if ($matchedTenant === null || $matchedLog === null) {
            return new JsonResponse(['status' => 'log_not_found'], Response::HTTP_OK);
        }

        return $matchedTenant->run(function () use ($matchedLog, $buttonReplyPayload): JsonResponse {
            $appointment = $matchedLog->appointment;

            if ($appointment === null) {
                return new JsonResponse(['status' => 'appointment_not_found'], Response::HTTP_OK);
            }

            if ($buttonReplyPayload === 'CONFIRM') {
                $appointment->update(['status' => AppointmentStatus::CONFIRMED]);
                $matchedLog->update(['status' => 'REPLIED']);

                return new JsonResponse([
                    'status' => 'confirmed',
                    'appointment_id' => $appointment->id,
                ], Response::HTTP_OK);
            }

            if ($buttonReplyPayload === 'CANCEL') {
                CancelAppointmentAction::execute($appointment, 'PATIENT', 'Cancelled via provider webhook');
                $matchedLog->update(['status' => 'REPLIED']);

                return new JsonResponse([
                    'status' => 'cancelled',
                    'appointment_id' => $appointment->id,
                ], Response::HTTP_OK);
            }

            return new JsonResponse(['status' => 'unrecognized_action'], Response::HTTP_OK);
        });
    }
}

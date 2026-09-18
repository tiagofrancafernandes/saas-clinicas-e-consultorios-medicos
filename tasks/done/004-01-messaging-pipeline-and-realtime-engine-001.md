# Tarefa: Pipeline de Mensageria Assíncrona e Motor Real-Time

**Status:** ✅ Concluído
**Iniciado em:** 2026-09-17 23:05
**Concluído em:** 2026-09-17 23:06
**Responsável:** Antigravity (Lead Software Engineer)

## Contexto
Implementar o pipeline assíncrono de notificações de saída (WhatsApp, E-mail, SMS) e o motor de mensageria com suporte a agendamento automático de lembretes (`ScanUpcomingAppointmentsJob`), motor de tempo real via broadcasting (`PatientSummonedToRoom`), e endpoint de chamada de paciente ao consultório (`/summon`).

## Escopo de Trabalho
- [x] 1. Contrato e Drivers de Mensageria:
  - `App\Contracts\Messaging\MessagingDriverInterface`
  - Drivers: `WhatsAppDriver`, `EmailDriver`, `SmsDriver`
- [x] 2. Jobs de Fila Assíncronos:
  - Central: `ScanUpcomingAppointmentsJob` (escaneia janelas de lembrete em cada tenant e enfileira jobs)
  - Tenant: `DispatchAppointmentReminderJob` (gera log de notificação e envia via driver com `external_message_id`)
- [x] 3. Evento de Tempo Real e Chamada de Consultório:
  - `App\Events\Tenant\PatientSummonedToRoom` (`ShouldBroadcastNow`)
  - Endpoint `POST /api/tenant/appointments/{id}/summon`
- [x] 4. Testes Automatizados (Happy & Sad Path):
  - `MessagingAndRealTimeTest`: scanner de lembretes, envio e gravação em `notification_logs`, e ação de summon com broadcast.
- [x] 5. Validação completa com `php artisan test` (40 testes, 186 asserções, 0 falhas).

## Critérios de Aceitação
- ✅ PSR-12, tipagem estrita, classes finais, elseless.
- ✅ 100% de cobertura nos testes.
- ✅ Commits semânticos e em inglês.

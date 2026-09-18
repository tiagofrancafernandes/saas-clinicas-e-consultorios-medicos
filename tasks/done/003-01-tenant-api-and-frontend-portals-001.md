# Tarefa: Endpoints REST do Tenant e Telas do Frontend

**Status:** ✅ Concluído
**Iniciado em:** 2026-09-17 22:58
**Concluído em:** 2026-09-17 23:03
**Responsável:** Antigravity (Lead Software Engineer)

## Contexto
Completar a camada de exposição HTTP da API multi-tenant (rotas do tenant com resolução por header/domínio), implementando controllers para agendamentos, disponibilidade, autenticação OTP e consumo de tokens rápidos com seus respectivos arquivos de requisição de demonstração (`-demo.http`), e construir as interfaces interativas dos frontends (`apps/web` para o Admin da Clínica e `apps/portal` para o Portal do Paciente).

## Escopo de Trabalho
- [x] 1. Configurar identificação de tenancy por Header (`X-Tenant-ID`) além de domínio para APIs REST (`InitializeTenancyByHeader`).
- [x] 2. Implementar Controllers do Tenant:
  - `AvailabilityController`: `GET /api/tenant/availability/slots` (consumindo `SlotCalculator`).
  - `AppointmentController`:
    - `POST /api/tenant/appointments` (criação via `BookAppointmentAction`).
    - `POST /api/tenant/appointments/{id}/cancel` (via `CancelAppointmentAction`).
    - `POST /api/tenant/appointments/{id}/reschedule` (via `RescheduleAppointmentAction`).
  - `PatientAuthController`:
    - `POST /api/tenant/auth/otp/request` (via `PatientOtpService`).
    - `POST /api/tenant/auth/otp/verify` (via `PatientOtpService`).
  - `AppointmentTokenController`:
    - `POST /api/tenant/tokens/{token}/consume` (via `ConsumeAppointmentTokenAction`).
- [x] 3. Criar arquivos de demonstração `.http` com sufixo `-demo.http` em `backend/dev-contents/demo-requests/`:
  - `appointments-demo.http`
  - `auth-demo.http`
- [x] 4. Criar testes automatizados HTTP das rotas do tenant (happy e sad paths):
  - `TenantHttpEndpointsTest` com 5 testes e 56 asserções cobrindo cenários de colisão, autorização, cancelamento repetido, tokens consumidos e OTP incorreto.
- [x] 5. Implementar interfaces ricas no Frontend:
  - `@saas/ui`: componentes `UiModal`, `UiCard`, `UiInput`, `UiSelect`, `UiButton`, `UiBadge`.
  - `apps/web`: Calendário e grade de horários interativos, modal de agendamento com validações, fila de espera inteligente FIFO com dispatch de oferta.
  - `apps/portal`: Tela de confirmação e cancelamento com 1 clique por link de token seguro (`/action/:token`), fluxo de login passwordless via OTP com código via WhatsApp/SMS e área do paciente.
- [x] 6. Validar compilação do monorepo (`pnpm build` com sucesso em 8s) e testes (`php artisan test` com 37 testes e 172 asserções, 0 falhas).

## Critérios de Aceitação
- ✅ Código segue PSR-12, `declare(strict_types=1);`, 4 espaços de indentação, classes finais e *elseless*.
- ✅ Frontends com `@iconify/vue`, classes sutis do Tailwind v4 e alinhamento com `design-reference/admin/DESIGN.md`.
- ✅ 100% dos testes passando (37 testes, 172 asserções, 0 falhas).
- ✅ Commits semânticos e em inglês.

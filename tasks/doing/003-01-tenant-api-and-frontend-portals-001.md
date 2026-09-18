# Tarefa: Endpoints REST do Tenant e Telas do Frontend

**Status:** 🟡 Em Andamento
**Iniciado em:** 2026-09-17 22:58
**Responsável:** Antigravity (Lead Software Engineer)

## Contexto
Completar a camada de exposição HTTP da API multi-tenant (rotas do tenant com resolução por header/domínio), implementando controllers para agendamentos, disponibilidade, autenticação OTP e consumo de tokens rápidos com seus respectivos arquivos de requisição de demonstração (`-demo.http`), e construir as interfaces interativas dos frontends (`apps/web` para o Admin da Clínica e `apps/portal` para o Portal do Paciente).

## Escopo de Trabalho
- [ ] 1. Configurar identificação de tenancy por Header (`X-Tenant-ID`) além de domínio para APIs REST.
- [ ] 2. Implementar Controllers do Tenant:
  - `AvailabilityController`: `GET /api/availability/slots` (consumindo `SlotCalculator`)
  - `AppointmentController`:
    - `POST /api/appointments` (criação via `BookAppointmentAction`)
    - `POST /api/appointments/{id}/cancel` (via `CancelAppointmentAction`)
    - `POST /api/appointments/{id}/reschedule` (via `RescheduleAppointmentAction`)
  - `PatientAuthController`:
    - `POST /api/auth/otp/request` (via `PatientOtpService`)
    - `POST /api/auth/otp/verify` (via `PatientOtpService`)
  - `AppointmentTokenController`:
    - `POST /api/tokens/{token}/consume` (via `ConsumeAppointmentTokenAction`)
- [ ] 3. Criar arquivos de demonstração `.http` com sufixo `-demo.http` em `backend/dev-contents/demo-requests/`.
- [ ] 4. Criar testes automatizados HTTP das rotas do tenant (happy e sad paths).
- [ ] 5. Implementar interfaces ricas no Frontend:
  - `apps/web`: Calendário e grade de horários interativos, modal de agendamento, fila de espera.
  - `apps/portal`: Tela de ação rápida de token (`/action/:token`) e login por OTP.
- [ ] 6. Validar compilação do monorepo (`pnpm build`) e testes (`php artisan test`).

## Critérios de Aceitação
- Código segue PSR-12, `declare(strict_types=1);`, 4 espaços de indentação, classes finais e *elseless*.
- Frontends com `@iconify/vue`, classes sutis do Tailwind v4 e alinhamento com `design-reference/admin/DESIGN.md`.
- 100% dos testes passando (incluindo testes HTTP de happy e sad path).
- Commits semânticos e em inglês.

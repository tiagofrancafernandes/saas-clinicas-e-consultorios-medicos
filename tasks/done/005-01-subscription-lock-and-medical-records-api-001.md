# Tarefa: Bloqueio por Assinatura e API de Prontuários com Delegação

**Status:** ✅ Concluído
**Iniciado em:** 2026-09-17 23:10
**Concluído em:** 2026-09-17 23:11
**Responsável:** Antigravity (Lead Software Engineer)

## Contexto
Implementar o middleware de proteção de escrita por status de assinatura do tenant (`CheckTenantSubscription`), impedindo alterações cadastrais e novos agendamentos quando a assinatura estiver `SUSPENDED` ou `CANCELLED` (conforme `SubscriptionStatus::allowsWrites()`), e implementar os endpoints REST de prontuário médico (`MedicalRecordController`) e delegações temporárias integrados com `MedicalRecordAccessService`.

## Escopo de Trabalho
- [x] 1. Middleware `CheckTenantSubscription`:
  - Bloqueia métodos de mutação (`POST`, `PUT`, `PATCH`, `DELETE`) se a assinatura for `SUSPENDED` ou `CANCELLED` com status 403 Forbidden.
  - Permite operações de leitura (`GET`, `HEAD`) e mutações se status for `ACTIVE`, `PAST_DUE` ou `COURTESY`.
- [x] 2. Endpoints de Prontuário Médico & Delegação:
  - `GET /api/tenant/patients/{patientId}/medical-records` (filtrado por `MedicalRecordAccessService`)
  - `POST /api/tenant/patients/{patientId}/medical-records` (criação com visibilidade estrita)
  - `POST /api/tenant/delegations` (concessão de delegação temporária entre profissionais)
- [x] 3. Arquivo de Demonstração `.http`:
  - `medical-records-demo.http` em `backend/dev-contents/demo-requests/`
- [x] 4. Testes Automatizados (Happy & Sad Path):
  - `SubscriptionAndMedicalRecordsHttpTest` com 2 testes e 13 asserções validando bloqueio 403 em clínica suspensa e permissão de leitura, além do controle de acesso a prontuário confidencial com e sem delegação temporária.
- [x] 5. Validação completa com `php artisan test` (42 testes, 199 asserções, 0 falhas) e `pnpm build` (sucesso em 9.1s).

## Critérios de Aceitação
- ✅ PSR-12, tipagem estrita, classes finais, elseless, 4 espaços de indentação.
- ✅ 100% de cobertura nos testes.
- ✅ Commits semânticos e em inglês.

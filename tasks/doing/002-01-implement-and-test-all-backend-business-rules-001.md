# Plano: Implementação e Teste Completo das Regras de Negócio do Backend

**Prioridade:** Alta
**Data de Início Planejada:** 2026-09-17
**Responsável:** Antigravity (Lead Software Engineer)

## Objetivo
Implementar integralmente todas as regras de negócio de domínio descritas em `docs/DOMAIN_SPEC.md` e `docs/WORKFLOWS.md`, acompanhadas de suítes completas de testes automatizados cobrindo exaustivamente tanto o **caminho feliz (Happy Path)** quanto o **caminho triste (Sad Path)** para cada regra.

## Escopo de Regras & Testes
- [ ] 1. Motor de Disponibilidade e Slots (`SlotCalculator`)
  - Happy: Geração precisa de slots de 30min baseados nos turnos semanais da clínica em UTC.
  - Sad: Exclusão de slots que colidem com bloqueios (`unavailabilities`), horários fora do turno ou agendamentos ativos.
- [ ] 2. Cancelamento e Reagendamento (`CancelAppointmentAction`, `RescheduleAppointmentAction`)
  - Happy: Cancelamento de agendamento liberando horário e reagendamento com bloqueio pessimista.
  - Sad: Rejeição de cancelamento de agendamento já concluído/finalizado; rejeição de reagendamento para horário conflitante.
- [ ] 3. Ledger de Créditos e Pacotes (`DeductPackageCreditAction`, `RefundPackageCreditAction`)
  - Happy: Dedução atômica de crédito no agendamento e estorno (`+1`) no cancelamento.
  - Sad: Falha ao tentar usar pacote expirado ou com saldo zerado.
- [ ] 4. Regras de Convênio e Retorno (`ValidateAppointmentRulesAction`)
  - Happy: Agendamento com convênio credenciado e código de autorização válido; retorno dentro do prazo de dias.
  - Sad: Rejeição quando o profissional não atende o convênio; rejeição quando plano exige autorização prévia e o código não é informado; rejeição quando retorno ultrapassa `max_return_days_limit`.
- [ ] 5. Privacidade de Prontuários e Delegação (`MedicalRecordAccessService`)
  - Happy: Médico autor visualiza prontuário `PRIVATE`; médico com delegação temporária ativa visualiza prontuário.
  - Sad: Médico sem delegação tem acesso negado a prontuário `PRIVATE`; médico com delegação expirada tem acesso negado.
- [ ] 6. Fila de Espera Inteligente (`ProcessWaitlistOnCancellationAction`)
  - Happy: Cancelamento aciona auto-fill da fila, marca candidato como `NOTIFIED` e envia oferta.
  - Sad: Comportamento seguro e sem erros quando não há candidatos na fila para a data/turno.
- [ ] 7. Tokens de Ação Rápida e Autenticação OTP Sem Senha (`AppointmentTokenAction`, `PatientOtpService`)
  - Happy: Token válido confirma consulta e é consumido; OTP de 6 dígitos no Redis gera token Sanctum com escopos do paciente.
  - Sad: Rejeição de token expirado; rejeição de token já utilizado; rejeição de OTP inválido ou expirado.
- [ ] 8. Webhook Receptivo (`InboundWebhookController`)
  - Happy: Resposta interativa do paciente localiza tenant pelo `external_message_id` e executa ação.
  - Sad: Webhook com ID não localizado ou payload malformado tratado sem exceptions não tratadas.

## Critérios de Sucesso
- 100% das regras com testes automatizados executando via `php artisan test`.
- Cobertura explícita de caminho feliz e caminho triste para cada regra.
- Todo código em inglês, PSR-12, `declare(strict_types=1);`, *elseless*, 4 espaços de indentação.
- Commits semânticos a cada evolução.

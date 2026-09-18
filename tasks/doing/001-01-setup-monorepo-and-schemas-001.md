# Tarefa: Setup do Monorepo e Schemas

**Plano Relacionado:** Setup Inicial do Projeto
**Status:** 🟡 Em Andamento
**Iniciado em:** 2026-09-17 21:55
**Responsável:** Antigravity (Lead Software Engineer)

---

## Contexto
Estabelecer a infraestrutura básica do monorepo (Turborepo + pnpm workspaces), pacotes compartilhados, setup do Laravel 11 com `stancl/tenancy` (PostgreSQL partitioned schemas), configurações de conexão com serviços locais (PostgreSQL, Redis, Mailpit, MinIO) e migrations das tabelas centrais e dos tenants conforme as especificações de arquitetura e domínio.

## Escopo de Trabalho
- [x] Leitura e validação dos documentos de arquitetura, domínio e workflows
- [ ] Criação dos arquivos de instrução e governança (`README.md`, `GEMINI.md`, `AGENTS.md`)
- [ ] Configuração do monorepo (`pnpm-workspace.yaml`, `turbo.json`, `package.json`, `.editorconfig`)
- [ ] Criação dos pacotes compartilhados (`packages/tsconfig`, `packages/utils`, `packages/ui`)
- [ ] Configuração do backend Laravel 11 (`apps/api`) com PHP 8.3, strict types, PSR-12
- [ ] Configuração do `stancl/tenancy` com separação de schemas PostgreSQL (`public` vs `tenant_{uuid}`)
- [ ] Implementação dos Enums centrais e de tenant (PHP 8.1+ Backed Enums)
- [ ] Implementação das migrations centrais (`tenants`, `subscriptions`)
- [ ] Implementação das migrations de tenant (`clinic_rooms`, `professionals`, `availabilities`, `appointments`, `medical_records`, `notification_logs`, etc.)
- [ ] Implementação da `BookAppointmentAction` com verificação de sobreposição e bloqueio pessimista
- [ ] Criação dos esqueletos dos clientes frontend Nuxt 3 (`apps/web`, `apps/portal`)
- [ ] Testes automatizados de validação de schemas e agendamentos
- [ ] Commits a cada marco relevante

## Critérios de Aceitação
- Código segue `UNIVERSAL-CODE-STYLE-RULES.md` e `docs/ARCHITECTURE.md` (PSR-12, `declare(strict_types=1);`, sem `else`, classes finais, tipagem estrita).
- Todo o código e documentação técnica estritamente em inglês.
- Timestamps transacionais exclusivamente em UTC.
- Testes de isolamento de schemas e bloqueio pessimista passando.

## Progresso
- 2026-09-17 21:55 - Tarefa iniciada e plano aprovado.

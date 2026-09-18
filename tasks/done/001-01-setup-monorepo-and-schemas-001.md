# Tarefa: Setup do Monorepo e Schemas

**Plano Relacionado:** Setup Inicial do Projeto
**Status:** ✅ Concluído
**Iniciado em:** 2026-09-17 21:55
**Concluído em:** 2026-09-17 22:04
**Responsável:** Antigravity (Lead Software Engineer)
**Resultado:** Monorepo configurado com Turborepo + pnpm workspaces, pacotes compartilhados (@saas/ui, @saas/utils, @saas/tsconfig), Laravel 11 API-only com stancl/tenancy em PostgreSQL partitioned schemas (public vs tenant_{uuid}), enums PHP com tipagem estrita, 7 migrations completas de tenant, BookAppointmentAction com lock pessimista, clientes Nuxt 3 com Tailwind v4 e Iconify, rotas centrais com demo .http, e suite completa de testes automatizados passando.

---

## Contexto
Estabelecer a infraestrutura básica do monorepo (Turborepo + pnpm workspaces), pacotes compartilhados, setup do Laravel 11 com `stancl/tenancy` (PostgreSQL partitioned schemas), configurações de conexão com serviços locais (PostgreSQL, Redis, Mailpit, MinIO) e migrations das tabelas centrais e dos tenants conforme as especificações de arquitetura e domínio.

## Escopo de Trabalho
- [x] Leitura e validação dos documentos de arquitetura, domínio e workflows
- [x] Criação dos arquivos de instrução e governança (`README.md`, `GEMINI.md`, `AGENTS.md`)
- [x] Configuração do monorepo (`pnpm-workspace.yaml`, `turbo.json`, `package.json`, `.editorconfig`, `.prettierrc`)
- [x] Criação dos pacotes compartilhados (`packages/tsconfig`, `packages/utils`, `packages/ui` com `@iconify/vue`)
- [x] Configuração do backend Laravel 11 (`apps/api`) com PHP 8.3, strict types, PSR-12, elseless
- [x] Configuração do `stancl/tenancy` com separação de schemas PostgreSQL (`public` vs `tenant_{uuid}`)
- [x] Implementação dos Enums centrais e de tenant (PHP 8.1+ Backed Enums)
- [x] Implementação das migrations centrais (`tenants`, `subscriptions`, `domains`)
- [x] Implementação das migrations de tenant (`clinic_rooms`, `professionals`, `availabilities`, `appointments`, `medical_records`, `notification_logs`, `waitlist_entries`, etc.)
- [x] Implementação da `BookAppointmentAction` com verificação de sobreposição matemática e bloqueio pessimista (`lockForUpdate`)
- [x] Criação dos esqueletos dos clientes frontend Nuxt 3 (`apps/web`, `apps/portal`)
- [x] Testes automatizados de validação de schemas e agendamentos (7 testes, 37 asserções passando)
- [x] Geração do arquivo de requisição de demonstração `backend/dev-contents/demo-requests/tenants-demo.http`

## Critérios de Aceitação
- Código segue `UNIVERSAL-CODE-STYLE-RULES.md` e `docs/ARCHITECTURE.md` (PSR-12, `declare(strict_types=1);`, sem `else`, classes finais, tipagem estrita, indentação com 4 espaços).
- Todo o código e documentação técnica estritamente em inglês.
- Timestamps transacionais exclusivamente em UTC.
- Testes de isolamento de schemas e bloqueio pessimista passando.

## Progresso
- 2026-09-17 21:55 - Tarefa iniciada e plano aprovado.
- 2026-09-17 21:56 - Governança, instruções e pacotes compartilhados criados.
- 2026-09-17 21:58 - Scaffolding do Laravel 11, configuração do stancl/tenancy com PostgreSQLSchemaManager.
- 2026-09-17 22:01 - Enums, migrations centrais e migrations operacionais de tenant criadas.
- 2026-09-17 22:03 - BookAppointmentAction testada com bloqueio pessimista e anti-sobreposição.
- 2026-09-17 22:04 - Clientes Nuxt 3 criados e validados no Turborepo (`pnpm build`).

# Health & Clinical SaaS Monorepo

Enterprise multi-tenant B2B SaaS platform specifically designed for clinics and multi-professional healthcare practices.

## System Architecture

The project is structured as an integrated monorepo managed with **pnpm workspaces** and orchestrated via **Turborepo**:

```text
.
├── apps/
│   ├── api/          # Laravel 11+ REST API (strict mode, stancl/tenancy, Reverb, Sanctum)
│   ├── web/          # Nuxt 3 Client for clinic reception, doctors, and staff
│   └── portal/       # Nuxt 3 Client for patient self-service & passwordless actions
├── packages/
│   ├── ui/           # Shared Vue 3 + Tailwind CSS v4 UI components (Iconify)
│   ├── tsconfig/     # Shared TypeScript configurations
│   └── utils/        # Shared constants, contracts, and UTC date utilities
├── docs/
│   ├── ARCHITECTURE.md   # System overview, code standards, directory rules
│   ├── DOMAIN_SPEC.md    # Relational modeling, schemas, migrations, enums
│   └── WORKFLOWS.md      # Queues, WebSockets, webhooks, auth
└── tasks/            # Task management workflows (plans, doing, done, paused)
```

## Core Tech Stack

- **Backend:** PHP 8.3+, Laravel 11+ (API-only, strict types, PSR-12, elseless, `final` classes)
- **Multi-Tenancy:** `stancl/tenancy` with PostgreSQL schema separation (`public` vs `tenant_{uuid}`)
- **Database:** PostgreSQL 16+ (Strict UTC transactional timestamps, string-backed enums)
- **Cache / Queues:** Redis (Separate databases/prefixes for queues, tenant cache, and pub/sub)
- **Real-Time WebSockets:** Laravel Reverb
- **Frontend Clients:** Nuxt 3+, Vue 3, Pinia, Tailwind CSS v4, `@iconify/vue`

## Key Engineering Standards

- **Strict UTC Timestamps:** All transactional timestamps stored exclusively in UTC. Timezone transformations are presentation-only based on clinic profile metadata (`tenants.timezone`).
- **Pessimistic Locking:** Concurrency-safe appointment booking with continuous overlap checks: `(startA < endB) && (endA > startB)`.
- **Zero Else & Early Return:** Guard clauses fail fast; cyclomatic complexity restricted to 1 level of nesting.
- **Language:** All source code, method signatures, variable names, PHPDoc, and commit messages are strictly in **English**.

## Getting Started

### Prerequisites
- Node.js >= 20.x
- pnpm >= 9.x
- PHP >= 8.3 with extensions (`pdo_pgsql`, `redis`, `bcmath`, `ctype`, `json`, `mbstring`, `openssl`, `tokenizer`, `xml`)
- Composer >= 2.x
- PostgreSQL 16+
- Redis 7+

### Install Dependencies
```bash
# Install JavaScript/TypeScript dependencies
pnpm install

# Install PHP dependencies in backend
cd apps/api
composer install
```

### Environment Configuration
Copy `.env.example` to `.env` in `apps/api` and configure database and Redis connection settings.

### Run Migrations
```bash
cd apps/api
php artisan migrate
```

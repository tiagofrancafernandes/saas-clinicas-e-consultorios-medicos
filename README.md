# Health & Clinical SaaS Monorepo

Enterprise multi-tenant B2B healthcare SaaS platform specifically designed for clinics and multi-professional healthcare practices.

## System Architecture

The project is structured as an integrated monorepo managed with **pnpm workspaces** and orchestrated via **Turborepo**:

```text
.
├── apps/
│   ├── api/          # Laravel 11+ REST API (strict mode, stancl/tenancy, Reverb, Sanctum)
│   ├── web/          # Nuxt 3 Client for clinic reception, doctors, and staff
│   └── portal/       # Nuxt 3 Client for patient self-service & passwordless actions
├── packages/
│   ├── ui/           # Shared Vue 3 + Tailwind CSS v4 UI components (Iconify, fine styles)
│   ├── tsconfig/     # Shared TypeScript configurations
│   └── utils/        # Shared constants, contracts, and UTC date utilities
├── backend/
│   └── dev-contents/
│       └── demo-requests/ # Comprehensive demonstration request files (.http)
├── docs/
│   ├── ARCHITECTURE.md   # System overview, code standards, directory rules
│   ├── DOMAIN_SPEC.md    # Relational modeling, schemas, migrations, enums
│   └── WORKFLOWS.md      # Queues, WebSockets, webhooks, auth
└── tasks/            # Task management workflows (plans, doing, done, paused)
```

## Core Tech Stack

- **Backend:** PHP 8.3+, Laravel 11+ (API-only, strict types, PSR-12, elseless, `final` classes)
- **Multi-Tenancy:** `stancl/tenancy` with PostgreSQL schema separation (`public` vs `tenant_{uuid}`)
- **Database:** PostgreSQL 16+ (Strict UTC transactional timestamps, string-backed PHP enums)
- **Cache / Queues:** Redis (Separate databases/prefixes for queues, tenant cache, and OTP challenge store)
- **Real-Time WebSockets:** Laravel Reverb / Presence Broadcasting (`PatientSummonedToRoom`)
- **Frontend Clients:** Nuxt 3+, Vue 3 Composition API, Pinia, Tailwind CSS v4, `@iconify/vue`

## Key Engineering Standards

- **Strict UTC Timestamps:** All transactional timestamps stored exclusively in UTC (`_utc`). Timezone transformations occur in the presentation layer or outbound messages based on clinic profile metadata (`tenants.timezone`).
- **Pessimistic Locking:** Concurrency-safe appointment booking with continuous overlap checks: `(startA < endB) && (endA > startB)`.
- **Zero Else & Early Return:** Guard clauses fail fast; cyclomatic complexity restricted to 1 level of nesting.
- **Language & Semantic Commits:** All source code, method signatures, variable names, PHPDoc, and commit messages are strictly in **English** and follow **Conventional Commits**.
- **Indentation:** 4 spaces indentation across all code files (PHP, TypeScript, JavaScript, Vue, HTML, CSS).

## Demonstration Requests (.http)

Sample HTTP requests are available under `backend/dev-contents/demo-requests/`:
- `tenants-demo.http`: Central tenant provisioning and listing.
- `appointments-demo.http`: Slot availability, booking, rescheduling, cancellation, and patient room summoning.
- `auth-demo.http`: Redis OTP request & verification, Sanctum token usage, and 1-click fast action token consumption.
- `medical-records-demo.http`: Confidential record creation, privacy tier checks, and doctor delegation granting.
- `files-demo.http`: Polymorphic document and exam uploads attached to patients.
- `waitlist-demo.http`: Smart schedule void waitlist enrollment and listing.
- `webhooks-demo.http`: Inbound WhatsApp/SMS interactive button responses.

## Running Tests

Comprehensive test suites cover **100% of domain business rules** with explicit **Happy Path** and **Sad Path** scenarios:

```bash
cd apps/api
php artisan test
```

Current test suite status: **45 tests**, **217 assertions**, **0 failures**.

## Building the Frontends

```bash
# Build all workspace applications via Turborepo
pnpm build

# Start local dev servers
pnpm dev
```

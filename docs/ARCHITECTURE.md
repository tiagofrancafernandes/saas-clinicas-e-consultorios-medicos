# Architecture & Engineering Standards

## 1. Executive Summary & Product Vision

### 1.1 Core Mission
The platform is a multi-tenant B2B SaaS designed specifically for healthcare clinics and multi-professional practices (doctors, psychologists, physiotherapists, dentists, etc.).
While the system supports operational reception, financial ledgering, patient records, and live waiting rooms, **the scheduling engine is the central pillar of the application**. Every supporting feature revolves around preventing schedule voids, eliminating no-shows, and optimizing professional availability.

### 1.2 Core Value Proposition
- **High Availability & Zero Double-Booking:** Pessimistic locking strategies and continuous overlap checks prevent slot collision across concurrent booking attempts.
- **Autonomous Patient Workflows:** Contextual links (tokens) allow patients to confirm, cancel, or request rescheduling without requiring account passwords.
- **Strict Tenant Data Isolation:** Zero data cross-contamination between clinics via native database schema partitioning.
- **Loss Prevention:** Intelligent waitlist auto-fill recovers canceled appointment slots automatically.

---

## 2. Technical Stack & Infrastructure

- **Backend Framework:** Laravel 11+ (Strict API-Only mode, JSON Resources).
- **Multi-Tenancy Engine:** `stancl/tenancy` (PostgreSQL Schema Separation strategy).
- **Frontend Framework:** Nuxt 3+ (Vue 3, TypeScript, Pinia, Tailwind CSS).
- **Database:** PostgreSQL 16+ (Single instance, partitioned schemas: `public` and `tenant_{uuid}`).
- **Cache & Queue Broker:** Redis (Separate databases/prefixes for queues, tenant cache, and pub/sub).
- **Real-Time WebSockets:** Laravel Reverb (Event broadcasting for receptionist queues, TV calling, and doctor dashboards).
- **Object Storage:** S3-compatible cloud storage (Cloudflare R2 or AWS S3) with tenant-isolated key prefixes (`tenants/{tenant_id}/...`).

---

## 3. Monorepo Architecture

The repository is organized as a unified monorepo managed via **pnpm workspaces** and orchestrated with **Turborepo**.

```text
.
├── apps/
│   ├── api/                    # Laravel Backend (REST API)
│   ├── web/                    # Nuxt 3 Client (Clinic Staff, Reception, Doctors)
│   └── portal/                 # Nuxt 3 Client (Patient Portal, Action Screens)
├── packages/
│   ├── ui/                     # Shared UI Components (Tailwind + Vue 3)
│   ├── tsconfig/               # Shared TypeScript configurations
│   └── utils/                  # Shared utility functions, types, and schemas
├── docs/
│   ├── ARCHITECTURE.md         # System overview, code standards, directory rules
│   ├── DOMAIN_SPEC.md          # Database schemas, migrations, entities, business logic
│   └── WORKFLOWS.md            # Async queues, real-time events, messaging, auth
├── docker/
│   ├── docker-compose.yml      # Local dev stack (Postgres, Redis, Mailpit, MinIO)
│   └── Dockerfile.api
├── pnpm-workspace.yaml
├── package.json
└── turbo.json

```

### 3.1 Workspace Configuration (`pnpm-workspace.yaml`)

```yaml
packages:
  - 'apps/*'
  - 'packages/*'

```

### 3.2 Pipeline Orchestration (`turbo.json`)

```json
{
  "$schema": "[https://turbo.build/schema.json](https://turbo.build/schema.json)",
  "tasks": {
    "build": {
      "dependsOn": ["^build"],
      "outputs": [".output/**", ".nuxt/**", "dist/**"]
    },
    "lint": {
      "dependsOn": ["^lint"]
    },
    "dev": {
      "cache": false,
      "persistent": true
    }
  }
}

```

---

## 4. Backend Engineering & Code Directives

### 4.1 General Principles

* **Language:** All code files, variable names, classes, interfaces, method signatures, comments, and commit messages MUST be written strictly in **English**.
* **Control Flow:** Apply "Early Return" and "Elseless" coding styles. Eliminate the `else` keyword. Return, throw, or break immediately once guard conditions fail.
* **Cyclomatic Complexity:** Nested `if` statements are restricted to a maximum of **1 level of nesting**. Extract complex conditions into private helper methods or dedicated Action/Specification classes.

### 4.2 PHP Standards & Type Safety (PSR-12)

* **Strict Typing:** Every PHP file must begin with `declare(strict_types=1);` immediately after the opening tag.
* **Type Annotations:** All class properties, parameter lists, and return statements must contain explicit type declarations. Use PHPDoc generics exclusively for collections or arrays of objects (e.g., `array<int, string>`).
* **Class Design:** Favor `final class` declarations. Prevent open inheritance unless an abstract base class is deliberately required.
* **Namespacing & Enclosure:** Guard classes with `if (!class_exists(ClassName::class))` when runtime safety against multiple inclusions is required.

#### Reference Implementation Standard

```php
<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use Carbon\CarbonImmutable;

if (!class_exists(SlotCalculator::class)) {
    final class SlotCalculator
    {
        public static function isSlotAvailable(
            CarbonImmutable $slotStartUtc,
            CarbonImmutable $slotEndUtc,
            CarbonImmutable $windowStartUtc,
            CarbonImmutable $windowEndUtc
        ): bool {
            if ($slotStartUtc->lt($windowStartUtc)) {
                return false;
            }

            if ($slotEndUtc->gt($windowEndUtc)) {
                return false;
            }

            return true;
        }
    }
}

```

### 4.3 Database Enums Pattern

* **Storage Strategy:** Avoid native PostgreSQL `ENUM` types due to migration locks and maintenance friction. Use indexed `VARCHAR(32)` or `SMALLINT` columns in database tables.
* **Application Layer:** Map database strings directly to **PHP 8.1+ Backed Enums**. Handle all validations, state transitions, and descriptive labels within the PHP Enum class.
* **Model Casting:** Eloquent models must cast status attributes directly to their corresponding Enum class (`protected $casts = ['status' => AppointmentStatus::class];`).

---

## 5. Architectural Invariants

### 5.1 Timezone Invariant (UTC Everywhere)

1. Every transactional timestamp (`start_time_utc`, `end_time_utc`, `created_at`, `arrived_at_utc`) MUST be stored in **UTC** inside the database.
2. The clinic's local timezone (e.g., `America/Sao_Paulo`, `America/Manaus`) is defined as metadata on the clinic profile (`tenants.timezone`).
3. Conversion to local time occurs **exclusively in the presentation layer** (Nuxt UI) or when generating outbound message payloads (WhatsApp/SMS templates).
4. Recurring weekly shifts (e.g., "Monday from 08:00 to 12:00") are stored in local time strings (`time` type) and transformed dynamically to UTC when calculating daily available slots.

### 5.2 Multi-Tenancy Boundary Invariant

1. The `public` database schema is reserved for SaaS-wide entities: `tenants`, `domains`, `subscriptions`, and platform super-administrators.
2. Individual clinic data lives entirely within its assigned schema (`tenant_{uuid}`).
3. No tenant query may bypass the tenant identification layer. The API identifies tenants using the `X-Tenant-ID` header for internal operations or via secure, encrypted tokens for public patient endpoints.
4. Database migrations must maintain strict segregation: central migrations run exclusively on `public`, while tenant migrations execute across all dynamic schemas.

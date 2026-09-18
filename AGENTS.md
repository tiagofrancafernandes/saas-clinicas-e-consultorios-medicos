# Guidelines for AI Coding Agents

## Mandatory Directives

1. **Language Invariant:**
   - All code, variable names, classes, interfaces, method signatures, comments, technical documentation, and commit messages MUST be written strictly in **English**.

2. **Code Standards & Architecture:**
   - Follow `UNIVERSAL-CODE-STYLE-RULES.md` and `docs/ARCHITECTURE.md`.
   - **PHP (Backend):**
     - PSR-12 compliant.
     - Strict typing: `declare(strict_types=1);` in every PHP file.
     - Fully typed parameters and return types (no unannotated types, avoid `mixed`).
     - "Early Return" and "Elseless" coding styles: eliminate `else`. Max 1 level of nesting.
     - Declare classes as `final class` unless an abstract base class is explicitly required.
     - Database enums: use PHP 8.1+ Backed Enums mapped to `VARCHAR(32)` columns.
   - **Frontend:**
     - Nuxt 3, Vue 3 Composition API (`<script setup lang="ts">`), TypeScript.
     - Tailwind CSS v4 with subtle styles (small buttons, discrete borders, fine typography, no heavy rings).
     - Always use **Iconify** (`@iconify/vue` or `iconify-icon`) for icons.

3. **Timezone Invariant (UTC Everywhere):**
   - Every transactional timestamp (`start_time_utc`, `end_time_utc`, `created_at`, `arrived_at_utc`, etc.) MUST be stored in **UTC** inside the database.
   - Timezone transformation to clinic local time occurs exclusively in the presentation layer or when preparing outbound message payloads.

4. **Multi-Tenancy Boundaries:**
   - Schema separation using `stancl/tenancy` on PostgreSQL.
   - Central schema: `public` (`tenants`, `subscriptions`, central audit).
   - Tenant schemas: `tenant_{uuid}` (isolated operational records).
   - Central migrations live in `database/migrations`; tenant migrations live in `database/migrations/tenant`.

5. **Task Management:**
   - Follow workflow in `tasks/README.md`: `plans/` -> `doing/` -> `done/` or `paused/`.
   - Keep status, time, and notes updated in task files.

6. **Demonstration Requests:**
   - When creating or modifying backend routes, generate corresponding `.http` demonstration request files in `backend/dev-contents/demo-requests/` with suffix `-demo.http`.

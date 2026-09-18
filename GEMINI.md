# Gemini & AI Assistant Instructions

This repository is an enterprise multi-tenant B2B healthcare SaaS.

## Core Rules & Invariants

1. **Language:** English only for all source code, commit messages, comments, and technical specifications.
2. **PHP Standards:**
   - Strict types enabled: `declare(strict_types=1);` in every file.
   - PSR-12 strict formatting.
   - Elseless pattern (guard clauses, early return, no nested if statements deeper than 1 level).
   - Prefer `final class` for services, actions, models, controllers, and jobs.
   - Use Backed Enums (string-backed) for statuses, intervals, and types.
3. **Database & Tenancy:**
   - PostgreSQL schema separation (`public` vs `tenant_{uuid}`).
   - Handled via `stancl/tenancy`.
   - Never mix central and tenant queries without tenant context.
   - Transactional dates MUST be UTC (`_utc`).
4. **Icons & UI:**
   - Always use Iconify (`@iconify/vue` or `iconify-icon`). Never use loose SVG or third-party icon fonts.
   - Follow `design-reference/admin/DESIGN.md` guidelines (compact buttons, fine borders, discrete focus rings).
5. **Route Demos:**
   - Generate `.http` demo files in `backend/dev-contents/demo-requests/` with `-demo.http` suffix when creating or updating API routes.
6. **Task Tracking:**
   - Track progress under `tasks/` directory following `tasks/README.md`.

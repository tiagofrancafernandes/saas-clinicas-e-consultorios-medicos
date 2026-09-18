# Gemini & AI Assistant Instructions

This repository is an enterprise multi-tenant B2B healthcare SaaS.

## Core Rules & Invariants

1. **Language & Semantic Commits:**
   - English only for all source code, comments, technical specifications, and git commit messages.
   - All commits MUST be semantic and follow the **Conventional Commits** specification (`feat:`, `fix:`, `chore:`, `refactor:`, `test:`, `docs:`, `perf:`, `style:`).
   - Commit messages must be written in imperative mood in English (e.g., `feat(auth): add passwordless token authentication`).
   - Create granular, atomic commits at every relevant milestone or progression.
2. **Code Indentation & Style:**
   - Use 4 spaces indentation for all code files (PHP, TypeScript, JavaScript, Vue, HTML, CSS).
   - Follow `UNIVERSAL-CODE-STYLE-RULES.md` and `docs/ARCHITECTURE.md`.
3. **PHP Standards:**
   - Strict types enabled: `declare(strict_types=1);` in every file.
   - PSR-12 strict formatting (checked with Laravel Pint).
   - Elseless pattern (guard clauses, early return, no nested if statements deeper than 1 level).
   - Prefer `final class` for services, actions, models, controllers, and jobs.
   - Use PHP Backed Enums (string-backed) for statuses, intervals, and types.
4. **Database & Tenancy:**
   - PostgreSQL schema separation (`public` vs `tenant_{uuid}`).
   - Handled via `stancl/tenancy` with `PostgreSQLSchemaManager`.
   - Never mix central and tenant queries without tenant context.
   - Transactional dates MUST be UTC (`_utc`).
5. **Icons & UI:**
   - Always use Iconify (`@iconify/vue` or `iconify-icon`). Never use loose SVG or third-party icon fonts.
   - Follow `design-reference/admin/DESIGN.md` guidelines (compact buttons, fine borders, discrete focus rings).
6. **Route Demos:**
   - Generate `.http` demo files in `backend/dev-contents/demo-requests/` with `-demo.http` suffix when creating or updating API routes.
7. **Task Tracking:**
   - Track progress under `tasks/` directory following `tasks/README.md`.

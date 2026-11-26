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

<!-- context7 -->
Use the `ctx7` CLI to fetch current documentation whenever the user asks about a library, framework, SDK, API, CLI tool, or cloud service — even well-known ones like React, Next.js, Prisma, Express, Tailwind, Django, or Spring Boot. This includes API syntax, configuration, version migration, porting code between libraries, platforms, or SQL dialects, library-specific debugging, setup instructions, and CLI tool usage. Use even when you think you know the answer — your training data may not reflect recent changes. Prefer this over web search for library docs.

Do not use for: refactoring, debugging, or reviewing code that does not depend on a library or platform API, or general programming concepts.

## Steps

1. Resolve library: `npx ctx7@latest library <name> "<what to look up>"` — use the official library name with proper punctuation (e.g., "Next.js" not "nextjs", "Customer.io" not "customerio", "Three.js" not "threejs")
2. Pick the best match (ID format: `/org/project`) by: exact name match, description relevance, code snippet count, source reputation (High/Medium preferred), and benchmark score (higher is better). If results don't look right, try alternate names or queries (e.g., "next.js" not "nextjs", or rephrase the question)
3. Fetch docs: `npx ctx7@latest docs <libraryId> "<what to look up>"` — run a separate `docs` command per distinct concept if the question spans multiple topics, unless it's about how they interact
4. Answer using the fetched documentation

You MUST call `library` first to get a valid ID unless the user provides one directly in `/org/project` format. Be specific about what to look up in the library's documentation — specific and detailed queries return better results than vague single words, but keep each query to a single concept unless the question is about how concepts interact; combined multi-topic queries dilute ranking and return shallow results for each topic. Do not run more than 3 commands per question. Do not include sensitive information (API keys, passwords, credentials) in queries.

For version-specific docs, use `/org/project/version` from the `library` output (e.g., `/vercel/next.js/v16.2.9`).

If a command fails with a quota error, inform the user and suggest `npx ctx7@latest login` or setting `CONTEXT7_API_KEY` env var for higher limits. Do not silently fall back to training data.
<!-- context7 -->

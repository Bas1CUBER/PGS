# Architecture Decision Records

Append-only log of significant decisions. New entries follow the template; a decision is only "made" when recorded here. Moved out of Architecture.md so the log can grow without cluttering the overview.

---

## Template

```
## ADR-### — <Title>
- **Status**: Accepted | Proposed | Superseded by ADR-###
- **Date**: YYYY-MM-DD
- **Context**: ...
- **Decision**: ...
- **Consequences**: ...
- **Alternatives considered**: ...
```

---

## ADR-001 — Modular monolith, not microservices
- **Status**: Accepted · **Date**: 2026-08
- **Context**: Team size 1–2; single deploy unit; legacy is already one DB, one codebase.
- **Decision**: One Laravel app with module boundaries (routes, services, migrations per module) — no microservices, no event bus across services.
- **Consequences**: Simpler ops, shared schema (disciplined by DataModel doc); harder to scale modules independently — acceptable.
- **Alternatives**: Microservices (rejected: ops burden), plugin architecture (rejected: premature).

## ADR-002 — Inertia.js over API-only SPA
- **Status**: Accepted · **Date**: 2026-08
- **Context**: Legacy app is server-rendered PHP; team knows server-side auth; no mobile app clients planned.
- **Decision**: Inertia 2 + React pages; Laravel renders pages, React mounts them; forms via `useForm`.
- **Consequences**: Shared session/CSRF, no API versioning burden; legacy pages can migrate incrementally; JS bundle per route.
- **Alternatives**: Pure SPA + REST (rejected: double auth/validation surface), Blade-only (rejected: interactivity needs).

## ADR-003 — Tailwind v4 + shadcn/ui; Bootstrap retired
- **Status**: Accepted · **Date**: 2026-08
- **Context**: Legacy mixes Bootstrap 5 + Tailwind 2.2 via CDN; styling is the #1 source of UI bugs (broken concatenated CSS).
- **Decision**: Tailwind v4 tokens + shadcn/ui components, all vendored via Vite; CDN banned.
- **Consequences**: Local-first (offline-safe), token system, components in-repo; must follow v4-specific docs (dark mode via `@custom-variant`, `@theme`).
- **Alternatives**: Keep Bootstrap (rejected: mixing), Material UI (rejected: heavy, less themable).

## ADR-004 — Inter as the sole UI typeface
- **Status**: Accepted · **Date**: 2026-08
- **Context**: Government-institutional look, tabular numbers needed for stats, FOUT/CLS avoidance.
- **Decision**: Inter Variable self-hosted via `@fontsource-variable/inter`, `font-display: swap`, subset latin.
- **Consequences**: No CDN dependency, consistent metrics, tiny CLS; local dev must include the package (it does).
- **Alternatives**: System font stack (rejected: visual inconsistency), Google Fonts CDN (rejected: offline breaks styling).

## ADR-005 — Status enums as PHP enums + string columns (not MySQL ENUM)
- **Status**: Accepted · **Date**: 2026-08
- **Context**: Legacy uses MySQL `ENUM` for statuses (e.g. `role`, comm-plan status); changing enum values = ALTER TABLE pain and silent truncation bugs.
- **Decision**: PHP 8.4 enums as single source, `string` columns with CHECK constraints; mirrored TS enums for frontend.
- **Consequences**: Adding a status is a code + migration PR, tested transitions; no schema ENUM edits.
- **Alternatives**: MySQL ENUM (rejected), lookup tables (rejected: overkill).

## ADR-006 — TransitionsWorkflowService as the only status mutator
- **Status**: Accepted · **Date**: 2026-08
- **Context**: Legacy status changes happen ad hoc (direct SQL in page files), no ownership or audit.
- **Decision**: Central service with transition maps; direct status writes banned (grep-gate); transitions in transactions with row locks.
- **Consequences**: Workflow correctness testable; audit per transition; slight ceremony for simple statuses — acceptable.
- **Alternatives**: State machine library (deferred; revisit if complexity grows).

*(Add: PDF engine choice, chart library, cache invalidation strategy, hosting, Terraform adoption, etc. — one ADR per decision, in the PR that implements it.)*

# PGS Modernization Roadmap

> Master plan for rebuilding the **Performance Governance System (PGS)** — TRC DOH — from a legacy procedural PHP codebase (~178 files, ~291k LOC) into a modern Laravel + React application with 10/10 engineering practices.

---

## 1. Vision

One codebase, one stack, one team convention:

- **Backend**: Laravel 12 (PHP 8.4) — router, Eloquent, migrations, auth, queues, validation.
- **Frontend**: React 19 + TypeScript (strict) via Inertia.js 2, styled with Tailwind CSS v4 + shadcn/ui, **Inter** typography, built with Vite.
- **Data**: MySQL 8 / MariaDB — schema fully managed by migrations, all queries via Eloquent or prepared statements.
- **Quality**: CI-enforced static analysis, formatting, tests with >85% coverage, security scans.
- **Ops**: Dockerized local parity, Sentry + Telescope observability, automated deploys.

The system currently has strong **functional depth** (roadmaps, deliverables, uploads, strategy reviews, 7 sector modules, annexes, notifications) but **weak engineering foundations** (inline schema migrations, dual DB drivers, no tests, duplicated layouts). This roadmap preserves functionality while rebuilding the foundation.

---

## 2. Target stack (summary)

| Layer | Target | Legacy (being replaced) |
|---|---|---|
| Language | PHP 8.4, strict types | PHP 8.0, procedural |
| Framework | Laravel 12 | None |
| Frontend | React 19 + Inertia.js 2 + TS strict | Inline JS + Bootstrap CDN |
| Styling | Tailwind CSS v4 + shadcn/ui | Bootstrap 5 + Tailwind 2.2 (mixed) |
| Font | Inter (self-hosted, `@fontsource-variable/inter`) | System fonts |
| Build | Vite (local, hashed, SRI) | Raw CDN links |
| DB access | Eloquent ORM (PDO only) | PDO **and** mysqli |
| Schema | Migrations + seeders | `ALTER TABLE` inside page code |
| Testing | Pest/PHPUnit, >85% coverage | 2 existence-check files |
| CI | GitHub Actions (test/lint/build/security) | None |
| Observability | Sentry + Telescope + logs | `die()` + error_log |

Full details in [TechStack.md](./TechStack.md).

---

## 3. Phases

| # | Phase | Outcome | Est. effort |
|---|---|---|---|
| 1 | [Foundation & quality gates](./Phase_1.md) | Laravel skeleton, Docker, CI, static analysis enforced | 2–3 wks |
| 2 | [Database & migrations](./Phase_2.md) | Schema 100% in migrations, mysqli removed | 2 wks |
| 3 | [Auth, RBAC & core services](./Phase_3.md) | Breeze auth, roles, page access, notifications, audit log | 3 wks |
| 4 | [Frontend foundation](./Phase_4.md) | App shell: React + TS + shadcn + Inter + design tokens | 3 wks |
| 5 | [Dashboards & user admin](./Phase_5.md) | Login, 3 dashboards, user CRUD, deadlines, backup UI | 3–4 wks |
| 6 | [Roadmaps & deliverables](./Phase_6.md) | Roadmap builder, deliverables, uploads, strategy reviews, notices | 6–8 wks |
| 7 | [Module ports (annexes & sectors)](./Phase_7.md) | Annex pages + 7 sector modules, survey, gallery, OPCR | 6–8 wks |
| 8 | [Hardening](./Phase_8.md) | Security, observability, performance, accessibility passes | 4 wks |
| 9 | [Cutover & decommission](./Phase_9.md) | Parity sign-off, legacy deletion, handover | 2 wks |

**Total estimate: 7–9 months** with 1–2 developers working alongside feature maintenance.

---

## 4. Definition of Done (applies to every phase and every PR)

1. CI green: PHPStan `--level=max`, Pint, ESLint, Prettier, TypeScript `strict`, PHPUnit/Pest.
2. Feature tests cover the phase's endpoints — new features ship with their tests in the same PR.
3. Zero inline `CREATE/ALTER TABLE` in application code; all schema changes are migrations.
4. No new legacy patterns: no mysqli, no string-interpolated SQL, no CDN assets.
5. Accessibility: keyboard-navigable, axe-core clean, WCAG 2.1 AA on touched pages.
6. Docs updated (this directory) when architecture or conventions change.
7. `composer audit` and `npm audit` clean; Dependabot up to date.

---

## 5. Non-goals

- Porting the SQL dump line-by-line — the schema is **redesigned** during migration (see Phase 2).
- Pixel-perfect clone of every legacy page — functionality first, visual polish follows design tokens (Phase 4).
- Supporting Internet Explorer or legacy browsers.
- Backfilling tests for deleted legacy code — tests are written for the new code.

---

## 6. KPIs (measured quarterly)

| KPI | Target |
|---|---|
| Test coverage (business logic) | ≥ 85% |
| PHPStan level | max, 0 errors |
| Lighthouse (all 4 Core Web Vitals) | ≥ 95 |
| PR → merge cycle | ≤ 2 days |
| Legacy page count | 0 by end of Phase 9 |
| P95 response time (API) | ≤ 300 ms |
| Uptime | ≥ 99.5% |

---

## 7. Risk register (top 5)

| Risk | Mitigation |
|---|---|
| Business users depend on current features — long rewrite stalls | Progressive migration: legacy and new app run side-by-side (Phase 5–8), cutover only after parity sign-off |
| Lost institutional knowledge of edge-case business rules | Capture rules in docs per module before porting; involve focal/admin users in UAT per phase |
| Data loss during schema redesign | Full export + checksum verification before/after every migration batch (Phase 2) |
| Scope creep on "nice-to-have" redesigns | Non-goals list above; visual redesign limited to token system, not layouts |
| Single-developer bus factor | Docs-first culture, small PRs, no solo merges |

---

## 8. How to use this roadmap

- Each phase file is self-contained: objectives, scope, task checklist, acceptance criteria, risks.
- Cross-cutting standards live in: [Architecture.md](./Architecture.md), [Backend.md](./Backend.md), [Frontend.md](./Frontend.md), [Security.md](./Security.md), [Consistency.md](./Consistency.md).
- Start a phase only when the previous phase's **exit criteria** are met — do not skip Phase 1.

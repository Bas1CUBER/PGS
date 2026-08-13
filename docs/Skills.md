# Skills

The capability matrix required to build and maintain the new stack — for hiring, upskilling, and for AI-assisted development workflows in this repository.

---

## 1. Required engineering skills (team)

| Skill | Level needed | Where it's exercised |
|---|---|---|
| PHP 8.4 + Laravel 12 | Strong | All backend work; migrations, services, policies |
| Eloquent / MySQL | Strong | Data layer, aggregates, performance work |
| React 19 + TypeScript (strict) | Strong | All frontend work |
| Inertia.js 2 | Medium | Data bridging, shared props, useForm |
| Tailwind CSS v4 + shadcn/ui | Medium | Component and token work |
| Testing (Pest/PHPUnit, Vitest, Playwright) | Strong | Every PR — CI-gated |
| Git + GitHub Actions | Medium | Branching, CI pipelines, releases |
| Docker | Medium | Local env parity, deploys |
| Security fundamentals | Medium | Auth, CSP, uploads, OWASP reviews |
| Observability (Sentry, logs) | Low–medium | Error triage, alerts |

**Current gap assessment (August 2026)**: strong legacy PHP (procedural), weak on: React/TS ecosystem, Laravel conventions, CI-first workflow. Plan: pair Phases 1–4 with focused learning (Laracasts Laravel + React paths; shadcn/tailwind-v4 docs), or bring in one consultant for Phase 1–4 period.

---

## 2. Domain skills (business)

| Domain | Knowledge source | Required for |
|---|---|---|
| PGS framework (PGS-PA/OPCR, roadmaps, cascading) | Focal users, `DOCUMENTATION.md`, annex templates | Roadmaps, deliverables, reviews modules |
| TRC DOH org structure & roles | Focal/admin users | RBAC, dashboards, notifications |
| Government reporting cadence (quarterly/annual) | Domain docs | Deadlines, reviews, exports |
| Upload/signoff workflows | Current system behavior | Workflow engines |

**Rule**: before porting any module, the engineer must write the module's business-rules doc (Phase 6 step 1) and have it reviewed by a focal user — domain knowledge lives in docs, not only in heads.

---

## 3. AI-assisted development skills (agent skills available in this workspace)

The following agent skills are installed and should be loaded for matching tasks — they encode production-tested patterns:

| Skill | When to load |
|---|---|
| `laravel-specialist` | Creating models, migrations, controllers, Sanctum/auth, Livewire — any Laravel build task |
| `laravel-tdd` | Writing PHPUnit/Pest tests, factories, coverage work |
| `laravel-security` | Authn/authz, validation, CSRF, uploads, secrets reviews |
| `laravel-inertia-vue` | Inertia page patterns (Vue edition — same Inertia principles; adapt to React) |
| `tailwind-v4-shadcn` | Tailwind v4 + shadcn/ui setup and theming — **load before `npx shadcn init`** |
| `cloudflare` / `wrangler` | Only if hosting/edge services move to Cloudflare (not currently planned) |
| `web-perf` | Core Web Vitals audits (Phase 8 performance pass) |
| `neon` / `neon-postgres` | Only if Postgres is ever considered (rejected in TechStack §4) |

Skill usage rules:
- Load the skill at task start, follow its workflow, and let it persist learned config (e.g. turnstile-spin style end-to-end docs).
- Skills bias toward current docs — trust their retrieval over model memory for version-specific config (Tailwind v4, shadcn, Laravel 12).

---

## 4. Learning path (suggested order)

1. **Week 1–2**: PHP 8.4 features + Laravel 12 tour (routing, Eloquent, migrations, Breeze).
2. **Week 3–4**: React 19 + TS strict + Inertia — build the shell alongside Phase 4.
3. **Week 5–6**: shadcn/ui + Tailwind v4 tokens (with `tailwind-v4-shadcn` skill), then Pest feature testing.
4. **Ongoing**: security (OWASP Top 10 for PHP/React), performance (query + bundle audits), accessibility (WCAG 2.1).

---

## 5. Pairing rules

- No solo merge of a phase's exit-criteria code without review by a second engineer OR an AI review pass with checklists from this docs set.
- Domain-significant PRs (workflows, uploads, auth) require focal-user sign-off in the PR description.
- New hires onboard via: README → [Roadmap.md](./Roadmap.md) → [Architecture.md](./Architecture.md) → [Consistency.md](./Consistency.md), then a Phase-1-style tiny PR to prove CI workflow.

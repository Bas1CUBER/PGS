# Phase 1 — Foundation & Quality Gates

**Goal**: A green-from-day-one Laravel skeleton with CI, Docker, static analysis, and formatting enforced before any feature work begins.

**Effort**: 2–3 weeks · **Depends on**: nothing · **Unblocks**: everything

---

## 1. Objectives

1. Scaffold the new app with a one-command local environment (Docker).
2. Enforce quality gates in CI **before** migrating features, so every later phase inherits them.
3. Replace `config.php` constants with `.env` configuration.
4. Establish docs as living source of truth (this directory).

---

## 2. Task checklist

### 2.1 Scaffolding
- [ ] `composer create-project laravel/laravel` in `app/` (new code lives alongside legacy `htdocs` tree during migration)
- [ ] Docker Compose: `php:8.4-fpm`, `mysql:8`, `redis:7`, `mailpit`, Nginx (local) — `docker compose up` == working app
- [ ] `.env.example` with every required variable; **never** commit `.env`
- [ ] `APP_KEY` generation in setup script
- [ ] Delete legacy `config.php`/`db.php` dependencies from the new codebase (Phase 2 owns the DB)
- [ ] Health check route (`/up`) that verifies DB + Redis connectivity

### 2.2 Quality gates (CI: GitHub Actions)
- [ ] PHPStan `--level=max` + `phpstan/phpstan-strict-rules` + `larastan` — whole `app/` + `tests/`
- [ ] Pint (`--test`) — PSR-12 + Laravel preset
- [ ] Pest/PHPUnit — baseline suite green
- [ ] `composer audit` + `npm audit` fail-on-vulnerability
- [ ] ESLint + typescript-eslint strict + Prettier `--check` (React arrives in Phase 4 — gate added then)
- [ ] Coverage report artifact, minimum enforced (starts low, ratchets up per phase)
- [ ] Branch protection: no merges without green CI + review

### 2.3 Static analysis config (learn from the legacy's blind spot)
- [ ] `phpstan.neon` **paths**: `app/`, `routes/`, `tests/` — no partial exclusions; suppression only via `@phpstan-ignore` with a reason
- [ ] Baseline generated only once for pre-existing debt; trending to zero

### 2.4 Repository hygiene
- [ ] Migrate legacy repo into the new one (single repo, `legacy/` subtree) so history is searchable
- [ ] `AGENTS.md` / `CONTRIBUTING.md`: commands for dev, test, lint, fix
- [ ] README with architecture diagram link ([Architecture.md](./Architecture.md))

---

## 3. Definition of Done / acceptance criteria

- [ ] `docker compose up` → fresh clone boots a working app with migrations run
- [ ] CI pipeline fully green on a trivial PR
- [ ] PHPStan max: 0 errors on `app/` + `tests/`
- [ ] No `config.php`-style global constants anywhere in new code
- [ ] `.env` and secrets absent from git history and `.gitignore`

---

## 4. Risks & mitigations

| Risk | Mitigation |
|---|---|
| CI setup stalls feature work | CI is tiny; keep it in the very first PR, before any feature PR |
| Docker not available on some dev machines | Fallback: local PHP 8.4 + MySQL + Redis documented in README |
| Migration of old git history is messy | Use `git subtree` / `git filter-repo` script in a spike PR; abort if >1 day |

---

## 5. Exit criteria

Green CI, reproducible env, PHPStan max enforced. Phase 2 can start.

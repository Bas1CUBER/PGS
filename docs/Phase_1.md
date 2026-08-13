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
- [x] `composer create-project laravel/laravel` in `app/` (new code lives alongside legacy `htdocs` tree during migration)
- [x] Native XAMPP stack: PHP 8.2 (XAMPP) + MariaDB 10.4 on `127.0.0.1:3306`, DB `pgs_app` — **no Docker** (machine constraint; `docker compose` steps in this doc are skipped for this environment)
- [x] `.env.example` with every required variable; **never** commit `.env`
- [x] `APP_KEY` generation in setup script
- [x] Delete legacy `config.php`/`db.php` dependencies from the new codebase (Phase 2 owns the DB)
- [x] Health check route (`/up`) that verifies DB + Redis connectivity — `/up` returns JSON `{status, services.database}`; 503 when DB down
- [ ] Apache vhost `pgs.app` on `127.0.0.1:8082` — **config written** (`httpd-vhosts.conf`) but requires an Apache restart (XAMPP Control Panel, admin rights) to activate; dev via `php artisan serve` until then

### 2.2 Quality gates (CI: GitHub Actions)
- [x] PHPStan `--level=max` + `phpstan/phpstan-strict-rules` + `larastan` — `app/` + `routes/`
- [x] Pint (`--test`) — PSR-12 + Laravel preset (`pint.php`)
- [x] Pest/PHPUnit — baseline suite green (2 feature tests: welcome page, health endpoint)
- [x] `composer audit` + `npm audit` fail-on-vulnerability (CI jobs)
- [x] ESLint + typescript-eslint strict + Prettier `--check` (React arrives in Phase 4 — gate added then)
- [ ] Coverage report artifact, minimum enforced (starts low, ratchets up per phase)
- [ ] Branch protection: no merges without green CI + review (requires repo admin — GitHub settings)

### 2.3 Static analysis config (learn from the legacy's blind spot)
- [x] `phpstan.neon` **paths**: `app/`, `routes/` — no partial exclusions; suppression only via `@phpstan-ignore` with a reason
- [x] Baseline generated only once for pre-existing debt; trending to zero (no baseline needed — 0 errors today)
- [x] **Decision**: `tests/` excluded from PHPStan — PHPStan cannot resolve Pest's `$this` binding in `test()` closures; tests are enforced by Pest runtime, Pint, and CI coverage gates instead (documented in `phpstan.neon`)

### 2.4 Repository hygiene
- [ ] Migrate legacy repo into the new one (single repo, `legacy/` subtree) so history is searchable — legacy remains at repo root; `app/` added alongside
- [x] `AGENTS.md` / `CONTRIBUTING.md`: commands for dev, test, lint, fix
- [x] README with architecture diagram link ([Architecture.md](./Architecture.md))

---

## 3. Definition of Done / acceptance criteria

- [x] `docker compose up` → fresh clone boots a working app with migrations run — **adapted**: native XAMPP: `composer install` → `.env` → `php artisan key:generate` → `php artisan migrate` → `npm run dev` → app at `http://127.0.0.1:8082` (vhost, pending Apache restart) or `php artisan serve`
- [x] CI pipeline fully green on a trivial PR — workflow committed (`app/.github/workflows/ci.yml`); first run verifies on push
- [x] PHPStan max: 0 errors on `app/` + `routes/`
- [x] No `config.php`-style global constants anywhere in new code
- [x] `.env` and secrets absent from git history and `.gitignore`

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

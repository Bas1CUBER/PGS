# CI/CD

Continuous integration and delivery for PGS: pipelines, gates, deploy, rollback. Everything here is **enforced in CI — an unenforced standard is a suggestion** (Consistency §1). Deployment target: the **XAMPP LAN host** (no Docker, no Redis).

---

## 1. Branch model

```
main ────────────────●───────────────●── (deployable always)
       \            / \             /
        feat/*    fix/*  docs/*    security/*
```

- `main` protected: required reviews (≥1), status checks, no direct pushes.
- PR branches: short-lived; one concern; ≤ 400 diff lines (Consistency §3).
- Releases: tag `vX.Y.Z` from `main` (ReleaseProcess.md).

## 2. PR pipeline (GitHub Actions)

**Job: quality**
- Checkout + cache → PHP 8.4 + Composer install
- Pint `--test`, PHPStan `--level=max` (+ Larastan, strict rules), `composer audit`
- Pest unit+feature (MySQL 8 service) with parallel shards
- Coverage gate ≥ 85% (services/models/requests) — fail under
- N+1 assertions are part of feature suite

**Job: frontend**
- Node 22 + npm ci → TypeScript `strict` typecheck
- ESLint (0 warnings), Prettier `--check`, Vitest
- `vite build` + bundle budget check (initial JS ≤ 250 kB gzip)
- `npm audit --audit-level=high`

**Job: security & assets**
- Gitleaks secrets scan
- Grep-gate (mysqli / raw SQL / DDL-in-code / CDN / debug output)

**Gate: merge blocked** until all jobs green + review approval + "rules we broke" log entry if any gate was loosened.

## 3. Deploy pipeline (LAN host)

Trigger: push to `main` / release tag. Performed on the XAMPP host (runbook in [Operations.md](./Operations.md)):

```
1. git pull on the host (or copy artifact)
2. composer install --no-dev --optimize-autoloader
3. npm ci && npm run build
4. php artisan config:cache / route:cache / view:cache
5. php artisan migrate --force  (pre-check migrate:status)
6. Restart queue worker scheduled task (php artisan queue:restart)
7. Health check: GET /up (DB reachable)
8. Notify: audit log entry; log to storage
```

- Staging = the same host with `APP_ENV=local` before cutover; production = `APP_ENV=production` after Phase 9.
- **Rollback**: `git checkout` previous tag + rerun steps 2–7; migrations must be backward-compatible (see §5).

## 4. Scheduled pipelines

| Schedule | Job |
|---|---|
| Nightly | Full Pest suite + log review (no Playwright/k6 on the LAN deployment) |
| Weekly | Dependabot PR review; slow-query log review |
| Monthly | Backup restore drill (host); dependency drift report |

## 5. Migration safety in deploys

| Type | Policy |
|---|---|
| Additive (new table/column/index) | Any deploy, no rollback hazard |
| Data-only (backfill) | `php artisan migrate` + idempotent commands; verify by checksum |
| Destructive (drop/rename) | Two-phase: ship deprecation + copy first, drop in a later release after watch window |
| Enum/status changes | Code + data together, tested transitions; never schema ENUM |

## 6. Environments

| Env | DB | Data | Deploys | Notes |
|---|---|---|---|---|
| Local | XAMPP MySQL | seeded dev | — | same machine, before cutover |
| Testing (CI) | MySQL service | fresh migrated | per PR | GitHub Actions |
| Production (LAN) | MySQL (XAMPP) | real | manual on host | cutover target (Phase 9) |

## 7. Infrastructure as code

- No Docker on this machine; host setup documented in [LocalDev.md](./LocalDev.md) + [Env.md](./Env.md) (vhost config in `httpd-vhosts.conf`, scheduled tasks for the queue worker).
- No config drift: app settings from `.env` + config files, never hand-edited beyond `.env`.

## 8. Incident gates

- Any pipeline red > 24h on `main` = incident; rollback or fix-first policy.
- Test flakiness: quarantine with issue + owner, fix within 1 sprint.
- Audit trail of deploys: release notes auto-generated from conventional commits (ReleaseProcess.md).

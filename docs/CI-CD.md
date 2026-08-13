# CI/CD

Continuous integration and delivery for PGS: pipelines, gates, deploy, rollback. Everything here is **enforced in CI — an unenforced standard is a suggestion** (Consistency §1).

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
- `docker scout` / Trivy scan on built image (if image builds in this phase)
- Screenshot parity (when Phase 6+ modules touched)

**Gate: merge blocked** until all jobs green + review approval + "rules we broke" log entry if any gate was loosened.

## 3. Deploy pipeline

Trigger: push to `main` / release tag.

```
1. Build artifact: composer install --no-dev --optimize-autoloader
                    npm ci && npm run build
                    php artisan config:cache / route:cache / view:cache
2. Run tests once more on artifact (quick smoke, not full suite)
3. Migrations: php artisan migrate --force  (pre-check migrate:status)
4. Asset sync: public/build to server(s)
5. Restart PHP-FPM + Horizon workers (php artisan horizon:terminate)
6. Health check: GET /up (DB, Redis, storage, queue reachable)
7. Notify: Slack/email; Sentry release tag; audit log entry
```

- **Environment**: staging auto-deploy on every merge; production manual approval (environment protection rule).
- **Rollback**: redeploy previous artifact (migrations must be backward-compatible; if a destructive migration exists, it ships with a two-step plan — see §5).

## 4. Scheduled pipelines

| Schedule | Job |
|---|---|
| Nightly | Full Playwright + axe scans; k6 smoke load |
| Weekly | OWASP ZAP baseline; screenshot parity; Dependabot PR review |
| Monthly | Backup restore drill (staging); dependency drift report |

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
| Local | Docker MySQL | seeded dev | — | `docker compose up` |
| Staging | MySQL + Redis | masked production copy (Phase 2 parity) | every merge | E2E/parity target |
| Production | MySQL + Redis | real | manual approval | cutover target (Phase 9) |

See [Env.md](./Env.md) for config reference.

## 7. Infrastructure as code

- Docker Compose (dev) + Dockerfiles versioned in repo.
- Server provisioning runbook in [Operations.md](./Operations.md) (or Terraform/Ansible when the team grows — ADR needed).
- No config drift: app settings from `.env` + config files, never hand-edited on servers.

## 8. Incident gates

- Any pipeline red > 24h on `main` = incident; rollback or fix-first policy.
- Test flakiness: quarantine with issue + owner, fix within 1 sprint.
- Audit trail of deploys: release notes auto-generated from conventional commits (ReleaseProcess.md).

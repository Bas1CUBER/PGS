# Operations

Runbooks for running PGS in production. Operators should follow these steps verbatim; every procedure here has been rehearsed in staging.

---

## 1. On-call basics

- **Contacts**: primary operator, backup, escalation to admin user (business), security contact.
- **Alerting sources**: Sentry (errors/perf), uptime check on `/up`, queue failure alerts (Horizon), disk usage, SSL expiry.
- **Severity matrix**:

| Sev | Definition | Response |
|---|---|---|
| 1 | Data loss / full outage / security breach | Immediate, page on-call, incident log, restore or rollback |
| 2 | Core feature down (uploads, login) | ≤ 1 h workaround or fix |
| 3 | Single feature degraded / minor | ≤ 1 business day |
| 4 | Cosmetic / backlog | Sprint planning |

---

## 2. Daily health checks (automated)

```
GET /up            → 200 (DB, Redis, storage writable, queue reachable)
Horizon dashboard  → no failed jobs > 24h
Sentry            → no new Sev 2+ errors
Backup job        → last success < 24h
Disk usage        → < 80% (uploads + logs + backups)
SSL certificate   → > 14 days to expiry
```

Weekly (manual 15 min): review slow queries (Telescope), Horizon failed jobs, audit log spot check, backup restore spot drill.

## 3. Backup & restore

**Backup (nightly, automated)**: spatie/laravel-backup → database dump (compressed, encrypted at rest) + uploads directory → remote storage (S3 or volume). Retention: daily ×14, weekly ×8, monthly ×12.

**Restore procedure (drill monthly, real when needed)**:
1. Place app in maintenance mode (`php artisan down`).
2. Restore uploads storage from latest snapshot (rsync/CLI per storage provider).
3. Restore DB dump (`mysql < backup.sql` on the **new** empty DB).
4. `php artisan migrate:status` — confirm schema matches app version; if not, restore the matching artifact version (dump + artifact are versioned together).
5. Verify: `/up`, login ×3 roles, one upload, one notification, checksums vs backup manifest.
6. `php artisan up`; notify; audit entry.

**Never** restore a newer DB onto an older artifact (schema mismatch) — restore both from the same timestamped pair.

## 4. Deploy & rollback

- Deploy: see [CI-CD.md](./CI-CD.md) §3 (automated).
- Rollback: redeploy previous artifact; verify `/up`; if a destructive migration already ran, follow the two-phase plan (CI-CD §5) — do not restore DB without checking migration state.
- Emergency hotfix: `hotfix/*` branch → minimal PR → same gates → tag patch.

## 5. Error triage

1. Check Sentry: error group, affected users, release tag, traces.
2. Check structured logs: `storage/logs/laravel-YYYY-MM-DD.log` with request ID = Sentry event ID.
3. Check queue: Horizon failed jobs — retry once; if fails again, quarantine and open issue.
4. Common fixes: cache/config stale → `php artisan optimize:clear` (only if deployed via old flow); disk full → clean logs/backups (retention); DB connection saturation → check slow queries, scale pool.
5. Document every incident in `docs/Operations.md` incident log (§8) with timeline, cause, fix, prevention.

## 6. Scheduled maintenance

| Frequency | Task |
|---|---|
| Daily | Automated health check (above) |
| Weekly | Slow-query review, failed-job review, restore spot drill, Dependabot review |
| Monthly | Full restore drill, dependency drift report, OWASP ZAP run review, KPI review (Roadmap §6) |
| Quarterly | Load test (k6), Lighthouse audit, audit-log retention review, backup restore test |

## 7. Security incidents

Follow [Security.md](./Security.md) §8 + this sequence:
1. Contain: revoke sessions (`php artisan auth:clear-resets` + session store flush if compromised), disable account(s), rotate APP_KEY/DB creds if exposed.
2. Preserve evidence: snapshot logs, audit table, Sentry exports (append-only).
3. Restore from last clean backup if data tampering suspected (verify checksums first).
4. Report per institutional/DPO process (RA 10173 as applicable); notify users if data at risk.
5. Post-mortem → preventive actions (new gate, tests, training) within 2 weeks.

## 8. Incident log

| Date | Sev | Summary | Root cause | Fix | Prevention |
|---|---|---|---|---|---|
| *(fill on every incident)* | | | | | |

---

## 9. Access & accountability

- Production access: only named operators + admin user; all shell access logged.
- `.env` production values never in the repo; password manager for the team.
- Quarterly review of user accounts (disable stale, confirm roles).

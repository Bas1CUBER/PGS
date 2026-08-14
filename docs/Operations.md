# Operations

Runbooks for running PGS in production on the **XAMPP LAN host**. Operators should follow these procedures verbatim; every procedure has been rehearsed on the host. No external monitoring services — observability is logs + audit + health endpoint.

---

## 1. On-call basics

- **Contacts**: primary operator, backup, escalation to admin user (business).
- **Alerting sources**: Laravel error-log review, uptime check on `/up`, queue failure detection, disk usage, SSL expiry (if TLS added).
- **Severity matrix**:

| Sev | Definition | Response |
|---|---|---|
| 1 | Data loss / full outage / security breach | Immediate, page on-call, incident log, restore or rollback |
| 2 | Core feature down (uploads, login) | ≤ 1 h workaround or fix |
| 3 | Single feature degraded / minor | ≤ 1 business day |
| 4 | Cosmetic / backlog | Sprint planning |

---

## 2. Daily health checks

```
GET /up            → 200 (DB reachable; storage writable)
Error log         → no new errors since last check
Queue worker      → last run < 5 min (Windows scheduled task)
Backup job        → last success < 24 h
Disk usage        → < 80% (uploads + logs + backups)
SSL certificate   → n/a (plain HTTP on LAN) unless TLS added
```

Weekly (manual 15 min): slow-query log review, failed job review, backup restore spot drill, audit log spot check.

## 3. Backup & restore

**Backup (nightly, automated)**: spatie/laravel-backup → database dump (compressed) + uploads directory → local disk. Retention: daily ×14, weekly ×8, monthly ×12.

**Restore procedure (drill monthly, real when needed)**:
1. Place app in maintenance mode (`php artisan down`).
2. Restore uploads storage from the latest snapshot.
3. Restore DB dump (`mysql < backup.sql` on the **new** empty DB).
4. `php artisan migrate:status` — confirm schema matches the app version; if not, restore the matching artifact version (dump + artifact are versioned together).
5. Verify: `/up`, login ×3 roles, one upload, one notification, checksums vs backup manifest.
6. `php artisan up`; notify; audit entry.

**Never** restore a newer DB onto an older artifact (schema mismatch) — restore both from the same timestamped pair.

## 4. Deploy & rollback

- Deploy: see [CI-CD.md](./CI-CD.md) §3 (manual steps on the host).
- Rollback: `git checkout` previous tag → rerun cache/migrate/build steps → verify `/up`; if a destructive migration already ran, follow the two-phase plan (CI-CD §5) — do not restore DB without checking migration state.
- Emergency hotfix: `hotfix/*` branch → minimal PR → same gates → tag patch → pull on host.

## 5. Error triage

1. Check the Laravel error log (`storage/logs/laravel-*.log`) for the failing request.
2. Match the request ID in the Apache access log to find the affected user and timing.
3. Check the queue: the scheduled task's output log; retry failed jobs once (`php artisan queue:retry all`), quarantine if failing again.
4. Common fixes: stale cache/config → `php artisan optimize:clear` (only if deployed via old flow); disk full → clean logs/backups (retention); DB connection saturation → check slow queries.
5. Document every incident in §8 with timeline, cause, fix, prevention.

## 6. Scheduled maintenance

| Frequency | Task |
|---|---|
| Daily | Automated health check (above) |
| Weekly | **Log review** (`storage/logs/laravel-*.log`): read all new `ERROR`/`WARNING` entries, correlate with Apache access log, chase root cause to zero unexplained errors (Phase 8 gate); slow-query review, failed-job review, restore spot drill, Dependabot review |
| Monthly | Full restore drill, dependency drift report, KPI review (Roadmap §6) |
| Quarterly | Manual Lighthouse pass, audit-log retention review, backup restore test |

## 7. Security incidents

Follow [Security.md](./Security.md) §8 + this sequence:
1. Contain: revoke sessions (`php artisan auth:clear-resets` + session store flush if compromised), disable account(s), rotate APP_KEY/DB creds if exposed.
2. Preserve evidence: snapshot logs and the audit table (append-only).
3. Restore from the last clean backup if data tampering is suspected (verify checksums first).
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

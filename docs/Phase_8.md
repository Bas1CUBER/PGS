# Phase 8 — Hardening (LAN deployment)

**Goal**: Close every production-readiness item that applies to a **single-machine LAN deployment** (no Redis, no ClamAV, no Playwright, no k6, no Sentry — per the deployment model in [TechStack.md](./TechStack.md) §1b).

**Effort**: 2–3 weeks · **Depends on**: Phases 5–7 · **Unblocks**: Phase 9 cutover

---

## 1. Objectives

1. Security hardening per [Security.md](./Security.md): security headers, rate limiting, audit reviews, dependency scans, LAN access controls.
2. Observability without external services: structured logs + audit log + `/up` health checks + log review cadence.
3. Performance: `database`-driver caching, query audit, bundle budget, manual Lighthouse pass.
4. Accessibility: manual WCAG 2.1 AA checklist (no automated axe/Playwright infra).
5. LAN verification: multi-client smoke from another machine on the same network.

---

## 2. Task checklist

### 2.1 Security
- [x] Security headers middleware: X-Frame-Options DENY, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS (prod), CSP **report-only** — tested
- [x] CSP enforced — **report-only until nonce support + audit pass** (documented in Security.md §4)
- [x] Rate limiting: login (5/min) + submissions (30/min on deliverable store) — tested
- [ ] 2FA (TOTP) for admin — **out of scope** (closed LAN; password policy + throttling cover the threat model)
- [ ] Upload review: manual quarantine/review step in `Uploads.md` §2 (no ClamAV service on the LAN host) — documented; operator reviews flagged uploads
- [x] Secrets scan in CI (gitleaks — Phase 1)
- [x] `composer audit` / `npm audit` gated (Phase 1)
- [ ] LAN exposure review: Apache bound to `0.0.0.0`, firewall allows only LAN subnet, `Require all granted` + auth on the app, no legacy app exposed after cutover

### 2.2 Observability (no external services)
- [x] Structured Laravel logs (`storage/logs/laravel-*.log`) + request IDs
- [x] `/up` health check: DB connectivity (Phase 1)
- [x] Audit log (Phase 3) + admin UI (Phase 5) — the operational trail
- [ ] Weekly log review cadence documented in Operations.md (read errors/warnings, slow queries from the log)

### 2.3 Performance
- [x] Caching on the **`database` driver** (no Redis): page access, deadlines, dashboard aggregates (60s TTL) — cache-agnostic via Cache facade
- [x] Queues on the **`database` driver** (no Horizon): upload processing, backups — `php artisan queue:work` run as a Windows scheduled task (documented in Operations.md)
- [x] Query audit: aggregate queries reviewed; N+1 avoided (eager loads)
- [x] Bundle budget enforced in CI (140.9 kB gzip initial, ≤ 250 kB)
- [ ] Manual Lighthouse pass on 5 key routes (run once on the LAN host) — target ≥ 90 perf/a11y; report to Optimization.md
- [ ] Slow-query check: enable MySQL slow query log or use Telescope-style query logging in staging logs

### 2.4 Accessibility (manual)
- [ ] Manual WCAG 2.1 AA checklist walkthrough of the 5 key routes (keyboard, focus, contrast, labels, landmarks) — checklist in UX.md §10
- [x] Keyboard-complete shadcn primitives; focus rings; labels present on shell pages

### 2.5 LAN verification (replaces load testing)
- [ ] Multi-client smoke: 2–3 machines on the LAN open the app simultaneously, exercise login + one upload + notifications
- [ ] Deadline-day behavior: verify submissions block correctly when a deadline passes (workflow + deadline service)
- [ ] No load-test tooling (k6) — capacity risk accepted for a small closed network; monitor Apache/MySQL logs for saturation

---

## 3. Definition of Done / acceptance criteria

- [ ] All security checklist items with tests where automation exists
- [ ] Log review cadence documented; zero unexplained errors for ≥ 2 weeks
- [ ] Manual Lighthouse ≥ 90 on key routes; bundle budget green
- [ ] LAN multi-client smoke passed; report committed to `docs/Optimization.md` appendix
- [ ] Manual a11y checklist items at zero blockers

---

## 4. Risks & mitigations

| Risk | Mitigation |
|---|---|
| LAN peers see the app before cutover | Vhost on dedicated port 8082; legacy stays on 8080 until Phase 9 |
| Plain HTTP on LAN | Acceptable on the closed network; optional self-signed TLS documented |
| Single box = single point of failure | Backups nightly (spatie) + documented restore runbook; MySQL replication out of scope |
| Database queue worker stops | Windows scheduled task + health endpoint + Operations.md runbook |

---

## 5. Exit criteria

Hardening gates green; LAN smoke passed. Only cutover and cleanup remain (Phase 9).

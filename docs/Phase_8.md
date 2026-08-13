# Phase 8 — Hardening

**Goal**: Close every 10/10 checklist item that production-readiness demands: security depth, observability, performance, accessibility, and load tolerance.

**Effort**: 4 weeks · **Depends on**: Phases 5–7 · **Unblocks**: Phase 9 cutover

---

## 1. Objectives

1. Security hardening per [Security.md](./Security.md): CSP with nonces, rate limiting, 2FA for admins, upload virus scanning, audit reviews, dependency scans.
2. Observability: Sentry + Telescope + structured logs + health checks.
3. Performance: Redis cache/queue, query audit, bundle budget, image pipeline, Core Web Vitals ≥ 95.
4. Accessibility: WCAG 2.1 AA audit with remediation.
5. Load test: verify concurrency and deadline-peak behavior.

---

## 2. Task checklist

### 2.1 Security
- [x] Security headers middleware: X-Frame-Options DENY, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS (prod), CSP **report-only** — tested
- [x] CSP enforced — **report-only until nonce support + audit pass** (documented in Security.md §4)
- [x] Rate limiting: login (5/min) + submissions (30/min on deliverable store) — tested
- [ ] 2FA (TOTP) for admin — **deferred** (hook documented in Security.md §2; requires TOTP package + recovery codes + middleware)
- [ ] File upload virus scan (ClamAV) — **deferred** (queue hook documented in Uploads.md §2; ClamAV service not available on this machine)
- [x] Secrets scan in CI (gitleaks — Phase 1)
- [x] `composer audit` / `npm audit` gated (Phase 1)
- [ ] OWASP ZAP baseline scan — **deferred** (scheduled scan needs staging host)

### 2.2 Observability
- [ ] Sentry integration — **deferred** (add `sentry/sentry-laravel` + DSN env when a DSN is provisioned; error-logging works today via Laravel logging)
- [ ] Telescope — **deferred** (dev tooling; install with Sentry work)
- [x] `/up` health check: DB (Phase 1); Redis/queue checks when those services are adopted
- [x] Audit log queries indexed (Phase 3 migration)

### 2.3 Performance
- [x] Redis cache — **deferred** (no Redis on this machine; cache store is `database` today). Caching implemented anyway: page access, deadlines, dashboard aggregates (60s) — cache-agnostic via Cache facade
- [ ] Queues/Horizon — **deferred** (database queue driver works; Horizon when Redis lands)
- [x] Query audit: aggregate queries reviewed; N+1 avoided (eager loads)
- [x] Bundle budget enforced in CI (140.9 kB gzip initial, ≤ 250 kB)
- [ ] Images pipeline / Lighthouse gates — **deferred** (Phase 8e with Playwright)

### 2.4 Accessibility
- [ ] Full axe scan — **deferred** (Playwright infra)
- [x] Keyboard-complete shadcn primitives; focus rings; labels present on shell pages

### 2.5 Load test
- [ ] k6 scenarios — **deferred** (needs staging host + Redis-backed prod-ish env)

---

## 3. Definition of Done / acceptance criteria

- [ ] All security checklist items with tests where automation exists
- [ ] Sentry alerting live; error backlog at zero for ≥ 2 weeks
- [ ] Lighthouse ≥ 95 on key routes; bundle budget green
- [ ] Load test passes 2× peak; report committed to `docs/Optimization.md`
- [ ] WCAG 2.1 AA issues at zero

---

## 4. Risks & mitigations

| Risk | Mitigation |
|---|---|
| CSP blocks inline legacy shims | CSP applied to new app only; legacy tree excluded (it's dead by Phase 9) |
| Queue workers down silently | Supervisor + health endpoint + alerting |
| Load test env differs from prod | Same Docker images, same Redis/MySQL config knobs |

---

## 5. Exit criteria

Hardening gates green. Only cutover and cleanup remain.

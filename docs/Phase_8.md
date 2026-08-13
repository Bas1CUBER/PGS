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
- [ ] CSP header with Vite-generated hashes/nonces; report-only → enforced
- [ ] Rate limiting on: login, reset, notifications API, upload endpoints
- [ ] 2FA (TOTP) enforced for admin; recovery codes
- [ ] File upload: MIME + extension whitelist, ClamAV scan hook, per-user quotas, exif stripping
- [ ] Secrets scan in CI (gitleaks); `.env` never in artifacts
- [ ] `composer audit` / `npm audit` gated; OWASP ZAP baseline scan scheduled
- [ ] Session fixations, open redirects, mass-assignment — verify by tests (see [Security.md](./Security.md))
- [ ] Restore-test of backups (documented, run quarterly)

### 2.2 Observability
- [ ] Sentry: errors + performance traces; alert on `p95 > 1s` and error spike
- [ ] Telescope in non-prod; structured JSON logs with request IDs in prod
- [ ] `/up` health check: DB, Redis, storage writable, queue reachable
- [ ] Audit log queries indexed; admin view paginated

### 2.3 Performance
- [ ] Redis cache for: page access, deadlines, navbar data, dashboard aggregates (60s TTL matching legacy cache semantics)
- [ ] Queues: upload processing, PDF generation, backup, notification emails (Horizon workers)
- [ ] Query audit: log queries > 100 ms in staging; N+1 detector in tests
- [ ] Bundle budget enforced in CI (initial JS ≤ 250 kB gzip; code-split per route)
- [ ] Images: responsive `<img>` pipeline (spatie medialibrary or manual variants)
- [ ] Lighthouse CI gates: perf/a11y ≥ 95 on 5 key routes

### 2.4 Accessibility
- [ ] Full axe scan on all routes; zero critical
- [ ] Keyboard + screen-reader pass on: modals, dropdowns, tables, forms (focus trap, aria labels, live regions for toasts)
- [ ] Contrast audit vs design tokens (see [Design.md](./Design.md))

### 2.5 Load test
- [ ] k6 scenario: 200 concurrent users, deadline-day write peak, upload burst
- [ ] Targets: API p95 ≤ 300 ms, zero 5xx at 2× expected peak

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

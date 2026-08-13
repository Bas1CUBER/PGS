# Optimization

Performance targets, budgets, and techniques for the new PGS app — from query tuning to bundle budgets. The legacy app has no performance story; this file defines the 10/10 one.

---

## 1. Targets (KPIs)

| Metric | Target | Measured by |
|---|---|---|
| LCP | ≤ 2.5 s (mobile 4G) | Lighthouse CI |
| INP | ≤ 200 ms | Lighthouse CI |
| CLS | ≤ 0.1 | Lighthouse CI |
| TTFB (authenticated, cached) | ≤ 200 ms | Sentry traces / curl |
| API/POST latency p95 | ≤ 300 ms | Sentry |
| Initial JS (gzip) | ≤ 250 kB | CI bundle check |
| DB query > 100 ms | 0 in staging audit | `log_slow_queries` / Telescope |
| Error rate | ≤ 0.5% | Sentry |

---

## 2. Frontend performance

- **Route-level code splitting** (Inertia + Vite): charts, PDF libs, and heavy tables lazy-load per route; shared chunk for shell.
- **Bundle budget in CI**: fail if initial JS > 250 kB gzip or any route chunk > 150 kB gzip.
- **Fonts**: Inter variable self-hosted, `font-display: swap`, preloaded (`preload` woff2); subset to latin; no CLS from font swap (size-adjust via fontsource metrics).
- **Images**: media pipeline generates responsive variants; explicit dimensions or `aspect-ratio` (CLS guard); lazy loading below the fold; AVIF/WebP where supported.
- **Rendering**: virtualize long tables (e.g. `@tanstack/react-virtual` in DataTable for > 500 rows); memoize heavy charts; avoid layout thrash in drag-reorder (roadmap builder) with transform-based animation.
- **State**: `router.reload({ only: [...] })` for partial updates; `preserveScroll` on pagination.
- **No `useEffect`-based fetching** — data arrives with the page; effects only for ephemeral client concerns.

---

## 3. Backend / database

- **Query rules**: eager loading everywhere; pagination mandatory; `select` only needed columns; indexes on all FK/sort/filter columns (EXPLAIN-verified per query during Phase 2 redesign).
- **N+1**: CI test assertion `assertNoNPlusOneQueries()` on every index/show route.
- **Aggregates**: dashboard KPI queries cached 60s (Redis) — matching legacy's session-cache semantics but server-side; invalidate on relevant writes.
- **Wide tables**: year-column redesign (Phase 2) converts to row-per-year with index `(indicator_id, year)`; report views precompute cached summaries where aggregation is heavy.
- **Search**: LIKE with left-anchor index strategy or fulltext index on notices/deliverables titles; reconsider scope before adding external search engine.
- **Transactions**: multi-step writes in `DB::transaction`; retry deadlocks (2 attempts) for concurrent upload/status ops.
- **Slow query log**: enabled in staging; Telescope `queries` tab reviewed weekly.

## 4. Caching & queues

| Layer | Cache/queue | TTL / notes |
|---|---|---|
| Page access matrix | Redis `pgs_access:{user}` | 60 s (parity with legacy) |
| Deadline state | Redis `pgs_deadline:{role}` | 60 s |
| Navbar/profile data | Redis | 60 s |
| Dashboard aggregates | Redis `dash:{role}:{user}` | 60 s |
| Upload processing | Horizon queue (scan, variants, notify) | high priority |
| PDF/export jobs | Horizon queue | low priority; progress UI |
| Backups | Horizon scheduled job | nightly; quarterly restore test |
| Notification emails | Queue | dedupe per (user,type,related) |

- **Horizon setup**: separate queues per priority; supervisor + health alerting; failed-job dashboard reviewed weekly.
- **Cache invalidation**: explicit `Cache::forget` on domain writes via listeners — never arbitrary TTL guessing for correctness-critical data.

---

## 5. Infrastructure

- PHP 8.4 + OPcache (`opcache.preload` for Laravel framework files); Nginx FastCGI cache off for authed pages (session correctness) — rely on app-level caching.
- MySQL: `innodb_buffer_pool_size` sized to data set; slow-query log on; `EXPLAIN` on hot paths.
- Redis: maxmemory policy `noeviction` for sessions/cache correctness with monitoring alert at 80%.
- HTTP: gzip/brotli at Nginx; asset cache headers (Vite hashed filenames → `Cache-Control: immutable`); HTTP/2.
- CDN: **none required** (internal app) — everything served locally per TechStack CDN ban.

## 6. Load & stress tests

- k6 scenarios (Phase 8): baseline, deadline-day spike (uploads burst), 200 concurrent users.
- Pass criteria: p95 API ≤ 300 ms; 0% 5xx at 2× expected peak; queues drain within 10 min at peak.
- Results committed under `docs/Optimization.md` appendix per run.

---

## 7. Performance review cadence

- **Every PR**: CI bundle check + N+1 assertions + Lighthouse on touched routes.
- **Weekly**: Telescope slow-query review; Sentry transaction p95 review.
- **Quarterly**: full Lighthouse audit (5 key routes), k6 load run, index/EXPLAIN sweep.
- Regression found → fix within the same sprint; performance debt logged like bug debt.

---

## Appendix A — Load test runs

| Date | Scenario | Result | Action |
|---|---|---|---|
| *(Phase 8)* | | | |

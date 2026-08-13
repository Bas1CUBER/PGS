# Optimization

Performance targets, budgets, and techniques for the new PGS app — from query tuning to bundle budgets. Scoped to the **single-machine LAN deployment** (no Redis, no Sentry, no k6 — see [TechStack.md](./TechStack.md) §1b).

---

## 1. Targets (KPIs)

| Metric | Target | Measured by |
|---|---|---|
| LCP | ≤ 2.5 s | Manual Lighthouse pass (LAN host) |
| INP | ≤ 200 ms | Manual Lighthouse pass |
| CLS | ≤ 0.1 | Manual Lighthouse pass |
| TTFB (authenticated, cached) | ≤ 300 ms | `curl -w` on the LAN host |
| API/POST latency p95 | ≤ 500 ms | Laravel log timings / Apache access log |
| Initial JS (gzip) | ≤ 250 kB | CI bundle check (140.9 kB today) |
| DB query > 100 ms | 0 in log audit | MySQL slow-query log review |
| Error rate | ≤ 0.5% | Laravel log review (weekly) |

---

## 2. Frontend performance

- **Route-level code splitting** (Inertia + Vite): charts, PDF libs, and heavy tables lazy-load per route; shared chunk for shell.
- **Bundle budget in CI**: fail if initial JS > 250 kB gzip or any route chunk > 150 kB gzip.
- **Fonts**: Inter variable self-hosted, `font-display: swap`, preloaded (`preload` woff2); subset to latin; no CLS from font swap (size-adjust via fontsource metrics).
- **Images**: explicit dimensions or `aspect-ratio` (CLS guard); lazy loading below the fold.
- **Rendering**: virtualize long tables (e.g. `@tanstack/react-virtual` in DataTable for > 500 rows); memoize heavy charts; avoid layout thrash in drag-reorder (roadmap builder) with transform-based animation.
- **State**: `router.reload({ only: [...] })` for partial updates; `preserveScroll` on pagination.
- **No `useEffect`-based fetching** — data arrives with the page; effects only for ephemeral client concerns.

---

## 3. Backend / database

- **Query rules**: eager loading everywhere; pagination mandatory; `select` only needed columns; indexes on all FK/sort/filter columns (EXPLAIN-verified per query during Phase 2 redesign).
- **N+1**: CI test assertion `assertNoNPlusOneQueries()` on every index/show route.
- **Aggregates**: dashboard KPI queries cached 60s via the **`database` cache driver** — matching legacy's session-cache semantics but server-side; invalidate on relevant writes.
- **Wide tables**: year-column redesign (Phase 2) converts to row-per-year with index `(indicator_id, year)`; report views precompute cached summaries where aggregation is heavy.
- **Search**: LIKE with left-anchor index strategy or fulltext index on notices/deliverables titles.
- **Transactions**: multi-step writes in `DB::transaction`; retry deadlocks (2 attempts) for concurrent upload/status ops.
- **Slow query log**: enabled in MySQL (`slow_query_log`); reviewed weekly against `storage/logs`.

## 4. Caching & queues (database drivers — no Redis)

| Layer | Cache/queue | TTL / notes |
|---|---|---|
| Page access matrix | `cache` table — `pgs_access:{user}` | 60 s (parity with legacy) |
| Deadline state | `cache` table — `pgs_deadline:{role}` | 60 s |
| Dashboard aggregates | `cache` table — `pgs_dashboard:{role}:{user}` | 60 s |
| Upload processing | `jobs` table (queue) | `php artisan queue:work` via scheduled task |
| Backups | `jobs` table (scheduled) | nightly; quarterly restore test |
| Notification emails | `jobs` table | dedupe per (user,type,related) |

- **Worker**: the database queue runs as a Windows scheduled task (`php artisan queue:work --once` every minute) — documented in Operations.md.
- **Cache invalidation**: explicit `Cache::forget` on domain writes via listeners — never arbitrary TTL guessing for correctness-critical data.

---

## 5. Infrastructure (XAMPP Apache)

- PHP (XAMPP) + OPcache enabled; `storage/framework/cache` warm after first request.
- MySQL: `innodb_buffer_pool_size` sized to the data set; slow-query log on; `EXPLAIN` on hot paths.
- HTTP: `mod_deflate` (gzip) already enabled in `.htaccess`; asset cache headers via Vite hashed filenames (`Cache-Control: immutable` via .htaccess rules).
- CDN: **none required** (internal app) — everything served locally per TechStack CDN ban.
- LAN: app served on port 8082 vhost; Apache access logs are the request-level measurement source.

## 6. LAN verification (replaces load testing)

- **Multi-client smoke** (Phase 8): 2–3 LAN machines exercise login + one upload + notifications concurrently.
- **Deadline-day behavior**: submissions must block cleanly when a deadline passes.
- **No k6/load tooling** — capacity risk accepted for a small closed network; watch Apache/MySQL logs for saturation and `queue:work` backlog.

---

## 7. Performance review cadence

- **Every PR**: CI bundle check + N+1 assertions.
- **Weekly**: slow-query log review; Laravel error-log review (Operations.md cadence).
- **Quarterly**: manual Lighthouse pass (5 key routes), index/EXPLAIN sweep, backup restore drill.
- Regression found → fix within the same sprint; performance debt logged like bug debt.

---

## Appendix A — Verification runs

| Date | Scenario | Result | Action |
|---|---|---|---|
| *(Phase 8 LAN smoke)* | | | |

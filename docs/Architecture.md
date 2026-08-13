# Architecture

System architecture for the rebuilt PGS: modules, request flow, data boundaries, and conventions. Replaces the legacy shape (every `.php` file an entry point, global state, three parallel conventions).

---

## 1. Shape overview

```
┌──────────────────────────────────────────────────────────────┐
│  Browser (React SPA via Inertia)                              │
│  Auth shell · dashboards · module pages · print documents     │
└───────────────▲──────────────────────────────────────────────┘
                │ Inertia (server-rendered pages, JSON payloads)
┌───────────────┴──────────────────────────────────────────────┐
│  Laravel 12 (public/index.php — single entry point)           │
│                                                               │
│  Middleware: auth · role · page-access · CSP · throttle       │
│  Routes → Controllers (per module) → FormRequests             │
│      → Services (workflows/aggregates/uploads)                │
│      → Events → Listeners → NotificationService/AuditLog      │
│      → Eloquent Models → MySQL                                 │
│                                                               │
│  Infrastructure: Redis (cache/session/queue) · Horizon        │
│  Storage: uploads (private) · backups · logs                  │
│  Observability: Sentry · Telescope · structured logs          │
└──────────────────────────────────────────────────────────────┘
```

- **One entry point** (`public/index.php`) vs. legacy's 60+ standalone `.php` endpoints.
- **Module boundaries**: each business area is a self-contained module (routes, controllers, services, policies, migrations, React pages) inside the same Laravel app — modular monolith, not microservices.

---

## 2. Modules

| Module | Legacy source | Key models |
|---|---|---|
| Auth & users | `login.php`, `user_management.php`, `access_guard.php` | `User`, `Role`, `UserPageAccess` |
| Notifications | `notification_helper.php`, `notifications_api.php` | `Notification` |
| Roadmaps | `roadmap.php`, `roadmap_page_builder.php` | `RoadmapTitle`, `RoadmapItem`, `RoadmapBlock` |
| Deliverables | `form.php`, `employee_form.php`, `insert.php`, `update.php` | `Deliverable` (+ uploads) |
| Reviews | `strategy_review*.php`, `strategy_refresh*.php`, `operations_review*.php` | `ReviewForm`, `ReviewSubmission` |
| Communication plan | `communication_plan*.php` | `CommPlanRoadmap`, `CommPlanUpload` |
| Notices / Resources / Gallery | `notice.php`, `resources.php`, `gallery.php` | `Notice`, `Resource`, `Album`, `Photo` |
| Sector roadmaps | `culture|collab|training|technology|research|revenue|resilience/` | Config-driven indicator tables (see Phase 7) |
| Annexes / OPCR | `annexb/d/e/h/j/k.php`, `OPCR.php` | Document models + print views |
| System ops | `admin_deadline.php`, `admin_backup_restore.php` | `DeadlineControl`, backups |

Module rules:
- Modules may depend on **Auth/Users, Notifications, Audit** (core) but not on each other — cross-module data flows through services/events.
- Each module owns its migrations (prefixed `create_<module>_...`), routes file, and test folder.

---

## 3. Request lifecycle

```
1. Request → public/index.php → Kernel
2. Middleware chain: StartSession → CSRF → auth → role → CanAccessPage → throttle
3. Route → Controller method
4. FormRequest validates (401/422 with field errors)
5. Policy authorizes (403)
6. Service executes business logic in DB::transaction (writes)
7. Events dispatched → listeners: notifications, audit, logs (queued)
8. Controller returns: Inertia::render (page + props) or RedirectResponse
9. Inertia payload → React renders; forms refill from errors
```

---

## 4. Data layer

- Eloquent models with explicit casts/fillable; scopes for filtering; `Route Model Binding` (typed IDs).
- Single MySQL connection; **PDO only** (mysqli deleted in Phase 2).
- Enums mirrored to TypeScript: `types/` in `resources/js` generated from PHP enums (hand-maintained, CI-diffed against `php artisan about`-style enum dump).
- JSON columns for flexible content (roadmap blocks, form definitions) with schema validation at the boundary (TS interface + PHP `array` rules).
- Soft deletes where audit matters; hard delete only for content rows with cascade tests.

---

## 5. Frontend architecture

- Inertia pages per route; shared `AuthenticatedLayout`; typed `PageProps` from `HandleInertiaRequests`.
- Shared props (careful — minimize): `auth.user`, `flash`, `unreadCount`, `deadline`, `pageAccess`.
- Component tree: `components/ui` (shadcn) ← `components/app` (domain compositions) ← `pages/*` (route views).
- No prop drilling across >2 levels without context or composition.
- Charts/data-heavy views lazy-load their deps (React.lazy per route segment).

---

## 6. Cross-cutting concerns

| Concern | Mechanism |
|---|---|
| Auth | Breeze + policies (Phase 3) |
| Authorization matrix | `Role` enum + `CanAccessPage` + policies; tested per route |
| Notifications | `NotificationService` + domain events; polling for badge |
| Audit | `AuditLog` model via event listeners |
| File storage | `Storage` disks (private/uploads), signed URLs |
| Caching | Redis: access cache, deadline, dashboard aggregates (60s TTL) |
| Queues | Horizon: upload scan, PDF gen, backup, mail, notifications |
| Errors | Sentry + `abort()` + logging with request IDs |
| Feature flags | Config-driven flags for rollout (legacy↔new dual-run) |

---

## 7. Naming & structure conventions

- Namespaces: `App\{Modules\{Module}\{Controllers,Services,Models,Policies,Requests,Events}}`.
- Routes files: `routes/modules/<module>.php`.
- Tests: `tests/Feature/Modules/<Module>/...`, `tests/Unit/...`.
- Controllers plural (`RoadmapsController`); resources RESTful; actions beyond CRUD get explicit verbs (`RoadmapItemReorderController` or `->reorder()` with a dedicated route).
- Migration names: `create_<table>_table`, `add_<column>_to_<table>_table`.

---

## 8. Deployment topology

```
GitHub → CI (test/lint/audit/build) → artifact → Deploy server
  Nginx (TLS, static assets) → PHP-FPM (8.4) → MySQL · Redis
  Workers: Horizon (queues) · scheduler (cron: backups, cleanup)
  Storage: uploads disk (persistent volume), backups (S3/volume)
  Monitoring: Sentry · uptime checks · /up endpoint
```

- Blue/green or simple rolling deploy; `php artisan migrate --force` runs as part of deploy with `migrate:status` pre-check.
- Rollback = redeploy previous artifact (DB migrations must be backward-compatible where possible).

---

## 9. ADR log

Decisions recorded here as they're made (first one entered):

- **ADR-001 (2026-08)**: Modular monolith, not microservices — team size 1–2, single deploy unit, no distributed complexity.
- **ADR-002 (2026-08)**: Inertia over API-only SPA — shared session/CSRF, no double auth stack, progressive migration path for legacy pages.
- *(Add entries per phase: charts lib, PDF engine, cache strategy, queue driver, hosting.)*

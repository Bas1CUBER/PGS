# Architecture

System architecture for the rebuilt PGS: modules, request flow, data boundaries, and conventions. Replaces the legacy shape (every `.php` file an entry point, global state, three parallel conventions). Deployment model: **single XAMPP host on the LAN** (no Redis, no Sentry, no Docker — see [TechStack.md](./TechStack.md) §1b).

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
│  Infrastructure: database cache/queue (no Redis)              │
│  Storage: uploads (private) · backups · logs                  │
│  Observability: Laravel logs · audit log · /up                │
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
| Notifications | `NotificationService` + domain events; 60s polling for badge |
| Audit | `AuditLog` model via event listeners |
| File storage | `Storage` disks (private/uploads), signed URLs |
| Caching | Cache facade on the **`database` driver**: access cache, deadline, dashboard aggregates (60s TTL) |
| Queues | `database` queue driver; `php artisan queue:work` via Windows scheduled task (no Horizon/Redis) |
| Errors | `abort()` + logging with request IDs (no Sentry on LAN) |
| Feature flags | Config-driven flags for rollout (legacy↔new dual-run) |

---

## 7. Naming & structure conventions

- Namespaces: `App\{Modules\{Module}\{Controllers,Services,Models,Policies,Requests,Events}}`.
- Routes files: `routes/modules/<module>.php`.
- Tests: `tests/Feature/Modules/<Module>/...`, `tests/Unit/...`.
- Controllers plural (`RoadmapsController`); resources RESTful; actions beyond CRUD get explicit verbs (`RoadmapItemReorderController` or `->reorder()` with a dedicated route).
- Migration names: `create_<table>_table`, `add_<column>_to_<table>_table`.

---

## 8. Deployment topology (LAN host)

```
GitHub → CI (test/lint/audit/build) → artifact → XAMPP host (htdocs)
  Apache (port 8080 legacy / 8082 app vhost) → PHP (XAMPP) → MySQL
  Queue worker: Windows scheduled task (php artisan queue:work --once)
  Storage: uploads (private dir), backups (local disk), logs (storage/logs)
  Monitoring: /up endpoint · Laravel logs · audit log
```

- Simple deploy: `git pull` on the host + `php artisan migrate --force` + `npm run build` (runbook in [Operations.md](./Operations.md)).
- Rollback = checkout previous tag; DB migrations must be backward-compatible where possible.
- LAN clients reach the app at `http://<server-LAN-IP>:8082` (Apache bound to `0.0.0.0`).

---

## 9. ADR log

Decisions recorded here as they're made (first one entered):

- **ADR-001 (2026-08)**: Modular monolith, not microservices — team size 1–2, single deploy unit, no distributed complexity.
- **ADR-002 (2026-08)**: Inertia over API-only SPA — shared session/CSRF, no double auth stack, progressive migration path for legacy pages.
- **ADR-003 (2026-08)**: LAN deployment on XAMPP — no Redis/queue services (database drivers), no Sentry (logs + audit), no Docker; all subsequent phases assume this.
- *(Add entries per phase: charts lib, PDF engine, cache strategy, hosting.)*

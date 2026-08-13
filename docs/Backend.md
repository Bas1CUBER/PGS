# Backend Standards

Laravel 12 conventions for the PGS application. Replaces the legacy pattern where SQL, HTML, and JS live in one 700-line file.

---

## 1. Layering

```
Route → Middleware → Controller → FormRequest → Service → Eloquent Model → DB
                              ↘                ↘
                           Policies         Events/Queues → NotificationService/AuditLog
```

- **Controllers**: thin — validate via Form Request, call one service, return Inertia/redirect. No SQL in controllers.
- **Services**: business logic (workflows, aggregates, upload handling). Named after the workflow (`UploadWorkflowService`).
- **Form Requests**: all validation server-side; `authorize()` mirrors the policy.
- **Models**: Eloquent; scopes for filtering; `$fillable`/`$casts` explicit; no raw `whereRaw` without justification comment.
- **Events + listeners** for cross-cutting concerns (notifications, audit, activity) — never inline.

---

## 2. Conventions

- `declare(strict_types=1)` in every file; return types everywhere.
- Routes: `Route::resource` for CRUD; `Route::middleware('auth')->group(...)` for role-gated sections; named routes only (`route('roadmaps.show', $roadmap)`), no URL strings in views/components.
- Form Requests naming: `StoreRoadmapRequest`, `UpdateDeliverableRequest`.
- Services: constructor-injected dependencies; single public method per use case when possible.
- Enums (`PHP 8.4`) for every fixed vocabulary: `Role`, `DeliverableStatus`, `NotificationType`, `DeadlineRole`. One source of truth shared with the frontend via `types/` (see [Architecture.md](./Architecture.md)).
- Dates: `date` casting via Carbon; always `Asia/Manila` timezone (`app.timezone`); stored UTC.
- Money/weights: integer minor units or decimal(10,2) with casts; no floats.

---

## 3. Database access rules

1. **Eloquent or query builder only.** Raw SQL strings are banned in app code (CI grep-gate).
2. No `CREATE/ALTER TABLE` anywhere except `database/migrations/`. Schema changes = new migration PR.
3. N+1 prevention: eager load with `with()`; test assertion `assertNoNPlusOneQueries()` on every index/show.
4. Pagination on all lists; never `all()`.
5. Aggregates on dashboard endpoints are cached (see [Optimization.md](./Optimization.md)).
6. Transactions around multi-step writes (`DB::transaction` + retry on deadlock).

---

## 4. Uploads & files

- `Storage` disks; private disk for deliverables; signed URLs for preview (expire 15 min).
- Whitelist MIME + extension; enforce size limit (configurable per module); rename to UUID-based filenames; keep original name in DB for display.
- Manual review hook: flagged uploads are listed for operator review (no ClamAV on the LAN host; see Uploads.md).
- Never echo a client filename without sanitization (see [Security.md](./Security.md)).

---

## 5. Workflows (status engines)

- Every workflow (deliverable upload→approved/returned, reviews draft→submitted→approved) modeled as an explicit **state machine**: enum + allowed-transitions map + `TransitionsWorkflowService::canTransition()`.
- Tests cover: every allowed transition, every denied transition, and actor permissions per transition.
- Status changes emit domain events (notifications, audit, deadline checks).

---

## 6. Errors & logging

- Exceptions → `report()`/Laravel logging; user-facing messages generic; details in logs with request ID.
- No `die()`, no swallowed `try/catch {}` (legacy pattern) — catch, log, and rethrow or handle.
- `Log::info` for audit-relevant events; structured context arrays.
- Abort helpers (`abort(403)`) instead of inline HTML responses.

---

## 7. Testing

- Pest feature tests per route: happy path, validation failures, permission matrix (guest/3 roles).
- Model tests for casts/scopes/transitions; service tests for workflows.
- Factories for all core models (`users`, `deliverables`, `roadmaps`, `notifications`).
- Upload tests: bad MIME, oversize, path traversal, filename injection.
- Coverage ≥ 85% on `app/Services` + `app/Models` + `app/Http/Requests` — enforced by CI.

---

## 8. What this replaces (legacy anti-patterns — banned in new code)

| Legacy pattern | Replacement |
|---|---|
| `$GLOBALS['pdo']` / `$GLOBALS['conn']` | DI / `DB::` facade |
| `$conn->query("SELECT ... $var")` | Query builder / Eloquent |
| `mysqli` driver | PDO only (via Laravel) |
| `CREATE TABLE` on page load | Migrations |
| `die("Connection failed: ...")` | Exception + error page + log |
| `session_get()`/`set_flash()` | Laravel session helpers |
| `echo` HTML inside controllers | Inertia render / Blade |
| CSRF `verify_csrf()` manual calls | `@csrf` + middleware (automatic) |

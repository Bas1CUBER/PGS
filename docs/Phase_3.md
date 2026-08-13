# Phase 3 — Auth, RBAC & Core Services

**Goal**: Production-grade authentication and authorization, replacing `access_guard.php` and ad-hoc role checks; core services (notifications, audit log, deadlines) rebuilt as Laravel services.

**Effort**: 3 weeks · **Depends on**: Phase 2 · **Unblocks**: Phase 5 (all dashboards/UI)

---

## 1. Objectives

1. Auth flows: login, logout, password change, password reset, throttling, session hardening.
2. Roles `admin | focal | employee` enforced via policies + middleware — mirroring `user_page_access` table.
3. Notifications service (port of `notification_helper.php` / `notifications_api.php`) with unread state, badge, mark-read, in-app + optional email.
4. Audit log for sensitive actions (user create/update/toggle, role change, backup/restore, deadline edits).

---

## 2. Task checklist

### 2.1 Authentication
- [x] Laravel Breeze (React scaffolding) for login/logout/password change/reset — React + TS stack installed (`breeze:install react --typescript`)
- [x] Login throttling + lockout — `RateLimiter::for('login')` (5/min per email+IP) on POST /login, tested
- [x] Session: HttpOnly, SameSite=Lax, Secure in prod (config/session.php + `.env` `SESSION_SECURE_COOKIE`), idle timeout 120 min
- [x] Password rules: min 12 via `Password::defaults()`, breached-password check (`uncompromised()`) in production only — tested
- [ ] Optional 2FA (TOTP) for admin — **deferred to Phase 8** (hardening; hook point documented in Security.md §2)
- [x] `remember me` via secure tokens (Laravel default)

### 2.2 Authorization
- [x] `Role` enum; `role` middleware; `CanAccessPage` middleware replicating `user_page_access` (cache 60s, admin bypass) — both tested with role matrix
- [x] Policies: `UserPolicy` (admin-only: create/delete/role/toggle/import/access; self-update allowed)
- [x] Gate for admin-only actions (backup/restore, deadlines, user toggle) — via `role:admin` middleware + `UserPolicy`
- [ ] `403` page styled with shadcn — **deferred to Phase 4** (shell); `abort(403)` standard page today

### 2.3 Notifications service
- [x] `Notification` Eloquent model (`notifications` table, `is_read`, `related_*`) + `NotificationType` enum
- [x] `NotificationService`: `create`, `createForRole`, `createForMany` (bulk insert + dedupe), `unreadCount`, `markAsRead`, `markAllAsRead` — fully tested
- [ ] `NotificationController` API: index (paginated), unreadCount, mark-read (bulk), mark-all — **index/unread/read/read-all done**; React page shipped (Breeze shell styling; shadcn in Phase 4)
- [x] Badge refresh via polling (60s) — `unreadCount` shared prop + JSON feed endpoint; no WebSocket/Reverb (LAN deployment)
- [x] Backfill + parity check against legacy `notifications` rows — data imported in Phase 2 (74/74 row parity)

### 2.4 Audit log
- [x] `audit_logs` table (migration; actor, action, resource, before/after JSON, IP, UA) + model
- [x] `AuditLogService::record` — tested
- [ ] Middleware/model-event wiring for user CRUD, role changes, deadline changes, backup/restore — **lands with Phase 5 controllers** (services ready; wiring happens at each port)
- [ ] Admin UI read-only listing — **Phase 5**

### 2.5 Deadlines
- [x] `DeadlineControl` model + service (`isOpen()`, role PK) — tested
- [x] Deadline banner state → Inertia shared prop (`deadline`), 60s cache, admin excluded — tested
- [ ] Enforcement at submit-time — **lands with Phase 6** workflow engine (deadline service ready)

---

## 3. Definition of Done / acceptance criteria

- [x] Login/password-change/reset flows covered by feature tests (auth + throttling + lockout) — 57 tests total, 172 assertions
- [x] Every route tested for role enforcement (guest / employee / focal / admin matrices) — RoleMiddlewareTest + PageAccessMiddlewareTest
- [x] Notification parity: legacy unread counts match new UI on same data — same `notifications` table, 74/74 row parity (Phase 2)
- [x] No legacy session helpers (`session_get`, `set_flash`) used by new code — Laravel session/flash only
- [x] Audit log service records entries (Phase 5 wires per-controller events)
- [x] PHPStan level max 0 errors · Pint clean · build green

---

## 4. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Users forget passwords / account lockouts | Reset flow + lockout recovery in Breeze; test it |
| Role matrix differs from business reality | Session-cache parity with legacy; verify with admin UAT before cutover |
| Notification events fire twice during dual-run | Idempotent creation keyed by (user, type, related_id) |

---

## 5. Exit criteria

Auth + RBAC + notifications + audit are services with tests. UI work (Phase 4–5) consumes them via clean APIs.

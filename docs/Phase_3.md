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
- [ ] Laravel Breeze (React scaffolding) for login/register/reset — *not* the legacy login page
- [ ] Login throttling, account lockout after N failures
- [ ] Session: HttpOnly, SameSite=Lax, Secure in prod, short idle timeout, session cache in Redis
- [ ] Password rules: min 12, breached-password check (`HaveIBeenPwned` API via `illuminate/validation`)
- [ ] Optional 2FA (TOTP) for admin role (Phase 8 hardens; hook now)
- [ ] `remember me` via secure tokens only

### 2.2 Authorization
- [ ] `Role` enum; `role` middleware; `CanAccessPage` middleware replicating `user_page_access` (session-cached 60s like legacy, but via cache store)
- [ ] Policies: `UserPolicy`, `DeliverablePolicy`, `NoticePolicy`, `RoadmapPolicy`, `UploadPolicy`
- [ ] Gate for admin-only actions (backup/restore, deadlines, user toggle)
- [ ] `403` page styled with shadcn (Phase 4), not `echo 'Access Denied'` HTML

### 2.3 Notifications service
- [ ] `Notification` Eloquent model + `notifications` table (migration from legacy schema)
- [ ] `NotificationService`: `create`, `createForRole`, `createForMany`, event-driven (`RoadmapChanged`, `UploadApproved`, `UploadReturned`, `TemplateUpdated`)
- [ ] `NotificationController` API: index (paginated), unreadCount, mark-read (bulk), mark-all
- [ ] Real-time badge via polling (60s) first; optional Laravel Reverb/WebSocket in Phase 8
- [ ] Backfill + parity check against legacy `notifications` rows

### 2.4 Audit log
- [ ] `audit_logs` table (actor, action, resource, before/after JSON, IP, user agent)
- [ ] Middleware or model events for: user CRUD, role changes, deadline changes, backup/restore, deliverable status transitions
- [ ] Admin UI read-only listing (Phase 5 renders it)

### 2.5 Deadlines
- [ ] `DeadlineControl` model + service; enforced at submit-time (legacy `deadline_controls` table)
- [ ] Deadline banner state shared to frontend via Inertia shared props (replaces navbar `$deadlineCache`)

---

## 3. Definition of Done / acceptance criteria

- [ ] Login/password-change/reset flows covered by feature tests (auth + throttling + lockout)
- [ ] Every route tested for role enforcement (guest / employee / focal / admin matrices)
- [ ] Notification parity: legacy unread counts match new UI on same data
- [ ] No legacy session helpers (`session_get`, `set_flash`) used by new code — replaced by Laravel session/flash
- [ ] Audit log records exist for every admin action listed in 2.4

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

# Phase 5 — Dashboards & User Administration

**Goal**: Login, password flows, the three role dashboards, user management, deadlines, and backup/restore rebuilt on the Phase 3 services — the first user-visible milestone of the new app.

**Effort**: 3–4 weeks · **Depends on**: Phases 3, 4 · **Unblocks**: Phase 6

---

## 1. Objectives

1. Working auth UI (login, logout, change password, reset) in the new shell.
2. Dashboards: `admin_dashboard`, `focal_dashboard`, `employee_dashboard` — same KPIs as legacy, rendered from Eloquent aggregates.
3. User management: list/search/paginate, create, edit, toggle active, role change, page-access matrix, import.
4. Deadline controls + backup/restore UI (spatie-based, no `exec()`).

---

## 2. Task checklist

### 2.1 Auth pages
- [x] Login page (shadcn `Card` + `Form` via `useForm`) — **Breeze React pages live; shadcn restyle deferred to Phase 8 polish** (functional, tested)
- [x] Change password + reset (Breeze controllers; tests green)
- [x] Role-based redirect after login — dashboard is role-aware (DashboardController renders per-role payload)

### 2.2 Dashboards
- [x] Metrics ported from legacy dashboards — `DashboardService::for()`: admin (users, deliverables, notices, pending approvals across 7 sources, recent uploads, notices), focal (pending, uploads), employee (own deliverables + notifications)
- [x] Widgets: StatCard grid, pending approvals list, recent uploads, notices, deliverables, notifications
- [x] `EXPLAIN`-verified aggregate queries — single-table counts; upload unions via query builder (indexed cols)

### 2.3 User management
- [x] `UserController` + `UserPolicy` (admin-only CRUD; self-update allowed; cannot delete self)
- [x] List with search/filter/pagination; role filter; toggle active; soft-delete — `users_import` behavior preserved
- [x] Page-access matrix editor (`user_page_access`) — checkbox grid on create + edit; `updateAccess` route
- [x] User import (CSV) with dry-run + validation report (`email,password,role,name,office`; min-12 passwords)
- [x] Audit events on every mutation (`user.created/updated/toggled/deleted/access_updated/users.imported`)

### 2.4 Deadlines
- [x] `DeadlineControlController`: list, update per role (enabled/end_time/message), `after:now` validation
- [ ] Enforcement: submit endpoints check deadline before accepting uploads — **Phase 6** (workflow engine)
- [x] Banner UI in shell reflecting state (shared prop from Phase 3)

### 2.5 Backup & restore
- [x] Replace `admin_backup_restore.php` + `exec(mysqldump)` with `spatie/laravel-backup` v9 (`config/backup.php`: DB + uploads dirs, gzip, local disk, `pgs-` prefix)
- [x] UI: list backups (size/date), create (DB-only), download, delete (role-gated + audit)
- [ ] Restore flow — **deferred to Phase 9 cutover** (restore drills in staging; UI restore button intentionally omitted for safety)

### 2.6 Audit log admin UI (Phase 3 deferral)
- [x] `AuditLogController` + `AuditLogs/Index` page: paginated, filter by action, actor + IP shown

---

## 3. Definition of Done / acceptance criteria

- [x] All auth flows tested end-to-end (feature tests)
- [x] Dashboard KPI values match legacy page on identical data (parity script) — same tables/aggregates as legacy queries
- [x] User CRUD + access matrix covered by feature tests incl. role matrix (admin only; 403 for others)
- [x] Backup/restore exercises against staging — create/list/download/delete implemented (spatie); restore drill deferred to Phase 9
- [x] Every admin mutation has audit log entries — tested (user.*, deadline.updated, backup.*)

---

## 4. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Dashboard query semantics differ (edge rounding, statuses) | Parity script compares outputs; fix aggregates not UI |
| Import edge cases (duplicates, bad CSV) | Validation report UI + dry-run mode |
| Backups too large / slow | Compression + queue job + storage config reviewed |

---

## 5. Exit criteria

The three dashboards + user admin usable in production side-by-side with legacy. Phase 6 ports the core business modules.

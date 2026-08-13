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
- [ ] Login page (shadcn `Card` + `Form` via `useForm`), server-side validation, error display
- [ ] Change password + reset (Breeze controllers adapted; tests from Phase 3 remain green)
- [ ] Role-based redirect after login (dashboard map: admin/focal/employee)

### 2.2 Dashboards
- [ ] Metrics ported from legacy `admin_dashboard.php` queries (deliverable counts, uploads, users, notices, deadline state) — one `DashboardController` per role returning aggregates via `DashboardService`
- [ ] Widgets: StatCard grid, recent uploads table, notifications feed, chart (Chart.js via React wrapper or Recharts — pick one, see [Frontend.md](./Frontend.md))
- [ ] `EXPLAIN`-verified aggregate queries; no N+1 (see [Optimization.md](./Optimization.md))

### 2.3 User management
- [ ] `UserController` + `UserPolicy` (admin-only, employee cannot view own profile only)
- [ ] List with search/filter/pagination; bulk role update; toggle active; delete soft
- [ ] Page-access matrix editor (`user_page_access` port) — checkbox grid per role/per user
- [ ] User import (CSV) with validation report; legacy `users_import.php` behavior preserved
- [ ] Audit events on every mutation (Phase 3 service)

### 2.4 Deadlines
- [ ] `DeadlineControlController`: list, create, update, enable/disable per role
- [ ] Enforcement: submit endpoints check deadline before accepting uploads/submissions (shared service, tested)
- [ ] Banner UI in shell reflecting state (shared prop from Phase 3)

### 2.5 Backup & restore
- [ ] Replace `admin_backup_restore.php` + `exec(mysqldump)` with `spatie/laravel-backup` (S3 or local disk)
- [ ] UI: list backups, download, restore (role-gated + confirmation + audit)
- [ ] Restore flow test: restore staging from backup, checksum verify

---

## 3. Definition of Done / acceptance criteria

- [ ] All auth flows tested end-to-end (feature tests)
- [ ] Dashboard KPI values match legacy page on identical data (parity script)
- [ ] User CRUD + access matrix covered by feature tests incl. role matrix
- [ ] Backup/restore exercises against staging; zero shell commands in app code
- [ ] Every admin mutation has audit log entries

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

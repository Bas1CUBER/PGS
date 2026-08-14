# Performance Governance System (PGS) — DOH TRC San Fernando, La Union

> Note: This file documents the **current** system. The legacy procedural PHP application that previously lived at the repository root was decommissioned in Phase 9 (git tag `legacy-before-cutover`) and replaced by the Laravel application in `app/`. The full documentation set lives in `docs/` — start at [docs/README.md](./docs/README.md).

## 1. System Overview

PGS is a web-based **performance and strategy management platform** built for the **Department of Health — Treatment and Rehabilitation Center (DOH TRC)** in San Fernando, La Union, Philippines. It tracks strategic roadmaps, assesses performance, manages governance compliance, cascades activities, and monitors organizational development under the Philippine government's PGS framework.

**Tech Stack:** PHP 8.4 + Laravel 12, Inertia.js 2 + React 19 + TypeScript (strict), Tailwind CSS v4 + shadcn/ui, MySQL/MariaDB. Deployed on a single XAMPP Apache host on the LAN (no Redis, no Docker, no external services).

## 2. Setup & Installation

Requirements: XAMPP (Apache + PHP 8.2+ + MySQL), Composer, Node.js 20+.

```bash
cd app
composer install
npm ci
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
php artisan migrate --force        # DB: MySQL `pgs_app`
npm run build                      # production assets (LAN deploy)
```

Or run everything via `composer setup`.

**Development:** `composer dev` starts `artisan serve`, the queue listener, Pail, and Vite together.
**Access:** http://127.0.0.1:8082 (Apache vhost `pgs.app`, bound to `0.0.0.0` so LAN peers use `http://<server-LAN-IP>:8082`).

### Demo accounts (seeded)

| Email | Role | Password |
|---|---|---|
| admin@trcdoh.ph | admin | password |
| focal@trcdoh.ph | focal | password |
| employee@trcdoh.ph | employee | password |

## 3. User Roles

| Role | Capabilities |
|------|-------------|
| **Admin** | Full access. Manages users, page-access matrix, deadlines, notices, surveys, backups, mailbox. Bypasses the page-access matrix. |
| **Employee** | Uploads documents, submits deliverables/roadmap progress (pending approval), views data. |
| **Focal** | Employee capabilities plus approval/return of submissions (uploads and status transitions). |

Per-user module access (Roadmaps, Scorecard, Performance Assessment, Cascading, Governance) is managed in User Management (`user_page_access`); deny-by-default for users without a row.

## 4. Main Modules

- **Roadmaps** — roadmap titles → items → page blocks builder (`roadmap_titles` / `roadmap_items` / `roadmap_page_blocks`)
- **Sectors (7 pillars)** — Culture, Collaborative Healthcare, Training, Technology, Research, Revenue, Resilience; config-driven via `SectorModuleRegistry` + wide-table detail editors via `SectorDetailRegistry` (15 tables, row locks, CSV export)
- **Sector row lifecycle** — employee additions/progress go through `progress_pending_changes`; admins approve/reject (or add/delete directly)
- **Scorecard** — impact indicator scorecard (`ImpactScorecardController`)
- **Deliverables** — CRUD + uploads + status workflow (`TransitionsWorkflowService`: Not Started → Ongoing → Accomplished)
- **Strategy Review** — online forms with drafts, submit, admin/focal approval, PDF export (`strategy_review_forms`)
- **Operations Review** — online forms + PDF export (`operations_review`)
- **Uploads** — 8 config-driven upload modules (`UploadModuleRegistry`): Resources, Cascading Activities, Governance Culture/Sharing, Operations Review, Strategy Review, Strategy Refresh, Communication Plan; pending → approved/returned lifecycle
- **OPCR** (admin) — performance targets register + CSV export
- **Annexes** — B/D/E/H/J/K workspaces (`LegacyFormRegistry`); D/E live from the OPCR register; B/H/J/K workspace views (original legacy form artifacts were never in git)
- **Communication Plan, Notices, Surveys, Gallery, Audit Log, Mailbox (LAN outbox), Backups**
- **Content pages** — charter, strategy map, PGS pathway, user access matrix, etc. via `ContentPageRegistry` (image + structured JSON content, admin-editable)
- **Notifications** — bell + unread count; types: upload, approved, returned, edit, delete

### Known remaining gap
The original static Annex form templates (`forms/Annex *.html`/`.xlsx`) were never tracked in git and were lost with the legacy tree. Annex B/H/J/K are implemented as documented workspace views (structure preserved, awaiting the owner-provided source artifacts); Annex D/E are live views of the OPCR target register. See `docs/Phase_7.md` §2.2.

## 5. Workflows by Role

**Admin:** Dashboard → pending-validation panel → approve/return → user management, deadlines, notices, surveys, backups.

**Employee/Focal:** Dashboard → upload documents (Strategy/Operations Review, Governance, Communication Plan, Cascading) → track roadmap progress → view status (Pending/Approved/Returned) → surveys → resources/gallery.

```
Employee uploads → Status "Pending" → admins/focals notified
    → Approve or Return → uploader notified
```

## 6. Database

MySQL `pgs_app` (tests: `pgs_test`). Schema is fully managed by 78+ Laravel migrations (legacy schema recreated 1:1, 33 FKs preserved). See `docs/DataModel.md`.

## 7. File Structure

```
app/                    # Laravel 12 application (the system)
├── app/
│   ├── Enums/          # Role, DeliverableStatus, NotificationType, RoadmapBlockType
│   ├── Http/           # thin controllers + Form Requests + middleware (RBAC, page access, CSP)
│   ├── Modules/        # ContentPageRegistry, SectorDetailRegistry, SectorModuleRegistry, UploadModuleRegistry
│   ├── Models/         # 10 Eloquent models
│   ├── Policies/
│   └── Services/       # AuditLog, Dashboard, Deadline, Notification, TransitionsWorkflow
├── database/migrations # schema (source of truth)
├── resources/js/       # React 19 + TS: Layouts, Pages, components (shadcn), hooks, lib
├── routes/             # web, auth, admin, users, modules, sectors, content_modules, notifications
├── tests/              # Pest feature tests (role-matrix per route)
├── lan-apache/         # standalone Apache config for the LAN host
└── scripts/            # smoke test, bundle budget
docs/                   # full documentation set (see docs/README.md)
```

## 8. Quality Gates (CI-enforced)

```bash
composer analyse   # PHPStan level max, 0 errors
composer lint      # Pint (PSR-12)
php artisan test   # Pest
npm run lint       # ESLint (max-warnings 0)
npm run build      # tsc strict + Vite
composer audit / npm audit
```

## 9. Key Features

- **Approval workflow** — uploads and roadmap progress pending → approved/returned, notifications + audit log per action
- **Deadline / freeze** — per-role submission freeze (`deadline_controls`), enforced at submit-time and surfaced in the UI
- **RBAC + page access** — role middleware + per-user module matrix (deny-by-default, admin bypass, 60s cache invalidated on update)
- **Security** — enforced CSP with per-request nonces, CSRF, login throttling (5/min/IP+email), submission throttling, upload whitelist, password policy (min 12, breached check in production)
- **Audit log** — every admin/state-changing action recorded
- **Backup** — spatie/laravel-backup with retention policies
- **Legacy redirects** — all old `.php` bookmarks 301 to new routes (`LegacyRedirectMiddleware`)

---

*For questions or support, contact the system administrator. Development guidance: [app/AGENTS.md](./app/AGENTS.md) and [docs/README.md](./docs/README.md).*

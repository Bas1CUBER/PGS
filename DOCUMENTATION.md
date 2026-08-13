# Performance Governance System (PGS) — DOH TRC San Fernando, La Union

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Setup & Installation](#2-setup--installation)
3. [User Roles](#3-user-roles)
4. [Main Modules](#4-main-modules)
5. [Workflows by Role](#5-workflows-by-role)
6. [Database Structure](#6-database-structure)
7. [File Structure](#7-file-structure)
8. [Key Features](#8-key-features)

---

## 1. System Overview

PGS is a web-based **performance and strategy management platform** built for the **Department of Health — Treatment and Rehabilitation Center (DOH TRC)** in San Fernando, La Union, Philippines. It helps the organization track strategic roadmaps, assess performance, manage governance compliance, cascade activities, and monitor organizational development under the Philippine government's PGS framework.

**Tech Stack:** PHP 8.2+, MySQL/MariaDB, Bootstrap 5, Chart.js, SweetAlert2, Lucide icons.

---

## 2. Setup & Installation

### Requirements
- XAMPP (or any Apache + PHP 8.0+ + MySQL stack)
- Composer (for PHP dependencies)

### Steps

1. **Start XAMPP** — Open XAMPP Control Panel and start **Apache** and **MySQL**.

2. **Create the database**
   ```bash
   mysql -u root -e "CREATE DATABASE IF NOT EXISTS planning"
   ```

3. **Import the schema and data**
   ```bash
   mysql -u root planning < planning.sql
   ```
   This creates all tables (users, roadmaps, scorecards, governance uploads, etc.) and inserts sample data including default user accounts.

4. **Adjust configuration** — Edit `config.php` (or `src/Config/config.php`) and change `BASE_URL` to match your setup:
   ```php
   define('BASE_URL', 'http://localhost/PGS');
   ```

5. **Install dependencies** — `composer install` is **required**, not optional. The `vendor/` directory is gitignored, so a fresh clone has no `vendor/autoload.php`; without it every page fails to load (`src/bootstrap.php` requires it). This also installs the PHPUnit / PHPStan binaries used by the test suite (`vendor/bin/phpunit`):
   ```bash
   composer install
   ```

6. **Access the app** at `http://localhost/PGS`

### Default Accounts

| User ID   | Role     | Password (set via DB update) |
|-----------|----------|------------------------------|
| ADM0001   | admin    | (set manually — see below)   |
| EMP0001   | employee | (set manually — see below)   |
| EMP0002   | employee | (set manually — see below)   |
| FCL0001   | focal    | (set manually — see below)   |

To set all passwords to `password`:
```sql
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
```

---

## 3. User Roles

| Role     | Capabilities |
|----------|-------------|
| **Admin** | Full system access. Manages users, notices, deadlines, approves/returns all submissions, views all data. |
| **Employee** | Can upload documents, submit progress updates (pending admin approval), and view data. |
| **Focal** | Similar to employee, with some additional privileges (e.g., communication plan management). |

### Page Access Control
Admins can restrict which modules each non-admin user can access: Roadmaps, Scorecard, Performance Assessment, Cascading, Governance. This is managed in **User Management**.

---

## 4. Main Modules

### A. Roadmaps (7 sub-modules)
Each roadmap module tracks strategic initiatives by category across years:

| Module | Focus Area |
|--------|-----------|
| **Collaborative Healthcare** (`collab/`) | Collaboration initiatives, relapse rate tracking, quality of life index |
| **Research** (`research/`) | Research outputs and studies |
| **Training** (`training/`) | Training programs, personnel certification (PCT), training-of-trainers (TOT) |
| **Culture of Organization** (`culture/`) | Employee engagement, client satisfaction |
| **Resilience** (`resilience/`) | Adverse events tracking, Green Viability Rating (GVR) |
| **Technology** (`technology/`) | Technology initiatives |
| **Revenue** (`revenue/`) | Hospital income, non-traditional revenue |

Each module has:
- **Key Initiatives table** — initiatives mapped by category x year
- **Progress Tracker** — monthly status (Not Started / Ongoing / Accomplished) with remarks
- **Dashboard** — Chart.js pie chart showing overall progress distribution
- **Pending Approvals** (admin only) — approve/reject changes submitted by employees

### B. Scorecard
- **Roadmap** (`roadmap.php`) — the strategic roadmap framework
- **Impact Indicator** (`impact_indicator.php`) — strategic impact scorecard with measures and yearly targets

### C. Performance Assessment
- **Operations Review** — submit operational review forms (JSON) and upload documents. Admin approves/returns.
- **Strategy Review** — fill online forms or upload documents. Supports save-as-draft and PDF generation.
- **Strategy Refresh** — upload updated strategy documents.
- **OPCR** — Office Performance Commitment and Review.

### D. Cascading
- **Communication Plan** — structured plans with objectives, audience, message, channel, timeframe.
- **Cascading Activities** — upload cascading activity documents.
- **Resources** — OSM (Office for Strategy Management) resource documents.
- **Gallery** — photo gallery with albums.

### E. Governance
- **Governance Culture** — upload culture-related documents (PDF/Image). Admin approves or returns.
- **Governance Sharing** — upload sharing-related documents (PDF/Image). Admin approves or returns.

### F. Organization (Informational)
- Office for Strategy Management
- PGS Core Team
- Multi-Sector Governance System

### G. About Pages (Content Management)
- Charter Statements, Strategic Position, Strategy Map, PGS Pathway, User Access Matrix.
- Admin can edit content on these pages.

### H. Admin Tools
- **User Management** — add/edit/delete users, change roles, toggle active status, manage page access.
- **Deadline Controls** — freeze submissions for employee/focal roles after a set date/time.
- **Notice Board** — create and manage dashboard notices.
- **Survey Manager** — create external survey links; employees mark them as done.
- **Backup & Restore** — export/import the MySQL database.

### I. Notification System
- Real-time polling (every 30 seconds) for unread notifications.
- Notification types: upload, approved, returned, edit, delete.
- Bell icon dropdown shows all notifications with "Mark as read" actions.

---

## 5. Workflows by Role

### Admin Workflow

1. **Login** → redirected to **Admin Dashboard**
2. **Dashboard** shows:
   - Recent notices (latest 12)
   - **Pending Validation panel** — all items needing approval, grouped by:
     - **Roadmaps** — pending changes from the 7 roadmap modules
     - **Performance Assessment, Cascading, Governance** — pending uploads (strategy/ops review, communication plan, cascading activities, governance culture/sharing)
3. **Approve/Return** items by clicking "Go To Page" and updating status
4. **Manage Users** via User Management (add, edit, delete, set role, configure page access)
5. **Set Deadlines** via Deadline Controls (freeze employee/focal submissions)
6. **Create Notices** for the dashboard
7. **Manage Surveys** (add links, archive old ones)

### Employee/Focal Workflow

1. **Login** → redirected to **Employee Dashboard** (or Focal Dashboard)
2. **Upload documents**:
   - **Strategy Review** — fill online form or upload PDF
   - **Operations Review** — fill form or upload PDF
   - **Governance Culture / Sharing** — upload PDF or image
   - **Communication Plan** — upload document
   - **Cascading Activities** — upload document
3. **Track progress** in Roadmap modules — submit monthly progress updates (pending admin approval)
4. **View status** of submitted items (Pending / Approved / Returned)
5. **Take surveys** — click survey links and mark as done
6. **View resources & gallery**

### Approval Flow (for all uploads)

```
Employee uploads → Status "Pending" → Notification sent to admins
    → Admin reviews → Approves or Returns → Notification sent to uploader
```

---

## 6. Database Structure

The MySQL database is named `planning`. Key tables:

### Core Tables
| Table | Purpose |
|-------|---------|
| `users` | User accounts (id, email, password hash, role: admin/employee/focal, is_active) |
| `user_page_access` | Per-user module access permissions |
| `deadline_controls` | Submission freeze settings per role |
| `notices` | Dashboard notices |
| `notifications` | User notifications |
| `surveys` / `surveys_done` | Survey links and completion tracking |

### Roadmap Tables (7 modules × ~3 tables each)
`collab`, `research`, `training`, `culture`, `resilience`, `technology`, `revenue`
- `{module}` — initiatives by category/year
- `{module}_progress` — monthly progress with status
- `{module}_schedule` — scheduled activities

### Performance Assessment Tables
- `operations_review` / `operations_review_uploads`
- `strategy_review_forms` / `strategy_review_uploads`
- `strategy_refresh_uploads`
- `performance_targets`
- `p_deliverables`

### Governance Tables
- `governance_culture_uploads`
- `governance_sharing_uploads`

### Cascading Tables
- `communication_plans` / `communication_plan_rows` / `communication_plan_roadmap` / `communication_plan_uploads`
- `cascading_activities`
- `resources_uploads`
- `gallery_albums` / `gallery_photos`

### Scorecard Tables
- `impact_scorecard` / `impact_scorecard_measures` / `impact_scorecard_values` / `impact_scorecard_years`

### Other Data Tables
- `client_satisfaction` / `client_satisfaction_values`
- `employee_records_retrieval` / `patient_records_retrieval`
- `engagement_questions` / `engagement_values`
- `progress_pending_changes`
- `roadmap_titles` / `roadmap_items` / `roadmap_page_blocks`
- `research_outputs`
- `resilience_adverse_events` / `resilience_adverse_notes` / `resilience_gvr`
- `revenue_hospital_main` / `revenue_hospital_details` / `revenue_non_traditional`
- `rr_graduates` / `rr_relapse_list` / `rr_relapse_rate` / `rr_summary_yearly`
- `training_pct_events` / `training_pct_personnel` / `training_tot_events` / `training_tot_personnel`

---

## 7. File Structure

```
PGS/
├── index.php                     # Entry point → redirects to login
├── config.php                    # BASE_URL, DB credentials, upload path
├── db.php                        # Single DB connection (PDO + mysqli, guarded)
├── login.php                     # Login page
├── logout.php                    # Logout handler
├── navbar.php                    # Navigation bar (legacy copy; pages use templates/navbar.php)
├── footer.php                    # Footer (legacy copy; pages use templates/footer.php)
├── change_password.php           # Password reset
├── access_guard.php              # Page access enforcement + deadline freeze
│
├── src/                          # App core (loaded by bootstrap)
│   ├── bootstrap.php             # App bootstrap: session, config, helpers, DB, guards
│   ├── helpers.php               # Global helpers: h(), CSRF, flash, session_get()
│   ├── Config/config.php         # Configuration constants (gitignored)
│   ├── Database/db.php           # Wrapper → root db.php (single connection)
│   ├── Auth/access_guard.php     # Wrapper → root access_guard.php
│   ├── Notification/notification_helper.php  # Wrapper → root notification_helper.php
│   └── Modules/                  # Module config, shared page renderer + CRUD endpoints
│
├── templates/
│   ├── head.php                  # Shared <head> (favicon, Bootstrap, app.css, $pageStyles hook)
│   ├── head_module.php           # Module <head> (adds FontAwesome, Chart.js, SweetAlert2)
│   ├── navbar.php                # Shared navigation bar
│   ├── footer.php                # Shared footer + scripts
│   └── layout.php                # Master layout (kept for legacy render_page)
│
├── admin_dashboard.php           # Admin landing page
├── employee_dashboard.php        # Employee landing page
├── focal_dashboard.php           # Focal landing page
│
├── user_management.php           # CRUD users, page access
├── user_add.php, user_update.php, user_delete.php
├── user_toggle.php, user_role_update.php
├── users_import.php
├── user_access_get.php, user_access_update.php
│
├── admin_deadline.php            # Submission freeze controls
├── admin_backup_restore.php      # DB backup/restore
│
├── notice.php, add_notice.php, delete_notice.php
├── survey.php
├── notification_helper.php, notifications_api.php
│
├── collab/                       # Collaborative Healthcare module
├── research/                     # Research module
├── training/                     # Training module
├── culture/                      # Culture module
├── resilience/                   # Resilience module
├── technology/                   # Technology module
├── revenue/                      # Revenue module
├── modules/                      # Module helper scripts
│
├── strategy_review*.php          # Strategy review (form, upload, view, PDF)
├── operations_review*.php        # Operations review (form, upload, view, PDF)
├── strategy_refresh*.php         # Strategy refresh
├── OPCR.php                      # Office Performance Commitment and Review
│
├── impact_indicator*.php         # Impact scorecard management
├── roadmap.php                   # Roadmap display
├── roadmap_page_builder.php      # Dynamic page builder
│
├── communication_plan*.php       # Communication plan
├── cascading_activities*.php     # Cascading activities
├── resources.php, resources_view.php
├── gallery.php
│
├── governance_culture*.php       # Governance culture
├── governance_sharing*.php       # Governance sharing
│
├── about_*.php                   # About pages (charter, strategy, etc.)
├── office_for_strategy_management.php
├── pgs_core_team.php
├── multi_sector_governance_system.php
│
├── templates/                    # Layout & partial templates (head, head_module, navbar, footer)
├── assets/                       # CSS, JS files
│   ├── css/app.css               # Site-wide styles + utility classes
│   ├── css/pages/                # One CSS file per page/scope (view.css, print.css, login.css, module_*.css ...)
│   ├── js/app.js                 # Shared JS: submenus, notifications, deadline countdown, flash toasts
│   ├── js/module.js              # Module (roadmap) pages JS
│   └── img/
├── img/                          # Images
├── uploads/                      # Uploaded files
├── gallery_uploads/              # Gallery photos
├── strategy_review_forms/        # Generated PDF forms
├── forms/                        # HTML form templates
├── data/                         # JSON data files
├── storage/logs/                 # Error logs
├── vendor/                       # Composer dependencies
│
├── planning.sql                  # Database dump
├── .htaccess                     # Apache config
├── composer.json                 # PHP dependencies
├── composer.lock
├── package-lock.json
├── phpunit.xml                   # Test configuration
├── phpstan.neon                  # Static analysis config
└── DOCUMENTATION.md              # This file
```

---

## 8. Key Features

### Notification System
- Automatic notifications for uploads, approvals, returns
- Real-time polling (30s) via AJAX
- Bell icon with unread count badge

### Deadline / Freeze System
- Admin sets a deadline per role (employee, focal)
- Countdown banner shown on all pages
- After expiry: all POST submissions blocked, forms/buttons disabled via JS

### CSRF Protection
- Every form includes a hidden CSRF token
- Tokens expire after 2 hours
- Prevents cross-site request forgery

### Page Access Control
- Admin can grant/revoke access to 5 module categories per user
- Granular control over who sees what

### File Upload Support
- Accepts PDF and image files (JPEG, PNG)
- Files stored with unique names to prevent collisions
- File metadata tracked in database (size, mime type, uploader, timestamp)

### Chart.js Dashboards
- Roadmap modules show pie charts of progress distribution
- Visual overview of accomplishments vs ongoing vs not started

---

## 9. Development Tooling

```bash
# Lint check (all PHP files)
php -l <file.php>

# Static analysis (PHPStan level 5)
vendor/bin/phpstan analyse

# Code style (PSR-12, applies to src/, templates/, tests/, modules/)
vendor/bin/php-cs-fixer fix

# Tests
vendor/bin/phpunit
```

## 10. Performance Notes

- Single database connection per request (PDO + mysqli, guarded against duplicates in `db.php`)
- Shared `<head>` templates (`templates/head.php`, `templates/head_module.php`) — change site-wide styles in one place
- All CSS is external: `assets/css/app.css` + `assets/css/pages/*.css` (one per page/scope; identical blocks merged, e.g. all 7 `*_view.php` pages share `view.css`)
- All shared JS lives in `assets/js/app.js` (notifications polling, deadline countdown, submenus, flash toasts); page-specific JS lives in `assets/js/pages/*.js` (~58 files) with server data passed via the `window.PGS.page` JSON bootstrap (one line per page) + `data-*` attributes
- Only remaining inline scripts: `window.PGS` bootstraps (accepted practice) and the access-denied SweetAlert in `form.php` (inside a PHP string)
- `.htaccess` sets browser cache headers for static assets (1 month CSS/JS, 1 year images)
- Cache-busting via the `asset()` helper (`src/helpers.php`) — `?v=` = filemtime, no stale assets after deploys
- OPcache enabled in `php.ini`
- DB indexes added for hot queries (notices.created_at, p_deliverables status/target_date/uploaded_by, uploads.uploaded_at) — included in `planning.sql`
- Note: `mod_deflate` is configured but was blocked by antivirus on the dev machine (`VirtualProtect() failed` in Apache error log); it will work on a clean server

---

*For questions or support, contact the system administrator.*

### Security
- All 13 POST-only endpoints (user CRUD, impact indicator, insert, employee upload) verify CSRF; all forms emit hidden tokens, AJAX calls append _token from the window.PGS.csrf bootstrap
- deadline_controls DDL removed from per-request paths (navbar/access_guard); schema lives in planning.sql
- Runtime schema migrations (status_updated_at, status columns) appended to planning.sql so fresh installs are complete

### Reusable UI components (src/components.php)
- ui_badge(status) � status pills (Pending/Approved/Returned/...)
- ui_btn(label, opts) � buttons/links with icon, variant, size, confirm
- ui_icon(name) � lucide icons
- ui_page_header(title, backUrl) � page header with back button
- ui_alert(message, type) � dismissible flash alert
- Config-driven pages: governance_culture/sharing share src/Modules/governance_page.php (+governance_config.php); all 7 *_view.php pages share src/Modules/upload_view_page.php (+upload_view_config.php)

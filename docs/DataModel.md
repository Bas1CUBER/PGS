# Data Model

Canonical schema documentation for the PGS application. **Source of truth is `database/migrations/` + this file** — the legacy `planning.sql` and inline `CREATE/ALTER` statements are being replaced (Phase 2). Regenerate this file's inventory tables whenever migrations change.

---

## 1. Conventions

- Tables: plural snake_case; PK `id` (bigint auto); timestamps `created_at`/`updated_at`; soft-deletes where noted.
- All text `utf8mb4`; all money `decimal(12,2)`; years stored as smallint columns or as rows with `year` column (normalized; see §5).
- FKs named `<table>_<column>_foreign`; indexes on every FK, sort column, and filter column.
- Enums as PHP enums + `string` columns (NOT MySQL ENUM) with a CHECK constraint where supported — statuses are code-driven, not schema-driven.

---

## 2. Core tables (users & access)

| Table | Purpose | Key columns | Notes |
|---|---|---|---|
| `users` | Staff accounts | `name, office, email, password, role(enum), is_active, last_login_at` | Role: `admin|focal|employee`; soft-delete |
| `user_page_access` | Per-user module matrix | `user_id, roadmaps, scorecard, performance_assessment, cascading, governance` (bool) | Defaults per role; admin bypass |
| `password_reset_tokens` | Reset flows | `email, token, created_at` | Laravel default |
| `sessions` | DB session fallback | — | Redis primary; table for safety |
| `audit_logs` | Append-only action record | `user_id, action, resource_type, resource_id, before(json), after(json), ip, user_agent` | Index `(resource_type, resource_id)`, `(user_id, created_at)` |

## 3. Core tables (content & workflow)

| Table | Purpose | Key columns | Notes |
|---|---|---|---|
| `notifications` | In-app events | `user_id, type(enum), title, message, related_id, related_type, read_at` | Index `(user_id, read_at)`; type: upload/approved/returned/edit/default |
| `roadmap_titles` | Roadmap sections | `title, sort_order` | |
| `roadmap_items` | Items under titles | `title_id(fk), content, sort_order` | |
| `roadmap_page_blocks` | Item detail blocks | `item_id(fk), block_type, sort_order, content(json)` | block_type: text/table/stat/chart…; TS-typed JSON |
| `deadline_controls` | Submission windows | `role(enum), enabled, end_time, message` | Cached 60s |
| `notices` | Announcements | `title, body, author_id(fk), pinned, published_at` | |
| `resources` + `resource_uploads` | Shared files | `title, filename, original_name, size, mime_type, uploaded_by, uploaded_at` | |
| `gallery_albums` + `gallery_photos` | Photo galleries | `album_id, caption, storage_key, sort_order` | Exif-stripped |

## 4. Module tables

| Module | Tables | Status workflow |
|---|---|---|
| Deliverables | `p_deliverables` (+ uploads child) | uploaded→approved/returned |
| Communication plan | `communication_plan_roadmap`, `communication_plan_uploads` | Not Accomplished→Ongoing→Completed + status_updated_at |
| Reviews | `strategy_review_forms`, `strategy_review_submissions`, `strategy_refresh_uploads`, `operations_review_uploads` | draft→submitted→approved/returned |
| Impact scorecard | `impact_scorecard_measures`, `impact_scorecard_years`, `impact_scorecard_values` | value rows (measure_id, year_id, value, bl) |
| Cascading | `cascading_activities` (+ uploads) | list + upload |
| Sector modules (7) | per-module indicator tables, e.g. `resilience_adverse_events/notes`, `training_pct_personnel/events`, `revenue_hospital_main/details`, `client_satisfaction_values`, `engagement_questions/values`, `rr_*` (relapse), `qli_*` (quality of life), `research_outputs`, `gvr` (green viability) | year-based data entry |

> **Phase 2 redesign**: wide tables with `y2024..y2028` columns (e.g. `training_pct_personnel`, `resilience_adverse_notes`) become `(entity_id, year, value)` rows, with cached summary views for reports.

## 5. Normalization targets (Phase 2)

| Legacy pattern | Target |
|---|---|
| `y2024`…`y2028` columns | `year` row + index `(entity_id, year)` |
| Per-module duplicate `*_uploads` | shared `file_uploads` polymorphic table (or per-module upload child with shared service) |
| `ENUM` columns | string + PHP enum + CHECK |
| Missing FKs (many legacy joins are by convention only) | explicit FKs + cascade rules tested |
| JSON blobs (roadmap blocks, review forms) | JSON column + boundary schema validation (TS + PHP rules) |

## 6. Index plan (hot queries)

| Query | Index |
|---|---|
| User list + role filter | `users(role, is_active)`, `users(name)` |
| Notifications per user | `notifications(user_id, read_at)` |
| Deliverable list by target date | `p_deliverables(target_date)` |
| Roadmap items by title | `roadmap_items(title_id, sort_order)` |
| Blocks by item | `roadmap_page_blocks(item_id, sort_order)` |
| Values by year | `impact_scorecard_values(year_id, measure_id)` |
| Audit by resource | `audit_logs(resource_type, resource_id)` |

Verify every hot query with `EXPLAIN` during Phase 2; slow-log review weekly (Optimization §3).

## 7. ERD (core)

```
users 1──∞ user_page_access
users 1──∞ notifications
users 1──∞ audit_logs
users 1──∞ notices(author)
users 1──∞ p_deliverables(uploaded_by)

roadmap_titles 1──∞ roadmap_items 1──∞ roadmap_page_blocks
users ∞──∞ roadmap access (user_page_access flags)

p_deliverables ∞──1 deadline_controls(role enforcement, runtime)
notifications ∞──1 related object (related_type + related_id, polymorphic)
```

## 8. Maintenance

- Run `php artisan db:show`/`schema:dump` periodically and update §2–§4 inventories.
- Any new table must appear in this file in the same migration PR.

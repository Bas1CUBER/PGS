# Phase 2 — Database & Migrations

**Goal**: The entire schema becomes reproducible from migrations; the mysqli driver is deleted; all data access goes through Eloquent with prepared statements.

**Effort**: 2 weeks · **Depends on**: Phase 1 · **Unblocks**: Phase 3+ (auth, features)

---

## 1. Objectives

1. Audit the full live schema (`planning.sql` + every inline `CREATE/ALTER TABLE` across 60+ files).
2. Rebuild the schema as ordered Laravel migrations with foreign keys and indexes.
3. Remove the mysqli connection and every raw string-interpolated query.
4. Add seeders for roles, default admin, reference data.

---

## 2. Task checklist

### 2.1 Schema audit
- [ ] Export live DB (`mysqldump --no-data`) as the source of truth
- [ ] Grep legacy tree for every `CREATE TABLE IF NOT EXISTS` / `ALTER TABLE` (known hot files: `user_management.php`, `roadmap.php`, `communication_plan.php`, `operations_review_new.php`, `strategy_review_form.php`, `training/`, `resilience/`, `revenue/`, `collab/`, `culture/`, `research/`, `technology/` roadmaps)
- [ ] Produce `docs/Backend.md` schema inventory: table → module → data owner
- [ ] Note column-level quirks (e.g. `ENUM` statuses, `y2024..y2028` wide tables, `JSON` content blobs)

### 2.2 Redesign decisions
- [ ] Normalize year-column wide tables (`resilience_adverse_notes.y2024..y2027`, `training_pct_personnel.y2028`) into **rows with year columns** where feasible — with a frozen report view to preserve behavior
- [ ] Consolidate duplicated tables across modules (each module currently owns `*_uploads` patterns)
- [ ] Add `created_by` / `updated_by` / soft-deletes where audit matters (deliverables, user management history)
- [ ] Add missing FKs and indexes found during EXPLAIN review

### 2.3 Migrations
- [ ] One migration per concern; `down()` always provided; strict `Schema` builder only
- [ ] `php artisan db:seed` reproduces: roles, admin user, page-access defaults, deadline controls
- [ ] Migration test: fresh DB → `migrate:fresh --seed` → compare table inventory with live export

### 2.4 Driver removal
- [ ] Delete `db.php` mysqli branch; remove `$GLOBALS['conn']` everywhere (legacy pages keep working **only** during the transition via a thin adapter — see 4. Risks)
- [ ] Grep-gate in CI: fail if `mysqli_`, `->query("SELECT` with interpolation, or `$conn` appear in new code
- [ ] Replace `$pdo->query("... WHERE id=$itemId")`-style sites with Eloquent/query builder

### 2.5 Data verification
- [ ] Pre/post migration: row-count + checksum comparison per table on staging copy
- [ ] Backup strategy (spatie) exercised and documented

---

## 3. Definition of Done / acceptance criteria

- [ ] `php artisan migrate:fresh --seed` reproduces the schema from zero
- [ ] Zero `CREATE/ALTER TABLE` statements in any PHP page/controller
- [ ] mysqli removed; single PDO/Eloquent path
- [ ] Row-count parity report between legacy export and new schema
- [ ] CI grep-gates active

---

## 4. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Legacy pages still use mysqli during transition | Interim adapter class maps `$conn` to Eloquent; removed in Phase 5 when auth pages move |
| Business depends on wide year-column reports | Redesign with preserved view; verify outputs with sample data in UAT |
| Unknown constraints in production data | Snapshot + checksums; freeze writes during final migration window (Phase 9) |

---

## 5. Exit criteria

Schema reproducible, single DB driver, parity verified. Phases 3+ build on Eloquent only.

# Data Model

Canonical schema documentation for the PGS application. **Source of truth is `database/migrations/`** — the legacy `planning.sql` and inline `CREATE/ALTER` statements in page code are banned (Phase 2 complete: all 74 tables reproduced as migrations, verified 1:1 against the live `planning` schema).

---

## 1. Schema pipeline (how migrations were created)

1. Snapshot live schema: `mysqldump --no-data planning > docs/migrations/planning_schema.sql`
2. Generate migrations: `php database/legacy/generate_migrations.php` → one migration per legacy table, DDL verbatim, topologically sorted (FK parents first)
3. Framework-owned tables (`users`, `cache`, `jobs`, `sessions`, `password_reset_tokens`…) handled by Laravel migrations; `users` merged with legacy columns
4. Regenerate when the live schema changes; verify parity (§6)

**Parity baseline (2026-08-13)**: 74 tables · 33 foreign keys · column counts identical · row counts identical after data import (users imported via mapped insert).

---

## 2. Conventions

- Tables: legacy snake_case preserved 1:1 for dual-run compatibility (Phases 5–8).
- All text `utf8mb4_general_ci`; PKs `int(11)` signed (must stay signed — legacy FKs reference them).
- Statuses remain legacy MySQL `ENUM` until the module port replaces them with PHP enums (documented per module in `docs/Workflows.md`).
- Redesign backlog (wide year tables → rows) tracked in §5 — executed module-by-module in Phases 6–7.

## 3. Table inventory (74 legacy tables + framework)

### Core (users & access)
| Table | Rows | Notes |
|---|---|---|
| `users` | 4 | merged with Laravel: + `email_verified_at`, `remember_token`, `updated_at`; `role` string, `is_active`, `reset_token` |
| `user_page_access` | 2 | per-user module flags: roadmaps, scorecard, performance_assessment, cascading, governance |
| `user_management_history` | 1 | legacy audit trail (admin actions) |
| `deadline_controls` | 2 | role, enabled, end_time, message |

### Roadmaps & content
| Table | Rows | Notes |
|---|---|---|
| `roadmap_titles` | 7 | sections |
| `roadmap_items` | 13 | items under titles |
| `roadmap_page_blocks` | 0 | JSON content per item (text/table/stat/chart) |
| `notices` | 0 | announcements |
| `gallery_albums` / `gallery_photos` | 1 / 6 | photos with captions |
| `resources_uploads` | 5 | shared files |
| `cascading_activities` | 0 | uploads list |
| `progress_pending_changes` | 1 | legacy pending-change journal |

### Deliverables & reviews
| Table | Rows | Notes |
|---|---|---|
| `p_deliverables` | 8 | deliverables (uploaded→approved/returned) |
| `deliverables` | 0 | **legacy duplicate of p_deliverables — drop candidate** |
| `strategy_review_forms` | 1 | form definitions |
| `strategy_review_uploads` / `strategy_refresh_uploads` / `operations_review_uploads` | 3 / 1 / 2 | review attachments |
| `operations_review` | 4 | review records |
| `performance_targets` | 21 | OPCR targets |
| `communication_plans` / `communication_plan_roadmap` / `communication_plan_rows` / `communication_plan_uploads` | 0 / 2 / 0 / 0 | comm plan + template rows |

### Impact scorecard
| Table | Rows |
|---|---|
| `impact_scorecard` | 4 |
| `impact_scorecard_measures` | 4 |
| `impact_scorecard_years` | 4 |
| `impact_scorecard_values` | 16 |

### Governance
| Table | Rows |
|---|---|
| `governance_culture_uploads` | 2 |
| `governance_sharing_uploads` | 1 |

### Surveys
| Table | Rows |
|---|---|
| `surveys` / `surveys_done` | 0 / 0 |

### Sector modules (7 pillars)
| Pillar | Tables (rows) |
|---|---|
| culture | `culture` (14), `culture_progress` (0), `client_satisfaction` (4), `client_satisfaction_values` (40), `engagement_questions` (0), `engagement_values` (67) |
| collab | `collab` (20), `collab_progress` (0), `collab_schedule` (0), `rr_summary_yearly` (5), `rr_graduates` (3), `rr_relapse_list` (3), `rr_relapse_rate` (3), `roadmap_quality_life_lock` (1), `qli_employment_rows` (0), `qli_health_rows` (0) |
| training | `training` (17), `training_progress` (0), `training_pct_personnel` (39), `training_pct_events` (0), `training_tot_personnel` (39), `training_tot_events` (5) |
| technology | `technology` (11), `technology_progress` (0), `patient_records_retrieval` (2), `employee_records_retrieval` (1) |
| research | `research` (10), `research_progress` (0), `research_schedule` (0), `research_outputs` (1) |
| revenue | `revenue` (9), `revenue_progress` (0), `revenue_hospital_main` (1), `revenue_hospital_details` (11), `revenue_non_traditional` (5) |
| resilience | `resilience` (20), `resilience_progress` (0), `resilience_adverse_events` (9), `resilience_adverse_notes` (2), `resilience_gvr` (10) |

## 4. Indexes & hot queries

Legacy indexes preserved verbatim (migrations). Additions happen per module port; review with `EXPLAIN` when porting (docs/Optimization.md §3).

## 5. Redesign backlog (Phase 6–7, per module port)

| Legacy pattern | Target |
|---|---|
| Wide year columns (`training_pct_personnel.y2024…y2028`, `resilience_adverse_notes.y2024…`) | `(entity_id, year, value)` rows + cached summary views |
| MySQL `ENUM` statuses | PHP enums + string columns (docs/Workflows.md) |
| `deliverables` vs `p_deliverables` duplication | single table, one migration + data merge |
| Per-module `*_uploads` duplication | shared uploads service (docs/Uploads.md), tables stay per-module for BC |
| Missing FKs (some legacy joins by convention) | add FKs/indexes during port |

## 6. Parity verification (run after any schema change)

```sql
-- tables & FKs
SELECT table_name FROM information_schema.tables WHERE table_schema='planning'
  EXCEPT SELECT table_name FROM information_schema.tables WHERE table_schema='pgs_app';
SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema='planning' AND constraint_type='FOREIGN KEY';
-- columns per table
SELECT t.table_name,
  (SELECT COUNT(*) FROM information_schema.columns c WHERE c.table_schema='planning' AND c.table_name=t.table_name),
  (SELECT COUNT(*) FROM information_schema.columns c WHERE c.table_schema='pgs_app' AND c.table_name=t.table_name)
FROM information_schema.tables t WHERE t.table_schema='planning' AND t.table_name <> 'users';
-- rows per table (compare COUNT(*) both DBs)
```

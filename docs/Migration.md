# Data Migration

Procedure for moving data from the legacy MySQL schema into the new schema, with verification and cutover controls. Executed in **Phase 2** (schema foundation) and **Phase 9** (final cutover).

---

## 1. Principles

1. **Never trust the old schema as documentation** — the live database is the source of truth.
2. **Verify by checksum, not by row count alone.**
3. **Migrate in frozen batches** — write activity paused during the final window.
4. **Backward compatibility**: during dual-run, both apps write to the same DB; new schema must be a superset that legacy code can still use (or writes are routed through an adapter).

---

## 2. Phase 2: foundation migration (non-destructive)

Goal: build the new schema and prove it maps 1:1. **No production writes.**

1. **Export**: `mysqldump --no-data` (live) → schema audit source.
2. **Inventory**: for every legacy table, record rows, columns, quirks (ENUM values, nullable oddities, duplicate rows) → `docs/DataModel.md`.
3. **Write migrations** reproducing the schema with redesigns (DataModel §5).
4. **Map tables**: legacy→new table/column mapping file (committed, machine-readable, e.g. `docs/migrations/mapping.yml`).
5. **Copy data to staging**: scripts (not hand queries) with chunked reads/writes; run against staging only.
6. **Verify**:
   - Row counts equal per table.
   - Checksums: `SELECT SHA2(CONCAT_WS('|', col1, col2, ...), 256)` aggregate per table, legacy vs new.
   - Sample spot checks: 50 random rows per domain table, manual diff.
   - Validation queries (FK orphan check, NOT NULL violations, enum drift).
7. **Sign off** with the mapping doc; store the checksum report (`docs/migrations/checksums_phase2.json`).

---

## 3. Phase 9: final cutover

1. **Freeze**: feature freeze 1 week prior; only bug fixes.
2. **Full backup**: spatie backup (DB + uploads) + checksums; snapshot uploads dir.
3. **Maintenance banner**: read-only mode via queue/maintenance mode.
4. **Final sync**: re-run migration scripts (incremental delta since Phase 2), then:
   - Row count parity per table
   - Checksums (same query as Phase 2)
   - FK/orphan validation
5. **Switch routing** to Laravel entry point (DNS/vhost/redirect map for bookmarks).
6. **Smoke tests** (automated + manual): login ×3 roles, one upload per module, notification flow, backup restore, dashboard KPIs.
7. **Watch window**: 2 weeks parallel (legacy read-only on internal IP, logs compared for anomalies).
8. **Rollback trigger**: any of — data divergence, 5xx spike, workflow regression not fixed in 2h. Rollback = restore vhost + point storage at pre-cutover snapshot (documented in `Operations.md`).

---

## 4. Uploads directory migration

| Legacy | New |
|---|---|
| Files on `uploads/` referenced by stored relative path | Copy to new private disk preserving path structure; store mapping legacy_path → storage_key |
| Filenames from user input | New uploads renamed UUID; legacy files keep paths but are read-only |
| Orphan files (no DB row) | Quarantine list → admin review → archive |
| Duplicate names | Path mapping handles collisions; verify by file size + hash |

Verify: file count + total size parity; sample `sha256` of 100 files.

---

## 5. Rollback plan (Phase 9)

| Trigger | Action | Time |
|---|---|---|
| Data divergence detected | Stop writes, restore from pre-cutover backup, re-verify | ≤ 2 h |
| Critical 5xx spike | Rollback vhost to legacy, keep new app on staging for debugging | ≤ 30 min |
| Security incident | Full restore + audit (Security §8) | ≤ 4 h |
| Queue drain failure | Scale workers, pause banner, re-run migrations idempotently | ≤ 1 h |

Rollback rehearsals: twice in staging before cutover (Phase 9 task list).

---

## 6. Idempotency & safety rules for migration scripts

- Every script is **idempotent** (re-runnable without duplication): keyed by mapping file, tracked by `migration_batches` table (batch id, table, rows, status).
- Run in transactions per table; abort batch on first error with full log.
- Never transform data in-place on legacy — read-only source.
- Timeouts: chunk reads (e.g. 10k rows); long tables (uploads metadata, audit) streamed.
- Secret handling: migration scripts read creds from `.env`, never inline.

---

## 7. Artifacts (committed)

| Artifact | Location |
|---|---|
| Table/column mapping | `docs/migrations/mapping.yml` |
| Checksum report (Phase 2) | `docs/migrations/checksums_phase2.json` |
| Checksum report (cutover) | `docs/migrations/checksums_cutover.json` |
| Parity scripts | `tests/Parity/` (see Testing.md) |
| Migration batch scripts | `database/legacy-migrations/` |

---

## 8. Post-cutover verification cadence

- Hour 0–24: automated parity suite every 4h.
- Days 2–14: daily parity + log review; weekly backup restore test.
- After watch window: decommission legacy per Phase 9.

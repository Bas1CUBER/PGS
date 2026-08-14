# Phase 7 — Module Ports (Annexes & Sector Roadmaps)

**Goal**: Port the remaining feature surface — the 7 sector roadmap modules, annex/OPCR pages, survey, governance pages, and core-team page — consolidating their duplicated patterns into shared components.

**Effort**: 6–8 weeks · **Depends on**: Phase 6 · **Unblocks**: Phase 8

---

## 1. Scope

| Legacy area | Legacy files | Strategy |
|---|---|---|
| Sector modules | `culture/`, `collab/`, `training/`, `technology/`, `research/`, `revenue/`, `resilience/` roadmaps (12+ files) | Extract shared "yearly indicator table" + "event/notes list" + "trend chart" primitives; each module becomes a config-driven page |
| Annexes / OPCR | `annexb/d/e/h/j/k.php`, `OPCR.php`, `office_for_strategy_management.php`, `multi_sector_governance_system.php` | Converted to Blade/React documents with print-optimized CSS; data from models, not raw queries |
| Survey | `survey.php` | Form with validation + results views |
| Governance | `governance_culture*.php`, `governance_sharing*.php` | Standard CRUD + uploads pattern |
| Strategy content | `about_*.php` (charter, pathway, strategy map, user access) | Static content → content models or Markdown, rendered in shell |
| Misc | `pgs_core_team.php`, `impact_indicator*.php` | Port using Phase 5–6 patterns |

---

## 2. Task checklist

### 2.1 Pattern library (done for the shared sector shape)
- [x] `SectorModuleRegistry` — config-driven pillars (verified against live schema: all 7 main tables are `id, category, year, description`; progress `+month, status, remarks, updated_by`; collab/research have schedules)
- [x] `SectorModuleController` — index + show (indicators paginated, progress, schedule) + row/progress updates with audit
- [x] `Sectors/Index` + `Sectors/Show` pages (shadcn) + nav entry
- [x] Generic feature tests parameterized over the pattern (5 tests: list, show, unknown slug, row update, progress update)
- [x] Wide-table year-column components — **implemented as the generic `SectorDetailController` + `SectorDetails/Show` page** (one table-driven editor for all sector detail tables; replaces the originally-planned `YearlyIndicatorTable`/`EventList`/`TrendChartCard`/`DataEntryGrid` components — ADR note: simpler, still one pattern per module)
- [ ] `PrintDocument` layout for annex/OPCR — **still open** (blocked on static form assets, see §2.2)
- [ ] `ModuleCalculations` service for relapse-rate style computed indicators — **still open**

### 2.2 Ports still to run (remaining work)
- [x] Sector detail tables: training pct/tot personnel+events, resilience adverse events/notes/gvr, revenue hospital details/NTR, collab rr_*/qli_*, research outputs, client satisfaction, engagement, records retrieval — via `SectorDetailRegistry` (14 tables; impact scorecard lives in the Scorecard module via `ImpactScorecardController`, technology turnaround under the Technology sector module). Registry column lists re-verified against the live schema during hardening (fixed `resilience_adverse_events` label→category/type and `employee_records_retrieval` registry_no→staff_name drift).
- [x] Annexes B/D/E/H/J/K + OPCR — **implemented as `LegacyFormRegistry` + `LegacyForms/Show` and `LegacyForms/Opcr` pages**: Annex D/E read live from the OPCR target register (`performance_targets`); Annex B/H/J/K are documented workspace views (columns only) because the original static `forms/Annex *.html`/`.xlsx` artifacts were never tracked in git and were lost with the legacy tree — see the `source_note` on each registry entry. CSV download per annex; OPCR is admin-managed CRUD + CSV export.
- [x] Strategy content pages (about_*, pgs_core_team, governance pages, survey) — `ContentPageRegistry` + `Content/Show`, `SurveyController`, governance via `UploadModuleRegistry`
- [ ] Manual parity check per module: compare new UI vs legacy side-by-side with a focal user (no Playwright infra on LAN deployment)

---

## 3. Definition of Done / acceptance criteria

- [x] All 7 sector modules + annexes + survey functional in the new app — sectors, survey, OPCR and annex workspaces done (annex B/H/J/K are workspace views pending owner-provided source artifacts)
- [ ] Manual screenshot comparison per module (side-by-side, focal sign-off)
- [x] Generic module tests pass for every configured module (no per-module special-casing) — `SectorModuleTest`, `ContentModulesTest`, `UploadModuleTest` (8 configured upload modules), plus `SectorWorkflowTest`, `StrategyReviewModuleTest`, `OperationsReviewModuleTest`, `LegacyFormsTest` (190 tests total)
- [ ] Print output verified A4 for annex/OPCR documents — annexes are on-screen workspaces with CSV export; original print layouts blocked with §2.2
- [x] No legacy page remains linked from the new shell (nav is registry-driven; legacy URLs 301 via `LegacyRedirectMiddleware`)

---

## 4. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Wide tables (year-columns) make config ugly | Column config arrays shared between DB accessor and TS types — single source |
| Manual parity is subjective | Focal sign-off checklist per module; data equivalence over pixel equality |
| Module-specific quirks (relapse-rate calcs) | Port calculations into a `ModuleCalculations` service with unit tests ported from spreadsheet values |

---

## 5. Exit criteria

Full feature parity; the only legacy files left are dead code awaiting deletion (Phase 9).

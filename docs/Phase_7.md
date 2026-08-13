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
| Misc | `pgs_core_team.php`, `impact_indicator*.php`, `roadmap_*` dashboard widgets | Port using Phase 5–6 patterns |

---

## 2. Task checklist

### 2.1 Pattern library (done for the shared sector shape)
- [x] `SectorModuleRegistry` — config-driven pillars (verified against live schema: all 7 main tables are `id, category, year, description`; progress `+month, status, remarks, updated_by`; collab/research have schedules)
- [x] `SectorModuleController` — index + show (indicators paginated, progress, schedule) + row/progress updates with audit
- [x] `Sectors/Index` + `Sectors/Show` pages (shadcn) + nav entry
- [x] Generic feature tests parameterized over the pattern (5 tests: list, show, unknown slug, row update, progress update)
- [ ] `YearlyIndicatorTable` / `EventList` / `TrendChartCard` / `DataEntryGrid` components — **deferred** (wide-table modules land with Phase 7b)
- [ ] `PrintDocument` layout for annex/OPCR — **deferred (Phase 7b)**
- [ ] `ModuleCalculations` service for relapse-rate style computed indicators — **deferred (Phase 7b)**

### 2.2 Ports still to run (Phase 7b — next work package)
- [ ] Sector detail tables: training pct/tot personnel+events, resilience adverse events/notes/gvr, revenue hospital/ntr, collab rr_*/qli_*, research outputs, client satisfaction, engagement, impact scorecard, technology turnaround — via the YearlyIndicatorTable pattern
- [ ] Annexes B/D/E/H/J/K + OPCR print documents (Blade print layout + A4 CSS)
- [ ] Strategy content pages (about_*, pgs_core_team, governance pages, survey)
- [ ] Playwright screenshot parity per module (needs Playwright infra — Phase 8e)

---

## 3. Definition of Done / acceptance criteria

- [ ] All 7 sector modules + annexes + survey functional in new app
- [ ] Playwright screenshot parity within visual tolerance per module
- [ ] Generic module tests pass for every configured module (no per-module special-casing)
- [ ] Print output verified A4 for annex/OPCR documents
- [ ] No legacy page remains linked from the new shell

---

## 4. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Wide tables (year-columns) make config ugly | Column config arrays shared between DB accessor and TS types — single source |
| Screenshot parity fights CSS differences | Baseline tolerance; prioritize data equivalence over pixel equality |
| Module-specific quirks (relapse-rate calcs) | Port calculations into a `ModuleCalculations` service with unit tests ported from spreadsheet values |

---

## 5. Exit criteria

Full feature parity; the only legacy files left are dead code awaiting deletion (Phase 9).

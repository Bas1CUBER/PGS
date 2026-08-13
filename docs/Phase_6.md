# Phase 6 — Roadmaps & Deliverables (core business modules)

**Goal**: Port the heart of the system — roadmap builder, deliverables/upload lifecycle, communication plan, strategy reviews, resources, notices, gallery — onto Eloquent + React with the full quality bar.

**Effort**: 6–8 weeks · **Depends on**: Phases 3–5 · **Unblocks**: Phase 7

---

## 1. Scope

| Legacy file(s) | New module | Notes |
|---|---|---|
| `roadmap.php`, `roadmap_page_builder.php`, `roadmap_page_builder` blocks | Roadmaps module | titles → items → blocks; drag-reorder; per-item page content |
| `form.php`, `employee_form.php`, `deliverable_add_form.php`, `insert.php`, `update.php`, `get_deliverables.php`, `employee_upload.php` | Deliverables module | CRUD + uploads + status workflow (upload → approved/returned) |
| `communication_plan*.php` | Communication plan | roadmap template + uploads + status updates |
| `strategy_review*.php`, `strategy_refresh*.php`, `operations_review*.php` | Reviews module | form builder, drafts, PDF export, status workflow |
| `notice.php`, `add_notice.php`, `delete_notice.php` | Notices | CRUD + homepage listing |
| `resources.php`, `resources_view.php` | Resources | uploads + viewer |
| `gallery.php` | Gallery | albums + photos |
| `cascading_activities*.php` | Cascading activities | list + uploads |
| `notification_helper.php`, `notifications_api.php` | (Phase 3 service) | reused |

---

## 2. Task checklist (per module)

### Workflow engine (foundation)
- [x] `TransitionsWorkflowService` — generic status engine (from/to/actor/preconditions), row-locked transitions in transactions, audit entries per transition (`{table}.status_changed`); DI-bound per module via `AppServiceProvider`
- [x] `DeliverableStatus` enum (legacy values) + deliverables transition map (NotYetStarted → Ongoing → Accomplished; reopen admin/focal) — tested (allowed, denied, wrong actor, audit)

### Deliverables module (`p_deliverables`)
- [x] Controller CRUD + MOV upload (storage disk `local/deliverables`, whitelisted types ≤25MB), download with policy, delete removes file
- [x] Employee scoping on index; admin/focal see all; policy matrix tested
- [x] Status transitions via `POST /deliverables/{id}/status` (workflow engine) — tested
- [x] Audit on create/update/delete; feature tests (9)

### Roadmaps module (`roadmap_titles` / `roadmap_items` / `roadmap_page_blocks`)
- [x] Titles CRUD; items CRUD with legacy columns (`sub_letter` auto A/B/C, `sub_label`, `page_slug` via `Str::slug`, `has_builder_page`) + up/down reorder (transactional swap)
- [x] Blocks CRUD with JSON content (legacy enum types `heading|paragraph|table|dashboard_stat`) — tested
- [x] Page-access matrix enforced (`page.access:roadmaps`) — tested

### Notices module (`notices`)
- [x] CRUD with audit; admin/focal manage, all roles read — tested

### Not yet ported (next sessions, per module checklist above)
- [ ] Communication plan module
- [ ] Reviews module (strategy review / refresh / operations review + PDF)
- [ ] Resources module
- [ ] Gallery module
- [ ] Cascading activities module
- [ ] Notifications wiring per workflow transition (events → NotificationService)

---

## 3. Definition of Done / acceptance criteria

- [ ] Feature parity checklist per module signed off by a focal/admin user
- [ ] Workflow transitions tested: every allowed/denied transition
- [ ] Upload security tests: bad MIME, oversize, path traversal, duplicate names
- [ ] N+1-free (logged queries) — see [Optimization.md](./Optimization.md)
- [ ] Notifications fire once, deduplicated, verified against legacy counts

---

## 4. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Roadmap block content is semi-structured legacy JSON | Type-first migration: TS interface per block_type; converter with validation report |
| Uploads directory has years of files | Filename hashing on new uploads; legacy files referenced by stored path, never user input |
| Strategy review forms are complex | Form-builder schema versioned; drafts saved; migration of only active forms |

---

## 5. Exit criteria

Core modules live in the new app with tests. Remaining legacy surface: annex pages and sector modules → Phase 7.

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

1. **Business rules doc**: read the legacy file, write `docs/<Module>.md` — statuses, validations, edge cases (e.g. `uploaded_by` backfill, deadline enforcement).
2. **Controllers + Form Requests**: thin controllers, `Store/Update` requests, service layer for workflows.
3. **Policies**: employee uploads own deliverables; focal approves; admin manages all.
4. **Uploads**: `Storage` disks, MIME whitelist, size limits, virus scan hook, filename sanitization, chunking if >50 MB. Never trust client filename (see [Security.md](./Security.md)).
5. **UI**: pages via Inertia + shadcn components; uploads via `UploadDropzone`; statuses via `StatusBadge`; tables via `DataTable` wrapper.
6. **Notifications**: wire events (`UploadApproved`, `UploadReturned`, `TemplateUpdated`) → notification service.
7. **Tests**: feature tests for each workflow state transition + permission matrix; upload validation tests.
8. **PDF/export**: server-side (DomPDF/Browsershot) replacing `jspdf`/`html2canvas` browser hacks.

### Cross-cutting
- [ ] Roadmap page-builder blocks ported to a `Block` model with JSON content typed in TS
- [ ] Status workflow engine: single source of truth (enum + transitions map), tested
- [ ] Search/sort/pagination everywhere; no loading full tables
- [ ] Legacy endpoints stop being linked from new UI (dual-run mode: new UI in prod, legacy still reachable for UAT)

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

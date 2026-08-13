# Phase 9 — Cutover & Decommission

**Goal**: Move production traffic fully onto the new app, verify parity under real usage, then delete the legacy codebase and complete the handover.

**Effort**: 2 weeks · **Depends on**: Phase 8 · **Unblocks**: project completion

---

## 1. Objectives

1. Controlled cutover with rollback plan.
2. Data verification before and after the final migration window.
3. Deletion of all legacy scripts, templates, and dual-driver code.
4. Handover: runbooks, docs review, final KPIs.

---

## 2. Task checklist

### 2.1 Pre-cutover
- [ ] Freeze feature changes 1 week prior; only bug fixes
- [ ] Full backup (spatie) + checksums; snapshot of uploads directory
- [ ] Feature-parity sign-off checklist signed by admin, focal, and employee UAT users
- [ ] Playwright parity suite run on staging; zero blocking diffs
- [ ] Runbook written: cutover steps, rollback steps, contacts
- [ ] DNS/config switch rehearsed twice in staging

### 2.2 Cutover window
- [ ] Read-only maintenance banner (queue-based, not hardcoded)
- [ ] Final export → migrate (Phase 2 pipeline) → verify row counts + checksums
- [ ] Switch virtual host / routing to Laravel entry point
- [ ] Smoke tests: login (3 roles), dashboard KPIs, one upload per module, notifications, backup restore check

### 2.3 Decommission
- [ ] 2-week parallel watch: compare logs (legacy still reachable on internal IP only, read-only)
- [ ] After watch: delete `legacy/` tree, `uploads` legacy handlers, `db.php`, `config.php`, old `templates/`
- [ ] Grep-gate CI updated: `mysqli`, `require db.php`, `$_GET` in views → fail
- [ ] Remove leftover CDN links and `build_css.php`
- [ ] Archive legacy repo in git history tag (`legacy-before-cutover`) for reference

### 2.4 Handover
- [ ] README + architecture diagram updated; docs/* reviewed for drift
- [ ] Runbooks: deploy, backup/restore, on-call, error triage
- [ ] Final KPI report vs [Roadmap.md](./Roadmap.md) §6 targets
- [ ] Onboarding doc for new developers (skills matrix → [Skills.md](./Skills.md))
- [ ] Retrospective: what the port taught us → update docs/Consistency.md

---

## 3. Definition of Done / acceptance criteria

- [ ] Production fully on Laravel app; legacy endpoints 404
- [ ] Zero data-loss incidents; checksum report committed
- [ ] Rollback never required during watch window
- [ ] All docs files updated and consistent; single source of truth
- [ ] Coverage, PHPStan, Lighthouse, load-test KPIs at target

---

## 4. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Hidden dependency on a legacy script found post-cutover | Grep-gate + log watch catches references; rollback rehearsed |
| Users bookmarked old URLs | Redirect map (`.htaccess` → new routes) maintained for 6 months |
| Uploaded legacy files referenced by old paths | Storage adapter resolves legacy paths until watch ends |

---

## 5. Exit criteria

Legacy deleted. Project done. Celebrate, then **re-run Roadmap.md §6 KPIs quarterly** — 10/10 is maintained, not achieved once.

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
- [x] Legacy redirect map: `LegacyRedirectMiddleware` (301s old `.php` paths + `/PGS/` prefix bookmarks → new routes) + fallback route + tests
- [ ] Freeze feature changes 1 week prior; only bug fixes — **scheduling item**
- [ ] Full backup (spatie) + checksums; snapshot of uploads directory — tooling ready (Phase 5)
- [ ] Feature-parity sign-off checklist signed by admin, focal, and employee UAT users — **user sign-off required**
- [ ] Playwright parity suite run on staging — needs Playwright infra (Phase 8e)
- [ ] Runbook written: cutover steps, rollback steps, contacts — **documented in Operations.md; to be rehearsed**
- [ ] DNS/config switch rehearsed twice in staging — **scheduling item**

### 2.2 Cutover window
- [ ] Read-only maintenance banner — **implement with maintenance mode at cutover**
- [ ] Final export → migrate (Phase 2 pipeline) → verify row counts + checksums — scripts ready (Migration.md)
- [ ] Switch virtual host / routing to Laravel entry point — **scheduling item**
- [ ] Smoke tests: login (3 roles), dashboard KPIs, one upload per module, notifications, backup restore check

### 2.3 Decommission
- [ ] 2-week parallel watch: compare logs (legacy reachable on internal IP only, read-only)
- [ ] **After watch + user sign-off**: delete legacy tree (`*.php` at repo root, `db.php`, `config.php`, old `templates/`, `build_css.php`, CDN references) — **destructive; explicitly requires the owner's go-ahead**
- [ ] Grep-gate CI updated: `mysqli`, `require db.php`, `$_GET` in views → fail
- [ ] Archive legacy repo in git history tag (`legacy-before-cutover`) for reference

### 2.4 Handover
- [ ] README + architecture diagram updated; docs/* reviewed for drift
- [ ] Runbooks: deploy, backup/restore, on-call, error triage — documented in Operations.md
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

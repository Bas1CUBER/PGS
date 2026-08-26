# Next Initiatives — Post-Audit Roadmap

**Context:** 110/110 audit findings closed (`5e6524e` + `c8f63fe` — 221 tests, PHPStan 0, Pint/tsc/Vite clean). This document tracks the four follow-up initiatives that prevent regression and compound future velocity. Each initiative lists scope, concrete tasks, acceptance criteria, and sequencing.

---

## 1. Frontend Consistency System — *highest ROI*

**Problem:** Pages still diverge despite Pager + `lib/urls.ts` / `roles.ts` / `status.ts` shims. 10+ pages mix `useForm` vs `router.post` with hand-rolled state; status colors live in inline maps; `page-width.ts` duplicates `nav-config.ts`; `groupIcons` vs `paletteGroupIcons` duplicate.

**Objective:** One canonical pattern per concern, enforced by lint, so every new page is consistent without review overhead.

### Tasks

- [ ] **Form pattern — `useForm` everywhere**
  - Migrate `LegacyForms/Opcr.tsx`, `Scorecard/Index.tsx` value-commit path, `Roadmaps/Index.tsx` block editor, `Users/Create.tsx` import `fetch` → `router`/`useForm` so `errors`/`processing` flow uniformly.
  - Delete hand-rolled `XSRF-TOKEN` regex in `Users/Create.tsx:65-74` (axios/router handles it).
  - Acceptance: `grep -r "router\.post\|router\.put" resources/js` only in `Pager`-like infra; every mutation uses `useForm`.

- [ ] **Design tokens — single source**
  - Replace every inline status→class map (`progress-card.tsx:7`, `CommPlan/Index:43`, `Deliverables/Index:43`, `Uploads/status-badge:4`) with `lib/status.ts:statusClass`.
  - Consolidate `Backups/Index:32-39` vs `Uploads/format-bytes.ts` → `lib/format-bytes.ts` (keep GB tier).
  - Acceptance: `grep -r "bg-green-100\|bg-amber-100" resources/js --exclude="lib/status.ts"` → 0 hits.

- [ ] **Navigation/layout — single NAV_CONFIG**
  - Merge `page-width.ts` path lists + `authenticated-sidebar:groupIcons` + `command-palette-items:paletteGroupIcons` into one `NAV_CONFIG` exported from `nav-config.ts` that drives sidebar, palette, and page width.
  - Acceptance: deleting a route from `NAV_CONFIG` removes it from all three surfaces.

- [ ] **Enforcement**
  - Add ESLint rule (or `grep-gate` entry like `app/bin/grep-gate.sh`) banning hardcoded `"/sectors/`/`"/users/` strings outside `lib/urls.ts`.
  - Remove `axios` global from `bootstrap.ts` if no consumer remains, or document its single consumer.

**Effort:** 2–3 days mechanical · **Owner:** frontend · **Depends on:** nothing

---

## 2. Test Coverage That Protects You

**Problem:** Unit suite is still 1 test (`ExampleTest`). Pure logic (`WorkflowRegistry`, `UploadModuleService` transition graph, `DashboardService` unions, `CsvFormulaGuard`) is only exercised indirectly through slow feature tests. No E2E.

**Objective:** Fast, deterministic unit coverage + one smoke E2E that would have caught the 4 broken uploads.

### Tasks

- [ ] **Unit suite (Pest `tests/Unit`)**
  - `WorkflowRegistryTest` — every `transitions[FROM]` → `TO` + actor `admin|focal|*` matrix.
  - `UploadModuleServiceTest` — graph from `STATUS_TRANSITIONS`, `initialStatus` per slug, duplicate-guard window.
  - `DashboardServiceTest` — `pendingApprovalUnionQuery` excludes non-reviewable tables; `recentUploads` covers all 8 registry tables.
  - `CsvFormulaGuardTest` — `=cmd|+cmd|-cmd|@cmd` neutralization.
  - Acceptance: `tests/Unit` ≥ 30 cases, < 2 s, run on every push.

- [ ] **One Playwright smoke (optional but high-value)**
  - `e2e/upload-approve-export.spec.ts`: login → upload to `resources` → focal approves → export PDF → assert 200.
  - Acceptance: would have failed on the pre-fix discarded-FormData bug.

**Effort:** 1–1.5 days · **Owner:** QA/backend · **Depends on:** (1) for stable selectors

---

## 3. Security Follow-Through — *30 min each*

**Problem:** Three former owner-decision items are mitigated in code but need human action to fully close.

### Tasks

- [ ] **#6 — Purge `planning.sql` blob from history**
  ```bash
  pip install git-filter-repo
  git filter-repo --path planning.sql --path app/planning.sql --invert-paths --force
  git push --force --all && git push --force --tags
  # then ask GitHub Support to GC the cached view (if repo was ever public)
  ```
  Credential rotation already done via #15 guard — this erases the blob itself.

- [ ] **#14 — Cloudflared decision**
  - If **permanent**: keep `bootstrap/app.php:trustProxies(at:'127.0.0.1')` (already committed), flip `SESSION_SECURE_COOKIE=true` behind the tunnel, add tunnel allowlist, keep `docs/Security.md:9` as is.
  - If **temporary**: revert `trustProxies` block. Either way, commit the decision so the diff is not "uncommitted".

- [ ] **#16 — Gmail app password**
  - File already neutralized (mailer now `array` via `phpunit.xml`); **still revoke** the old `kroq…` app password in Google Account → Security → App passwords.

**Effort:** < 1 hour total · **Owner:** repo/infra owner · **Depends on:** nothing

---

## 4. Performance & Observability

**Problem:** Bundle budget is wired but not enforced; one hot path does an extra DB hit per request.

### Tasks

- [ ] **Enforce bundle budget in CI**
  - Add job to `.github/workflows/ci.yml` after `npm run build`:
    ```yaml
    - run: node bundle-budget.mjs  # already wired at package.json:12, limit 250 kB gzip
    ```
  - Acceptance: PR that grows `app-C1bBohWE.js` past 250 kB gzip fails CI.

- [ ] **Cache the Inertia unread count**
  - `app/Http/Middleware/HandleInertiaRequests.php:44` currently calls `NotificationService::unreadCount` uncached on every page. Reuse the 30 s `CacheInvalidationService::remember('notification', "unread:{$user->id}", ..., 30)` that `NotificationController` already uses.
  - Acceptance: one fewer `COUNT(*)` per Inertia render; no behavior change.

- [ ] **(Optional) Log rotation already fixed** (`run-scheduler.ps1`/`run-worker.ps1` now rotate at 10 MB); verify on next deploy.

**Effort:** 0.5 day · **Owner:** infra/frontend · **Depends on:** nothing

---

## Sequencing

```
Week 1:  3 (security, <1h) ─┐
         4 (perf, 0.5d)    ─┤─ can run in parallel
Week 1-2: 1 (consistency)   ─┘  then  2 (tests, benefits from stable selectors)
```

**Gates after each initiative:** `composer analyse` (PHPStan 0) + `composer lint` + `php artisan test` + `npm run build` + `npx tsc --noEmit` — same gates that held the audit fixes green.

---

## Definition of Done

- [ ] All checkboxes above checked.
- [ ] `docs/AUDIT_FINDINGS.md` shows `110 Fixed / 0 Open` with no new findings introduced.
- [ ] CI enforces bundle budget; unit suite ≥ 30 cases.
- [ ] `git log --all -- planning.sql` returns nothing (history purged) or is explicitly accepted in writing.

*Created 2026-08-26 — next step: pick (1) or (3); both are unblocked.*

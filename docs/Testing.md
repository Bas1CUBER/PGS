# Testing

Test strategy for the PGS app: what we test, with what, and how CI enforces it. The legacy app has ~0% coverage — this standard exists to keep the new one honest.

---

## 1. Test pyramid

```
        ┌──────────┐
        │  E2E     │  Playwright: critical user journeys (login, upload, approve, backup)
        │  few     │
       ─┼──────────┼──
      ┌─┴──────────┴─┐
      │   Feature    │  Pest: every route × role matrix, workflows, uploads, auth
      │  ~150-250    │
     ─┼──────────────┼──
    ┌─┴──────────────┴─┐
    │      Unit        │  Models (casts/scopes/transitions), Services, Form Requests,
    │   many, fast     │  formatters, enums, TS utils (Vitest)
    └──────────────────┘
```

Ratio target: ~70% unit / 20% feature / 10% E2E. Coverage gate: **≥ 85%** on `app/Services`, `app/Models`, `app/Http/Requests`, `app/Modules` — enforced by CI (fail below threshold, ratcheting only upward).

## 2. Tooling

| Layer | Tool | Config |
|---|---|---|
| PHP tests | Pest 3.x (PHPUnit 11 under the hood) | `phpunit.xml`; parallel; `RefreshDatabase` |
| Coverage | PHPUnit `--coverage` | `phpunit.coverage.xml`; CI artifact + badge |
| JS unit | Vitest + React Testing Library | `vitest.config.ts`; `jsdom` |
| E2E | Playwright | `tests/e2e/`; 3 projects (chromium desktop/mobile, webkit) |
| N+1 guard | Laravel `assertNoNPlusOneQueries` | every index/show feature test |
| A11y | axe-core (Playwright integration) | key routes |
| Screenshot parity | Playwright diff (legacy vs new) | Phase 6–7 modules |

## 3. Test database strategy

- `DB_DATABASE=pgs_test` — migrations fresh per suite run (`RefreshDatabase`); `--seed` only for explicit seed-dependent tests.
- Factories for: `User` (role variants), `Deliverable`, `RoadmapTitle/Item/Block`, `Notification`, `DeadlineControl`, `Notice`, module indicator rows.
- Upload fixtures in `tests/Fixtures/` (small valid/invalid files, image with EXIF).
- No network in tests: HTTP fakes (`Http::fake`), storage fakes (`Storage::fake`), queue fakes (`Queue::fake`).

## 4. What every module's feature suite must cover

1. **CRUD**: index (paged, filtered, authorized), create/update/delete happy paths.
2. **Permission matrix**: each route × `guest | employee | focal | admin` → expected 302/403/200. Generated via a shared data provider (one test per route, roles table-driven).
3. **Workflows**: all allowed/denied transitions (Workflows §8 checklist).
4. **Uploads**: MIME/extension whitelist, oversize, path traversal, duplicate names, quarantine path, signed URL expiry.
5. **Deadlines**: closed-window denials, admin bypass audit.
6. **Notifications**: created once, dedup, mark-read, unread counts.
7. **Audit**: every admin mutation writes a row with before/after.
8. **Validation**: Form Request rules — required, format, length, boundary values.

## 5. Critical E2E journeys (Playwright)

| Journey | Steps |
|---|---|
| Employee upload → focal approve | login(emp) → upload → login(focal) → approve → badge/notification visible |
| Return loop | focal returns with reason → employee resubmits |
| Admin user lifecycle | create user → set access matrix → toggle → audit view |
| Backup/restore | create backup → download → restore → verify |
| Deadline enforcement | close window → upload denied with message → admin extends |
| Print (annex/OPCR) | open → print PDF → text layer present, sidebar hidden |

E2E run on staging against seeded data; flaky tests are disabled only with a logged issue and owner.

## 6. CI integration

- PR pipeline: unit+feature (parallel, MySQL service) → coverage gate → Vitest → lint (Pint, ESLint, Prettier, PHPStan max) → Playwright (staging preview) → audits (composer/npm) → bundle budget.
- Nightly: full Playwright suite + axe scans + k6 smoke.
- Weekly: OWASP ZAP baseline, screenshot parity suite.
- Quarantine rule: failing test blocks merge; no "test skip due to time" without `@todo` + issue + owner.

## 7. Writing tests — conventions

- Arrange–Act–Assert with blank-line separation; Pest `describe`/`test` naming describes behavior ("it denies returning without a reason").
- One assertion theme per test unless validating a whole workflow step.
- Factory states for domain conditions: `Deliverable::factory()->uploaded()->to(User::factory()->focal())`.
- Assert on outcomes (status, notification row, audit row), not implementation details.
- Golden-value tests for calculations (relapse rates, scorecard values) ported from spreadsheet-verified numbers.

## 8. Coverage review cadence

- Every phase exit: coverage report committed to `docs/Testing.md` appendix (current: **0% — target ≥ 85% by Phase 8 exit**).
- Quarterly: prune dead tests, revisit thresholds, review E2E stability.

## Appendix — coverage history

| Date | Phase | Services | Models | Requests | Overall |
|---|---|---|---|---|---|
| Aug 2026 | Start | 0% | 0% | 0% | 0% |
| (fill at phase exits) | | | | | |

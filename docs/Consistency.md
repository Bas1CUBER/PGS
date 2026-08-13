# Consistency

One team, one style. Conventions that keep 178 legacy files' worth of chaos from creeping into the new codebase.

---

## 1. Coding standards (enforced, not aspirational)

| Domain | Tool | Rules |
|---|---|---|
| PHP | Pint (Laravel preset, PSR-12) | Formatting is automated; CI `--test` |
| PHP quality | PHPStan `--level=max` + strict + Larastan | 0 errors; `@phpstan-ignore` only with reason |
| PHP tests | Pest/PHPUnit | Feature-first; coverage ≥ 85% on services/models |
| TypeScript | ESLint (typescript-eslint recommended-type-checked + react-hooks + react-refresh) | 0 warnings in CI |
| TS formatting | Prettier | No style debates |
| CSS | Tailwind v4 tokens; ESLint bans hardcoded colors | See [Design.md](./Design.md) |
| SQL | CI grep-gate | Bans `mysqli`, raw interpolated queries, `CREATE/ALTER` in app code |
| Assets | Vite | All assets local; **CDN ban** |

- **Ratchet rule**: if a gate gets loosened, it gets a dated entry in this file and an owner + re-tighten date. Loosening without an entry is a bug.

---

## 2. Naming conventions

| What | Convention | Example |
|---|---|---|
| PHP namespaces | `App\Modules\<Module>\<Layer>` | `App\Modules\Deliverables\Services` |
| Controllers | Plural noun | `RoadmapsController` |
| Services | VerbNounService | `UploadWorkflowService` |
| Form Requests | `Store\|Update<Thing>Request` | `StoreDeliverableRequest` |
| Migrations | `create_<table>_table` / `add_<col>_to_<table>` | `create_deliverables_table` |
| Routes | kebab-case URLs, `resource` by default | `/users`, `route('users.index')` |
| React files | camelCase components, PascalCase files | `users/index.tsx` |
| React components | PascalCase | `<UploadDropzone>` |
| Enums | PascalCase, singular | `DeliverableStatus::Approved` |
| Models/tables | Singular model, plural table | `Deliverable` / `deliverables` |
| DB columns | snake_case | `uploaded_at` |
| TS domain types | PascalCase, mirrors enums | `type DeliverableStatus` |
| CSS classes | Tailwind utilities + shadcn component classes only | — |

---

## 3. Git workflow

- **Branching**: `main` (always deployable) + short-lived branches `feat/<slug>`, `fix/<slug>`, `docs/<slug>`.
- **Commits**: conventional — `feat:`, `fix:`, `test:`, `docs:`, `refactor:`, `chore:`, `perf:`, `security:`. One logical change per commit.
- **PR rules**: ≤ 400 lines of diff (split otherwise); description template (what/why/how-tested); review required; CI green mandatory; screenshots for UI PRs.
- **No commits to `main`** except release merges and docs.
- **Versioning**: semantic; tags; release notes generated from conventional commits.

## 4. Code review checklist (every PR)

- [ ] Meets Definition of Done of its phase ([Roadmap.md](./Roadmap.md) §4)
- [ ] Tests cover new behavior (not just happy path) — permission matrix where applicable
- [ ] No banned patterns (grep-gate clean)
- [ ] No debug output, `dd()`, `dump()`, console.log
- [ ] No secrets, no hardcoded URLs/credentials
- [ ] UI: states (loading/empty/error/success) + a11y (keyboard, labels)
- [ ] Docs updated if behavior/conventions change

---

## 5. Error & message tone

- User-facing errors: specific + actionable + non-technical ("The file is larger than 25 MB. Split it or contact the admin.") — never "Internal error occurred".
- Logs: structured context; no sensitive data.
- Translations: English only for now; all user-facing strings through a single strings module (Laravel `lang/` + TS constants) so future i18n is a config change, not a refactor.

---

## 6. Data & time consistency

- All timestamps UTC stored, `Asia/Manila` displayed; single date/time formatter used everywhere (`lib/utils.ts`, tested).
- Amounts: `₱` + thousands separators, 2 decimals; weights as decimals.
- Status vocabulary single-sourced: `uploaded → approved/returned` etc. — same enum in PHP and TS; badge colors from the enum mapping only.

## 7. Doc consistency

- This docs set is the source of truth; code comments explain **why**, not **what**.
- Every phase PR may touch its phase file + the affected standards file in the same PR.
- `docs/Consistency.md` gets the "rules we broke" log: when a convention is violated knowingly, log it here with date, PR, reason, remediation.

---

## 8. "Rules we broke" log

| Date | Rule | PR/context | Why | Remediation |
|---|---|---|---|---|
| *(none yet — this is the standard to keep)* | | | | |

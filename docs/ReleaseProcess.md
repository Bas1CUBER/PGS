# Release Process

Versioning, release choreography, and change management for PGS. Keeps deploys boring and auditable. Companion to [CI-CD.md](./CI-CD.md) (pipelines) and [Operations.md](./Operations.md) (runbooks).

---

## 1. Versioning

- **Semantic**: `MAJOR.MINOR.PATCH` — MAJOR = breaking (schema/destructives/UX overhauls), MINOR = features, PATCH = fixes.
- Pre-release suffixes for phase milestones: `v0.5.0-alpha.1` (staging builds), `v1.0.0` at full cutover (Phase 9).
- Tags on `main` only; tag = deployable artifact reference; artifact + DB dump timestamped together (Operations §3).

## 2. Release types

| Type | Trigger | Process | Version bump |
|---|---|---|---|
| Hotfix | Sev 1/2 in prod | `hotfix/*` from main → minimal PR → same gates → tag | PATCH |
| Patch | Bugfix batch | standard PR flow | PATCH |
| Feature | Phase milestone | phase exit criteria met → release candidate (RC) on staging 1 week | MINOR |
| Major | Framework/destructive change | RC on staging 2+ weeks, UAT sign-off | MAJOR |

## 3. Release checklist (per release)

1. [ ] Branch `release/vX.Y.Z` from `main`; freeze features (bug fixes only)
2. [ ] Full CI + nightly suites green on RC
3. [ ] Staging: parity suite (Phases 5–7 modules), UAT sign-off from focal/admin, load smoke
4. [ ] Migration review: additive? destructive? two-phase plan confirmed (CI-CD §5)
5. [ ] Backup + restore drill passed
6. [ ] CHANGELOG generated from conventional commits (auto via CI); docs touched if behavior changed
7. [ ] Tag `vX.Y.Z` → deploy pipeline (manual approval in prod)
8. [ ] Post-deploy: `/up`, smoke script (login ×3, one upload, one export), log check
9. [ ] Announce (channel): what changed, what to watch for, rollback contact

## 4. Change management

- **CHANGELOG.md**: keep-a-changelog format, generated per release; user-visible changes in plain language ("Focals can now return uploads with a reason").
- **Breaking-change notice**: communicated ≥ 1 release ahead when feasible (deprecation warnings in UI for removed features).
- **Rollback protocol**: Operations §4 — previous artifact redeploy; DB rollback only per migration plan.

## 5. Definitions

| Term | Meaning |
|---|---|
| Release candidate | Artifact deployed to staging with full gates green, pending UAT |
| Frozen | No feature merges into the release branch |
| Cutover release | `v1.0.0`: legacy decommissioned (Phase 9) |
| Maintenance mode | `php artisan down` with schedule + retry UI (not hardcoded banner) |

## 6. Cadence

- Patches: as needed (hotfix < 24 h for Sev 1/2).
- Minor: per phase exit (target every 3–6 weeks).
- Major: on framework upgrades (planned ADR + dedicated release plan).
- No Friday-afternoon deploys for majors; deploy windows: Tue–Thu, 09:00–16:00 PH time.

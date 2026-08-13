# Contributing to PGS

Guidelines for working in the PGS repository (`app/` = new Laravel application, repo root = legacy code being replaced).

## Workflow

1. Branch from `main`: `feat/<slug>`, `fix/<slug>`, `docs/<slug>`.
2. One logical change per commit — Conventional Commits (`feat:`, `fix:`, `test:`, `docs:`, `refactor:`, `chore:`, `perf:`, `security:`).
3. Open a PR (≤ 400 lines of diff; split otherwise) with the PR template filled: what / why / how tested / screenshots for UI.
4. CI must be green: tests, PHPStan max, Pint, audits, build.
5. Review required before merge; never push directly to `main`.

## Definition of done (every change)

- [ ] `php artisan test` green locally
- [ ] `composer analyse` — 0 errors at level max
- [ ] `composer lint` — Pint clean
- [ ] New behavior covered by Pest tests (happy path + permission matrix where applicable)
- [ ] No banned patterns: raw SQL interpolation, migrations-in-app-code, `mysqli`, debug output (`dd`, `dump`, `console.log`)
- [ ] Docs updated if behavior or conventions change (`docs/`)
- [ ] No secrets or hardcoded URLs

## Conventions summary

- PHP: strict types, PSR-12 (Pint), Eloquent only, Form Requests + Services + Policies.
- Git: Conventional Commits, `main` protected.
- Full standards: `docs/Consistency.md`, `docs/Backend.md`, `docs/Architecture.md`.

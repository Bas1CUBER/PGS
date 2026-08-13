<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="Laravel Logo">
</p>

# PGS — Performance Governance System (TRC DOH)

Modernized application for the PGS platform: Laravel 12 + React (Phase 4+) + MySQL, replacing the legacy procedural PHP codebase.

> This is the **new application** (`app/`). The legacy codebase lives at the repository root and is being migrated per the phased roadmap in [`docs/Roadmap.md`](../docs/Roadmap.md).

## Quickstart (XAMPP native — no Docker)

```bash
composer install
cp .env.example .env            # edit DB_* for your MySQL (DB: pgs_app)
php artisan key:generate
php artisan migrate
npm install
npm run dev                     # Vite dev server
# app at http://127.0.0.1:8082 (Apache vhost) or `php artisan serve`
```

## Quality gates (CI-enforced)

```bash
composer analyse    # PHPStan level max (app/ + routes/) — 0 errors
composer lint       # Pint formatting
php artisan test    # Pest suite
composer audit && npm audit
```

## Documentation

- **Roadmap & phases**: [`docs/Roadmap.md`](../docs/Roadmap.md), [`docs/Phase_1.md`](../docs/Phase_1.md) → `Phase_9.md`
- **Standards**: [`docs/Architecture.md`](../docs/Architecture.md), [`docs/Backend.md`](../docs/Backend.md), [`docs/Frontend.md`](../docs/Frontend.md), [`docs/Consistency.md`](../docs/Consistency.md)
- **Ops**: [`docs/CI-CD.md`](../docs/CI-CD.md), [`docs/Operations.md`](../docs/Operations.md)
- Full index: [`docs/README.md`](../docs/README.md)

## Repository layout

```
app/   ← this Laravel application
docs/  ← project documentation set
*.php  ← legacy codebase (being migrated; do not modify)
```

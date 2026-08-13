# AGENTS.md — PGS App (Laravel)

Guidance for AI coding agents and new developers working in `app/` (the new Laravel application replacing the legacy procedural codebase in the repo root).

## Commands

```bash
composer install            # PHP dependencies
npm ci                      # frontend dependencies
npm run dev                 # Vite dev server (hot reload)
php artisan test            # Pest/PHPUnit suite
composer analyse            # PHPStan level max (app/ + routes/)
composer lint               # Pint formatting check
composer lint:fix           # Pint auto-fix
php artisan migrate         # run migrations (DB: MySQL `pgs_app`)
npm run build               # production assets
```

## Architecture & conventions (read before coding)

- **Read these first**: `../docs/Architecture.md`, `../docs/Backend.md`, `../docs/Consistency.md`, `../docs/Phase_1.md`.
- Laravel 12 + PHP 8.4 (`declare(strict_types=1)` everywhere), Inertia.js + React planned from Phase 4.
- Controllers are thin: validation in Form Requests, logic in Services, data via Eloquent.
- **Banned**: raw SQL string interpolation, `CREATE/ALTER TABLE` in app code (migrations only), `mysqli`, global state, CDN assets.
- Status changes only through `TransitionsWorkflowService` (Phase 3+).
- Tests: Pest in `tests/Feature`; every route needs its role-matrix test.

## Quality gates (CI enforces these — never merge red)

1. `composer analyse` — PHPStan level max, 0 errors
2. `composer lint` — Pint clean
3. `php artisan test` — green
4. `composer audit` + `npm audit` — clean
5. `npm run build` — succeeds

## Environment

- Local: XAMPP PHP 8.2 + MariaDB on `127.0.0.1:3306`, DB `pgs_app` (root, no password).
- App URL: `http://127.0.0.1:8082` (Apache vhost `pgs.app` in `C:/xampp/apache/conf/extra/httpd-vhosts.conf` — requires Apache restart to take effect; bound to `0.0.0.0` so LAN peers use `http://<server-LAN-IP>:8082`).
- Tests run on MySQL `pgs_test` (phpunit.xml) — mirrors the MariaDB/MySQL-specific legacy migrations.
- **No Redis / no Docker / no external services**: cache, sessions, and queues use the `database` driver; observability is Laravel logs + audit log (docs/TechStack.md §1b).
- No Docker on this machine — native XAMPP stack only.

## Repository layout

```
app/            ← this Laravel application
docs/           ← full project documentation set (../docs)
*.php, *.sql    ← legacy codebase (being migrated, do not modify)
```

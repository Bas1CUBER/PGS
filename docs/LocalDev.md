# Local Development

Getting a working PGS dev environment on a new machine — the "README quickstart" for engineers.

> **This machine (TRC DOH dev box):** native XAMPP stack only — **Docker is not used**. Apache service must not be restarted without the machine owner's go-ahead; use `php artisan serve` for the new app until the `pgs.app` vhost is activated.

---

## 1. Prerequisites (native XAMPP path — blessed on this machine)

| Tool | Version | Notes |
|---|---|---|
| XAMPP | current (PHP 8.2, MariaDB 10.4, Apache) | PHP must be on PATH or use `C:\xampp\php\php.exe` |
| Composer | 2.x | |
| Node | 22 LTS+ | npm bundled |

## 2. Native setup (blessed on this machine)

```bash
cd app
composer install
npm ci
cp .env.example .env
# edit DB_* to local MariaDB (DB: pgs_app, user root)
php artisan key:generate
php artisan migrate
php artisan serve          # dev server — http://127.0.0.1:8000 (or 8082 vhost when activated)
npm run dev                # Vite hot reload (second terminal)
```

Requires PHP 8.2+ (XAMPP) with `pdo_mysql`, `gd`, `intl`, `zip`, `exif` extensions; MariaDB running.

> Apache vhost `pgs.app` on `127.0.0.1:8082` is written to `httpd-vhosts.conf` but dormant — it activates only after an Apache restart (coordinated with the machine owner). Until then use `php artisan serve`.

## 3. Docker (documented for reference only — NOT used on this machine)

```bash
git clone <repo> pgs && cd pgs/app
cp .env.example .env
docker compose up -d                        # app, nginx, mysql, redis, mailpit, horizon, scheduler
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm ci && docker compose exec app npm run dev
# open http://localhost:8080  (mailpit UI: http://localhost:8025)
```

- `docker compose exec app php artisan test` — run the suite.
- `docker compose exec app php artisan horizon` already runs via service.
- Hot reload: `npm run dev`; production assets: `npm run build`.

## 4. Day-to-day commands

| Task | Command |
|---|---|
| Run tests | `php artisan test` (or `composer test`) |
| Coverage | `php artisan test --coverage` |
| Lint PHP | `vendor/bin/pint --test` / `vendor/bin/pint` (fix) |
| Static analysis | `vendor/bin/phpstan analyse --level=max` |
| JS lint | `npm run lint` / `npm run lint:fix` |
| Type check | `npm run types` |
| E2E | `npx playwright test` (staging URL via `PLAYWRIGHT_BASE_URL`) |
| Migrate fresh | `php artisan migrate:fresh --seed` (dev only!) |
| Queue work | `php artisan queue:work` (if not using horizon service) |
| Debug UI | http://localhost:8080/telescope (non-prod) |

## 5. Dev fixtures

- `--seed` creates: roles, demo users (`admin@trcdoh.ph / focal@… / employee@…` — passwords in `Database/Seeders`, dev only), sample roadmaps, deliverables, notifications, deadlines.
- Storage: `storage/app/private/uploads` gitignored; `Storage::fake` in tests.

## 6. Common problems

| Symptom | Fix |
|---|---|
| `Connection refused` on 3306 | MySQL container not ready: `docker compose restart mysql`; wait for health |
| 419 page expired | Clear browser cookies; restart `npm run dev` (XSRF stale) |
| Vite not found asset | `npm ci && npm run build` or keep `npm run dev` running |
| Migrations failed | `docker compose exec app php artisan migrate:fresh --seed` |
| Mail not sending | Mailpit at :1025; check `MAIL_HOST=mailpit` in `.env` |
| Slow first page | `php artisan optimize` (caches config/routes) |
| Permission errors (native) | `storage/` + `bootstrap/cache/` writable: `chmod -R 775` |

## 7. Onboarding checklist (first PR)

- [ ] Repo clone + Docker up + migrate+seed works
- [ ] Can log in as all three roles
- [ ] `php artisan test` green locally
- [ ] CI green on first tiny PR (Phase 1 gates)
- [ ] Read README → Glossary → Consistency before writing code

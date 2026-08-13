# Local Development

Getting a working PGS dev environment on a new machine — the "README quickstart" for engineers. Docker is the blessed path; a native fallback is documented for machines where Docker is unavailable.

---

## 1. Prerequisites

| Tool | Version | Notes |
|---|---|---|
| Git | ≥ 2.40 | |
| Docker Desktop (or engine + compose) | ≥ 24 | Blessed path |
| PHP CLI | 8.4 (native fallback only) | not needed with Docker |
| Composer | 2.x (native fallback only) | |
| Node | 22 LTS (native fallback only) | |
| MySQL client | any (native fallback only) | |

## 2. Blessed path (Docker)

```bash
git clone <repo> pgs && cd pgs/app          # new Laravel app directory
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

## 3. Native fallback (no Docker)

```bash
composer install
npm ci
cp .env.example .env
# edit DB_* to local MySQL; MAIL_MAILER=smtp MAIL_HOST=localhost MAIL_PORT=1025
php artisan key:generate
php artisan migrate --seed
npm run dev
```

Requires local PHP 8.4 with `pdo_mysql, redis, gd, intl, zip, exif` extensions; `redis-server` running; `mailpit` (or disable mail in dev).

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

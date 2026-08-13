# Environment Configuration

`.env` reference, environments, and Docker services. Secrets never live in the repo — `ENV.example`-style values here are placeholders only.

---

## 1. Environments

| Env | Purpose | Data | Key differences |
|---|---|---|---|
| `local` | Developer machine | Seeded dev data | `APP_DEBUG=true`, Telescope on, Mailpit, no TLS |
| `testing` | CI | Fresh migrated + seeded per run | `DB_CONNECTION=mysql` to `pgs_test`, in-memory queue |
| `staging` | Parity, E2E, UAT | Masked production copy (Phase 2) | Realistic data, Sentinel reports, debug off |
| `production` | Live | Real | Debug off, HTTPS only, Sentry on, CSP enforced |

## 2. `.env.example` (reference)

```env
APP_NAME="PGS"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8080
APP_TIMEZONE=Asia/Manila
APP_LOCALE=en
APP_CIPHER=AES-256-CBC

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pgs
DB_USERNAME=pgs_app
DB_PASSWORD=
DB_SCHEMA_CHARSET=utf8mb4

SESSION_DRIVER=redis
SESSION_LIFETIME=480
SESSION_SECURE_COOKIE=false

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS="pgs@example.ph"
MAIL_FROM_NAME="${APP_NAME}"

FILESYSTEM_DISK=local
PRIVATE_DISK=uploads
BACKUP_DISK=s3          # or 'local' in dev
BACKUP_RETENTION_DAILY=14
BACKUP_RETENTION_WEEKLY=8
BACKUP_RETENTION_MONTHLY=12

SENTRY_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.25

UPLOAD_MAX_SIZE_MB=25
UPLOAD_WHITELIST="pdf,docx,xlsx,pptx,jpg,jpeg,png,zip"
VIRUS_SCAN_ENABLED=true
CLAMAV_HOST=clamav

TOTP_ENABLED=true
RATE_LIMIT_LOGIN=5
RATE_LIMIT_UPLOAD=30

FEATURE_LEGACY_REDIRECT=true   # dual-run bookmark redirect map
```

- `DB_USERNAME`: least-privilege app user (SELECT/INSERT/UPDATE/DELETE + migrations user separate during deploys) — never root.
- `APP_KEY`: `php artisan key:generate`; rotated on suspected exposure.
- `SESSION_SECURE_COOKIE=true` in staging/prod.

## 3. Docker services (docker-compose.yml)

| Service | Image (pinned) | Ports (host) | Volumes |
|---|---|---|---|
| `app` | `php:8.4-fpm-alpine` (custom Dockerfile: extensions pdo_mysql, redis, gd, intl, zip, exif) | — | app code, uploads, storage |
| `nginx` | `nginx:1.27-alpine` | 8080:80 | app code, public/build |
| `mysql` | `mysql:8.0` (or `mariadb:10.11`) | 3306 | `db_data` |
| `redis` | `redis:7.4-alpine` | 6379 | — |
| `mailpit` | `axllent/mailpit:latest` | 8025 (UI), 1025 (SMTP) | — |
| `horizon` | same image as `app` | — | — (workers) |
| `scheduler` | same image as `app` | — | — (cron) |
| `clamav` | `clamav/clamav:stable` | — | quarantine dir |

`docker compose up -d` boots everything; `compose.override.yml` optional per-developer (ports, debug tooling).

## 4. Config vs env

- Laravel config files read env only; no `env()` calls outside `config/`.
- New settings: add to `config/pgs.php` (a single app-config file) + document here + `.env.example` in the same PR.
- Legacy `config.php` constants (BASE_URL, DB_*, UPLOAD_DIR, ITEMS_PER_PAGE, PGS_CSS_MODE) are **deleted** — replaced by env/config equivalents (BASE_URL → `APP_URL`, UPLOAD_DIR → filesystem disk, ITEMS_PER_PAGE → `config('pgs.per_page')`).

## 5. Environment checklist before a release

- [ ] `.env.production` verified on server (APP_ENV, debug off, secure cookies, HTTPS URL)
- [ ] `APP_KEY` matches artifact's cache expectations (or `config:cache` rebuilt on deploy)
- [ ] DB credentials least-privilege; migration user separate
- [ ] Backups configured with retention; restore drill passed (Operations §3)
- [ ] Sentry DSN set; release tag matched
- [ ] Rate limits and TOTP settings as per Security §2

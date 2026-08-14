# Environment Configuration

`.env` reference, environments, and Docker services. Secrets never live in the repo — `ENV.example`-style values here are placeholders only.

---

## 1. Environments

| Env | Purpose | Data | Key differences |
|---|---|---|---|
| `local` | Developer machine | Seeded dev data | `APP_DEBUG=true`, Telescope on, Mailpit, no TLS |
| `testing` | CI | Fresh migrated + seeded per run | `DB_CONNECTION=mysql` to `pgs_test`, in-memory queue |
| `staging` | Parity, E2E, UAT | Masked production copy (Phase 2) | Realistic data, Sentinel reports, debug off |
| `production` | Live | Real | Debug off, CSP enforced, log review on |

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

# LAN deployment: sessions/cache/queue all use MySQL tables — no Redis service.
SESSION_DRIVER=database
SESSION_LIFETIME=480
SESSION_SECURE_COOKIE=false

CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp           # Gmail SMTP for six-digit password reset codes
MAIL_SCHEME=smtp           # STARTTLS on port 587
MAIL_ENCRYPTION=tls
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=             # Gmail account
MAIL_PASSWORD=             # Gmail app password; keep this server-side
MAIL_FROM_ADDRESS="pgs@example.ph"
MAIL_FROM_NAME="${APP_NAME}"

# Use MAIL_MAILER=outbox for LAN-only local preview instead of sending email.

FILESYSTEM_DISK=local
PRIVATE_DISK=uploads
BACKUP_DISK=local          # LAN host disk; S3 only if provisioned later
BACKUP_RETENTION_DAILY=14
BACKUP_RETENTION_WEEKLY=8
BACKUP_RETENTION_MONTHLY=12
BACKUP_ARCHIVE_PASSWORD=       # required in production; use a strong secret

UPLOAD_MAX_SIZE_MB=25
UPLOAD_WHITELIST="pdf,docx,xlsx,pptx,jpg,jpeg,png,zip"
UPLOAD_MANUAL_REVIEW=true  # no ClamAV; flagged uploads are reviewed manually

RATE_LIMIT_LOGIN=5
RATE_LIMIT_SUBMISSIONS=30

FEATURE_LEGACY_REDIRECT=true   # dual-run bookmark redirect map
LAN_HOST_IP=192.168.1.10       # server LAN IP used for APP_URL on the network
```

- `APP_URL` = `http://<LAN_HOST_IP>:8082` so every LAN client resolves assets/routes correctly (see LocalDev.md §2b).
- `DB_USERNAME`: least-privilege app user (SELECT/INSERT/UPDATE/DELETE + migrations user separate during deploys) — never root.
- `APP_KEY`: `php artisan key:generate`; rotated on suspected exposure.
- `SESSION_SECURE_COOKIE` stays `false` for plain-HTTP LAN use; set `true` only if TLS is added.

## 3. Runtime services (XAMPP — no Docker)

| Service | Role | Notes |
|---|---|---|
| Apache (XAMPP) | Web server | vhost `pgs.app` on port 8082 (bound `0.0.0.0`); legacy app stays on 8080 until cutover |
| MySQL (XAMPP) | Database + cache + queue tables | `pgs` (app), `pgs_test` (tests) |
| PHP (XAMPP) | Runtime | extensions: pdo_mysql, gd, mbstring, intl, zip, exif |
| Windows Task Scheduler | Queue worker | `php artisan queue:work --once` every minute (Operations.md) |

No Redis, no ClamAV, no mail server, no Docker — the LAN host runs XAMPP only.

## 4. Config vs env

- Laravel config files read env only; no `env()` calls outside `config/`.
- New settings: add to `config/pgs.php` (a single app-config file) + document here + `.env.example` in the same PR.
- Legacy `config.php` constants (BASE_URL, DB_*, UPLOAD_DIR, ITEMS_PER_PAGE, PGS_CSS_MODE) are **deleted** — replaced by env/config equivalents (BASE_URL → `APP_URL`, UPLOAD_DIR → filesystem disk, ITEMS_PER_PAGE → `config('pgs.per_page')`).

## 5. Environment checklist before a release

- [ ] `.env.production` verified on server (APP_ENV, debug off, secure cookies, HTTPS URL)
- [ ] `APP_KEY` matches artifact's cache expectations (or `config:cache` rebuilt on deploy)
- [ ] DB credentials least-privilege; migration user separate
- [ ] Backups configured with retention; restore drill passed (Operations §3)
- [ ] Error log path verified and writable
- [ ] Rate limits and TOTP settings as per Security §2

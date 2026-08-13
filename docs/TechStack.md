# Tech Stack

Current legacy stack vs. the target stack, with versions, rationale, and rejected alternatives.

---

## 1. Target stack (locked)

| Layer | Choice | Version (pin) | Rationale |
|---|---|---|---|
| Language | PHP | 8.4.x | 8.0 is EOL; 8.4 is current stable. `declare(strict_types=1)` everywhere. |
| Framework | Laravel | 12.x | Router, Eloquent, migrations, auth, queues, validation — closes every backend gap in one move. |
| Frontend framework | React | 19.x | Most-documented ecosystem; team familiarity optional (see Skills). |
| Data layer | Inertia.js (React) | 2.x | Server-rendered pages + client components; no API/SPA split; CSRF & auth free. |
| Styling | Tailwind CSS | 4.x | Utility-first, CSS-variable theming, dark mode; shadcn/ui is built on it. |
| Components | shadcn/ui | latest | Copy-paste components (Radix primitives + Tailwind) — no framework lock-in, full source control. |
| Typography | Inter (Variable) | `@fontsource-variable/inter` | Self-hosted, hashed by Vite; no CDN, no FOUT. |
| Build tool | Vite | 6.x | Ships with Laravel; fast HMR; hashed/cached assets. |
| Database | MySQL / MariaDB | 8.x / 10.11+ | Already in production; no migration cost. |
| ORM | Eloquent | (Laravel) | Query builder + prepared statements; removes raw SQL interpolation. |
| Tests | Pest or PHPUnit | 3.x / 11.x | Pest for readable feature tests; coverage enforced by CI. |
| Static analysis | PHPStan | `--level=max` + strictRules | Catches the class of bugs this codebase has historically shipped. |
| Formatting | Pint (Laravel default) + Prettier + ESLint | latest | Zero-debate formatting; enforced in CI. |
| CI | GitHub Actions | — | PR gates: tests, lint, build, audit, coverage. |
| Observability | Laravel structured logs + `storage/logs` | (framework) | Log-based monitoring; no external services (no Sentry). |
| Ops | XAMPP Apache on the LAN host | — | App served from `htdocs`; LAN peers reach it over HTTP. No Docker. |
| Cache/Queue | Laravel `database` drivers | (framework) | Cache + queues via MySQL tables; **no Redis** (LAN deployment constraint). |
| Backup | `spatie/laravel-backup` | latest | Replaces `exec(mysqldump)` in `admin_backup_restore.php`. |

---

## 1b. Deployment model: LAN server (htdocs)

The system runs on one XAMPP machine inside the TRC DOH network. Other users on
the same LAN reach it through the browser. Consequences that the whole roadmap
assumes:

| Topic | Decision |
|---|---|
| Hosting | XAMPP Apache (`httpd` on port 8080, vhost `pgs.app` bound to `0.0.0.0:8082`) |
| Access URL | `http://<server-LAN-IP>:8082` — `APP_URL` is set to the server's LAN IP so assets/routes work for every client |
| Auth | Password-based sessions only (No 2FA — not required for a closed LAN deployment) |
| Encryption | Optional self-signed TLS if LAN policy demands it; default plain HTTP on the internal network |
| Cache/queue | Laravel `database` drivers (no Redis service on the host) |
| Uploads scanning | Manual review/quarantine (no ClamAV service) — see [Uploads.md](./Uploads.md) |
| Monitoring | Laravel logs + audit log + `/up` endpoint (no Sentry/APM) |
| Testing | Pest/PHPUnit feature tests only (no Playwright/k6/load-test infra) |
| Firewall | Port 8082 (and 8080) open to the LAN; Apache bound to `0.0.0.0` |

Documented in [LocalDev.md](./LocalDev.md) §2b and enforced per phase.

---

## 2. Legacy stack (being replaced)

| Layer | Legacy | Why replaced |
|---|---|---|
| PHP | 8.0+, procedural, no framework | No router, no autoload beyond `require`, 178 entry points |
| DB access | PDO **and** mysqli (both connected per request) | Duplicated, inconsistent; `die()` on error |
| Schema | `CREATE/ALTER TABLE` inside page code, swallowed exceptions | Silent drift; schema not reproducible |
| Frontend | Bootstrap 5.3.3 CDN + Tailwind 2.2 CDN (mixed) | Offline = unstyled site; no versioning; no SRI |
| JS | Inline scripts + jQuery 3.6 + SweetAlert2 + Chart.js + jsPDF | Unmaintainable; no bundling; duplicate loads per page |
| Build | `build_css.php` concat script | No minification of JS, no cache busting strategy |
| Auth | Custom `access_guard.php` + session | Works, but no 2FA, no password reset flows, no throttling |
| Tests | 2 files (existence checks) | ~0% coverage |
| Error handling | `die()`, `try/catch {}` swallows | Blinds operators to failures |

---

## 3. Versioning policy

- **Pin exact versions** in `composer.json` / `package.json` (no `*` or `^` looseness in production lockfiles).
- `composer.lock` and `package-lock.json` committed; CI installs with `--no-dev` on deploy.
- Dependabot opens PRs; **every dependency bump must pass CI** and ship with the app.
- Major framework upgrades get their own phase entry and ADR.

---

## 4. Rejected alternatives

| Alternative | Why rejected |
|---|---|
| Pure SPA + REST API (no Inertia) | Doubles the auth/validation/CSRF surface; no benefit for an internal dashboards app |
| Vue 3 (via `laravel-inertia-vue`) | Fine choice, but React chosen for ecosystem + shadcn/ui + type safety at scale |
| Next.js/Remix frontend | Requires separate server, complicates PHP asset pipeline and deploys |
| Livewire | Excellent for simple CRUD but weak for the data-heavy, chart/table modules (annexes, sector roadmaps) |
| Symfony | Powerful, but Laravel's batteries-included tooling (Breeze, migrations, testing) fits this team's size |
| PostgreSQL | No business reason to migrate; MySQL stays in prod |
| Server-side rendered Blade-only | Viable, but loses the interactive dashboards, drag-reorder blocks, and chart components the legacy app already implies |
| **Redis / queue services** | **No extra services on the LAN host** — Laravel `database` cache/queue drivers are sufficient at this scale |
| **Sentry / APM** | No external accounts — structured logs + audit log + `/up` cover monitoring needs |
| **Docker** | Not used on this machine (XAMPP native); documented only for reference |

---

## 5. Runtime dependencies to know

- **CDN ban**: all fonts, CSS, JS vendored through Vite. No runtime network dependency.
- **Environment**: `APP_ENV`, DB, mail via `.env` only; `config.php` constants removed. `APP_URL` = `http://<server-LAN-IP>:8082` for LAN clients.
- **PHP extensions required**: `pdo_mysql`, `gd`/`imagick` (uploads), `mbstring`, `intl`, `zip` (backup). **No `redis` extension needed.**

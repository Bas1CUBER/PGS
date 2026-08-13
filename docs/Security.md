# Security

Security baseline for the PGS application. This is an internal government health system — treat user and patient-adjacent data accordingly (RA 10173 / NPC guidance as applicable).

---

## 1. Threat model (top threats)

| Threat | Attack surface in legacy | Defense in new app |
|---|---|---|
| SQL injection | String-interpolated queries (`roadmap_page_builder.php`, dynamic columns in `revenue/`) | Eloquent/query builder only; CI grep-gate; PHPStan security rules |
| XSS | Direct `echo $_GET/$_POST`; inline JS; CDN scripts | Blade/Inertia auto-escaping; CSP; no inline scripts |
| CSRF | Manual `verify_csrf()` calls — easy to skip | Laravel middleware on all POST/PUT/DELETE; verified by tests |
| Broken access control | Role checks scattered; page-access table per-file | Central middleware + policies; matrix tests |
| Upload abuse | Filenames echoed unsanitized | Whitelist MIME/ext, UUID storage, virus scan, signed URLs |
| Command injection | `exec(mysqldump)` in `admin_backup_restore.php` | spatie backup lib; zero shell in app code |
| Session theft | Basic cookie params | HttpOnly, SameSite, Secure, rotation on auth |
| Supply chain | Unpinned CDNs (jQuery, Chart.js, jsPDF, Tailwind) | Vendored via Vite + lockfiles; audit in CI |

---

## 2. Authentication & sessions

- Laravel Breeze auth; password policy: min 12 chars, breached-password validation, `password:history` on change.
- Login throttling + lockout (5 fails → 15 min); rate limit on reset (1/min).
- Sessions: database store, HttpOnly + SameSite=Lax + Secure (prod), idle timeout 8h, `regenerate()` on privilege change.
- Admin: TOTP 2FA enforced (Phase 8); recovery codes stored hashed.
- Passwords: Argon2id (`bcrypt` acceptable for existing hashes at import); never log/echo credentials; `Hash::check` only.

## 3. Authorization

- `Role` enum: `admin`, `focal`, `employee`. Default deny.
- Policies per resource (User, Deliverable, Roadmap, Notice, Upload, DeadlineControl, Backup).
- Page access matrix (`user_page_access` port): `CanAccessPage` middleware + cached for 60s (matching legacy UX).
- Admin-only actions (backup, restore, deadline, user toggle, import) gated by `Gate::authorize`.
- Every authorization decision covered by a feature test (guest/employee/focal/admin × action).

## 4. Input & output hardening

- **Output**: everything via `{{ }}` / React escape; `h()` legacy helper not needed in new code; no `{!! !!}` without review.
- **Input**: Form Request rules for every endpoint; cast inputs to expected types before queries.
- **CSRF**: default middleware; API/Inertia requests use XSRF cookie; never disable on state-changing routes.
- **Headers** (Laravel middleware, replacing `.htaccess` partial set):
  - `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Permissions-Policy` (geolocation/mic/camera off)
  - **CSP**: `default-src 'self'; script-src 'self' 'nonce-...'; style-src 'self' 'nonce-...'` — nonces injected via Vite plugin; report-only first, enforced after clean audit
  - `Strict-Transport-Security` in prod
- **No eval/variable variables/shell functions** — grep-gate.

## 5. File uploads

1. MIME whitelist (pdf, docx, xlsx, pptx, images, zip-limited) — content sniff, not just extension.
2. Size caps per module (deliverables ≤ 25 MB default, configurable); chunked upload ≥ 50 MB.
3. Store as UUID filenames on private disk; original name only in DB, escaped on render.
4. Queue: virus scan → reject into `quarantine/` + notify uploader.
5. Images: strip EXIF/GPS at ingest.
6. Signed, expiring URLs for download/preview; downloads logged (audit).

## 6. Secrets & config

- `.env` only; `.env.example` complete; `.env` in `.gitignore`; no credentials in docs/code.
- `APP_KEY` rotated on deployment; DB creds scoped (least privilege DB user for app).
- Gitleaks (or equivalent) secrets scan in CI on every push.
- Never log `$_SERVER`, full request bodies with passwords, or tokens.

## 7. Dependency & runtime security

- `composer audit` + `npm audit` fail CI; Dependabot weekly; lockfiles committed.
- CDN ban (offline-safe + no third-party scripts); all assets vendored.
- PHP/Composer images pinned in Docker; `docker scout` or Trivy scan in CI.
- OWASP ZAP baseline scan scheduled (weekly) on staging.

## 8. Data protection

- Backups: `spatie/laravel-backup` → encrypted at-rest storage; restore tested quarterly (documented runbook).
- Audit log (immutable-ish, append-only table) for: auth events, admin CRUD, role changes, uploads, backups, deadline changes, exports.
- Retention policy documented per data class (uploads, audit, logs, sessions).
- PII minimization: staff profiles store name/office/email only; no sensitive fields beyond business need.

## 9. Security checklist before each release

- [ ] `composer audit`, `npm audit` clean
- [ ] Coverage includes auth/CSRF/upload tests
- [ ] CSP enforced (not report-only) on all routes
- [ ] Rate limits verified on auth + upload endpoints
- [ ] No secrets in diff (gitleaks green)
- [ ] Audit log entries created for all admin flows touched by the release
- [ ] Backup restore test passed this quarter

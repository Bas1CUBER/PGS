# API

Internal route/API surface for integrations. PGS is an internal application — most interaction is Inertia-rendered pages; this file documents the machine-readable surface (AJAX endpoints, future integrations, exports).

---

## 1. API philosophy

- **No public API by default.** Every endpoint ships behind auth; roles enforced by policy.
- Inertia pages carry data via server-rendered props — most modules never need JSON endpoints.
- Where JSON is needed (autocomplete, charts, notification badge, exports), it lives under `/api` with the same middleware, CSRF via XSRF cookie, and rate limits.
- Contracts documented here are **stable**: changes are additive or versioned (`/api/v1/...`).

## 2. Endpoint inventory (planned)

### Auth & profile
| Method | Path | Access | Notes |
|---|---|---|---|
| POST | `/login` | guest | throttled (5/min) |
| POST | `/logout` | auth | |
| POST | `/password/email` | guest | 1/min |
| POST | `/password/reset` | guest | |
| PUT | `/profile/password` | auth | history check |

### Notifications
| Method | Path | Access | Notes |
|---|---|---|---|
| GET | `/notifications` | auth | paginated, `?unread=1` |
| GET | `/notifications/unread-count` | auth | badge polling (60s) |
| POST | `/notifications/{id}/read` | auth (owner) | |
| POST | `/notifications/read-all` | auth | + undo toast |
| GET | `/api/notifications` | auth | JSON variant for integrations |

### Users & access (admin)
| Method | Path | Notes |
|---|---|---|
| GET/POST | `/users` | index (search/filter/paginate), store |
| GET/PUT/DELETE | `/users/{user}` | update incl. role, soft-delete |
| POST | `/users/{user}/toggle` | active |
| PUT | `/users/{user}/access` | page-access matrix |
| POST | `/users/import` | CSV, dry-run first |

### Deliverables & uploads
| Method | Path | Notes |
|---|---|---|
| GET/POST | `/deliverables` | list, store |
| PUT/DELETE | `/deliverables/{deliverable}` | update, delete |
| POST | `/deliverables/{id}/status` | transition via workflow service |
| POST | `/uploads` | chunked upload entry |
| GET | `/uploads/{id}/download` | signed URL redirect, audited |

### Roadmaps & content
| Method | Path | Notes |
|---|---|---|
| GET/POST | `/roadmaps`, `/roadmaps/{item}` | titles/items |
| POST | `/roadmap-blocks/{itemId}/reorder` | drag-reorder |
| GET/POST/PUT/DELETE | `/notices`, `/resources`, `/gallery/albums` | CRUD |
| GET | `/api/roadmaps/{id}/values` | chart data (sector modules) |

### System (admin)
| Method | Path | Notes |
|---|---|---|
| GET/POST/PUT | `/deadlines` | deadline controls |
| GET | `/backups`, POST `/backups`, POST `/backups/{id}/restore` | spatie-backed |
| GET | `/audit-logs` | paginated, filterable |

## 3. Conventions

- JSON: camelCase keys; `{ "data": [...], "meta": { pagination } }` envelope for lists.
- Errors: `422` with `{ errors: { field: [...] } }` (Inertia-native), `403`, `429` with `Retry-After`.
- Timestamps: ISO 8601 UTC; client formats per Design §3.
- Idempotency keys on uploads (`X-Idempotency-Key`) to survive retries.
- Rate limits: upload 30/min, auth 5/min, unread-count 60/min (config-driven).

## 4. Versioning & deprecation

- Additive changes: no version bump.
- Breaking changes: `/api/v2`, keep v1 for ≥ 6 months, deprecation notice via header + docs.
- Internal consumers only; no third-party access (no public registration — internal SSO/accounts only).

## 5. Testing

- Feature tests cover every endpoint above (matrix in Testing §4).
- Rate-limit tests (429 after N), CSRF tests (419 without token), policy tests per role.
- API docs stay in sync via `php artisan scribe:generate` if adopted (ADR to add when API grows).

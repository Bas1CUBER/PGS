# Uploads

Specification for the file-upload pipeline. [Security.md](./Security.md) states the policy; this file is the implementable spec — the reference for `UploadPipelineService`, its tests, and the `UploadDropzone` UI contract.

---

## 1. Supported file types

| Category | Extensions | MIME (content-sniffed) | Max size |
|---|---|---|---|
| Documents | pdf, docx, xlsx, pptx | `application/pdf`, Office Open XML types | 25 MB (default) |
| Images | jpg, jpeg, png, webp | `image/*` | 10 MB |
| Archives | zip (limited use) | `application/zip` | 50 MB |
| Legacy uploads | any already stored | — | read-only, no new uploads |

- Whitelist config: `UPLOAD_WHITELIST` + per-module overrides (`config/pgs.upload_limits`).
- **Extension check is not enough**: MIME content sniffing + magic-byte check on server; client hint only for UX.
- Rejected → 422 with specific reason shown inline in dropzone.

## 2. Pipeline stages

```
Client (UploadDropzone)
  → POST /uploads (chunked if > 50 MB, Resumable protocol)
  → StoreUploadRequest: size + extension pre-check, rate limit, deadline check
  → UploadPipelineService::handle()
      1. stream to private disk: uploads/{module}/{uuid}.{ext}
      2. persist metadata row (original_name, size, mime, sha256, uploaded_by, module, related_id)
      3. dispatch queue job: metadata/review step
         - clean  → keep; notify success listeners
         - flagged → move to review/ for manual operator inspection (no ClamAV on the LAN host; see Security.md §5)
      4. image variant generation (if image): responsive sizes, strip EXIF/GPS
      5. index/denormalize triggers (module-specific)
  → signed, expiring download URL for preview
```

- Status column reflects pipeline: `staging → stored → ready` (UI shows spinner until `ready`).
- **Manual review**: the operator reviews flagged uploads (heuristic flags: executable extensions, size anomalies) via the admin list; approved files stay, flagged files are moved to `review/` and audited.

## 3. Storage & naming

| Rule | Value |
|---|---|
| Disk | `uploads` (private; outside webroot) |
| Path | `uploads/{module}/{yyyy}/{mm}/{uuid}.{ext}` |
| Original name | DB only; rendered escaped (never used as filesystem path) |
| Collisions | impossible (UUID); duplicates allowed with distinct rows |
| Download/preview | signed URLs, 15 min expiry; access checked via policy |
| Orphan detection | nightly job: storage files with no DB row → quarantine list for admin |

## 4. UI contract (`UploadDropzone`)

- Drag & drop + click-to-browse; multi-select with per-file validation before submit.
- Inline errors: type not allowed, too large, duplicate name (info only), deadline closed.
- Progress per file; cancel per file; retry on failure; resume on connection loss (chunked).
- Accessibility: keyboard accessible, `aria-describedby` error wiring, focus retained.

## 5. Security invariants (tested)

- [ ] Path traversal in `original_name`/`related` params → 422 (no filesystem escape)
- [ ] Double extension / mangled MIME → rejected
- [ ] Oversize → rejected before stream (Content-Length + streamed guard)
- [ ] Flagged file never reaches the public path; manual-review actions audited
- [ ] EXIF/GPS stripped from images at ingest
- [ ] Signed URL expiry enforced server-side; download logged
- [ ] Deadline enforcement at upload time (Workflows §6)
- [ ] Rate limit per user per module (UPLOAD_RATE_LIMIT_UPLOAD)

## 6. Metrics

- Per module: upload count, bytes, failure rate, scan duration, quarantine count.
- Report weekly (Operations weekly review); alert on failure rate > 5%.

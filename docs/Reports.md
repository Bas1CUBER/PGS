# Reports & Export

Specification for all generated documents: annexes, OPCR, strategy reviews, operations reviews, and tabular exports. Replaces the legacy browser-side `jspdf`/`html2canvas` hacks with server-side generation.

---

## 1. Generated documents

| Document | Source (legacy) | Format | Trigger | Owner |
|---|---|---|---|---|
| Annex B/D/E/H/J/K | `annexb.php` … `annexk.php` | PDF (A4 print) | View/export from module | Read-only viewers, admins |
| OPCR | `OPCR.php` | PDF + on-screen | Annual commitment | Office leads |
| Strategy review | `strategy_review_generate_pdf.php` | PDF | Focal/admin, any status | Focal |
| Operations review | `operations_review*.php` | PDF | Focal | Focal |
| Tabular exports | various `get_*.php` | CSV/XLSX | All list views | Any role |

## 2. Generation engine

- **Server-side PDF**: DomPDF (simple) or Browsershot (complex layouts, WebKit fidelity). Decision recorded in ADR (pick one, keep one).
- **Print path for on-screen documents**: dedicated print stylesheet (Design §7); browser print → same visual as PDF (golden test: PDF vs print screenshot within tolerance).
- **CSV/XLSX**: `maatwebsite/excel` or native fputcsv for small exports; encoding UTF-8 BOM for Excel compatibility.
- Generation runs on the **queue** (low priority); UI shows progress + download link when ready (toast + notification for long jobs).
- Every export is **audited** (who, what, when, record count).

## 3. Layout contract (all PDFs)

- A4 portrait (annex D/H/K may be landscape — verify per template).
- Margins 10 mm; footer: page number, doc identifier, generated timestamp (Asia/Manila).
- Header: TRC DOH banner (SVG logo), doc title, version.
- Tables: repeat header row on page breaks; never split a row across pages; zebra off (print friendly).
- Status badges render as monochrome text labels in PDF (Design §7).
- Fonts: embedded (PDF) — use Inter subset; no system-font dependency.
- Filename: `<DocCode>_<office>_<period>_<date>.pdf`, sanitized.

## 4. Data contract

- Reports render from the **same models/services** as UI — no report-only SQL (single source of truth).
- Period handling: fiscal/calendar year passed as query params; defaults to current `Asia/Manila` year.
- Versioning: each generated PDF stores `generated_at` + source record IDs (reproducibility); a re-generation after data change is a new artifact, audit-logged.

## 5. Export UI

- Every list (deliverables, users, notices, module indicators) has an **Export** button (CSV default; PDF for documents).
- Exports respect current filters; filename includes filter summary when useful.
- Progress/state via the global toast + downloads pattern (UX §3); no page-blocking generation.
- Row cap warning: > 10k rows exports are queued with notification instead of synchronous.

## 6. Testing

- Golden-value tests: known dataset → expected PDF/CSV content (text extraction, row counts).
- Layout checks: manual print-preview comparison against the design baseline for each document.
- Size tests: 1000-row export completes under budget; PDF < 5 MB typical.
- Auth tests: export respects role/policy (no data leak via direct URL — signed or session-checked).

## 7. Accessibility of outputs

- PDFs must have a text layer (never images) — screen-reader friendly; tagged where DomPDF/Browsershot support it.
- CSV exports are data-only; document exports retain semantic headings.

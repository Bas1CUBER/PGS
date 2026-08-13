# Design System

Brand, tokens, typography, and component behavior for the PGS web app. Mirrors the organizational identity of TRC DOH while fixing the legacy app's styling fragmentation (three CSS files, mixed Bootstrap+Tailwind, broken concatenated CSS).

---

## 1. Design principles

1. **Government-grounded, modern**: clean institutional look; no decorative noise; information density over flair.
2. **One token system**: colors/typography/spacing/radius defined once in `app.css` `@theme`; components consume tokens only.
3. **Dark mode ready**: every token has light/dark values; respects OS preference with manual override.
4. **Accessibility first**: WCAG 2.1 AA contrast on all token pairs; focus states never removed.
5. **Print-clean**: annexes/OPCR/documents must print A4 with zero chrome.

---

## 2. Color tokens

| Token | Light | Dark | Usage |
|---|---|---|---|
| `--background` | `#f8fafc` | `#0f172a` | App canvas |
| `--foreground` | `#0f172a` | `#f1f5f9` | Primary text |
| `--primary` | `#0b4aa2` (legacy blue) | `#3b82f6` | Brand/actions |
| `--primary-foreground` | `#ffffff` | `#0f172a` | Text on primary |
| `--secondary` | `#e2e8f0` | `#1e293b` | Surfaces/wells |
| `--muted` | `#f1f5f9` | `#1e293b` | Subtle backgrounds |
| `--muted-foreground` | `#64748b` | `#94a3b8` | Secondary text |
| `--accent` | `#e8f0fe` | `#172554` | Highlights/hover |
| `--destructive` | `#dc2626` | `#f87171` | Errors/destructive |
| `--success` | `#16a34a` | `#4ade80` | Approved/uploaded |
| `--warning` | `#d97706` | `#fbbf24` | Returned/pending-warn |
| `--border` | `#e2e8f0` | `#334155` | Dividers/inputs |

Status colors map to domain: `upload → success`, `approved → primary`, `returned → warning`, `edit → muted`, `error → destructive`.

**Contrast rule**: any text/background pair must pass AA (≥ 4.5:1 body, ≥ 3:1 large) — verify with tokens in CI (axe).

---

## 3. Typography

- **Font**: Inter Variable (`@fontsource-variable/inter`), `font-display: swap`, no CDN.
- **Scale** (Tailwind defaults overridden in `@theme`):

| Token | Size / weight | Use |
|---|---|---|
| `display` | 2.25rem / 700 | Page titles |
| `title` | 1.5rem / 700 | Section headers |
| `heading` | 1.125rem / 600 | Card headers |
| `body` | 0.9375rem / 400 | Default text |
| `small` | 0.8125rem / 400 | Meta, captions |
| `label` | 0.75rem / 600, uppercase, tracking-wide | Form labels, badges |

- Line height 1.5 body / 1.2 headings; tabular numbers for statistics (`font-variant-numeric: tabular-nums`).
- Philippine domain conventions: dates `MMM D, YYYY` (e.g. `Aug 13, 2026`), amounts `₱` prefix with thousands separators — formatters in `lib/utils.ts`, tested.

---

## 4. Spacing & radius

- Spacing scale: 4px base (Tailwind default), used via `p-*`/`gap-*` only.
- Card padding `p-6`, gap `gap-4`, page gutter `px-4 md:px-6 lg:px-8`.
- Radius tokens: `sm=6px` (inputs), `md=8px` (cards), `lg=12px` (dialogs), `full` (badges/pills).
- Shadows: one elevation set (none → subtle → dialog overlay); no arbitrary shadows.

---

## 5. Components & behavior

| Component | Behavior rules |
|---|---|
| Buttons | One primary per view; secondary for alternatives; destructive requires ConfirmDialog |
| Cards | Header (title + actions slot), body, optional footer; never nested beyond 2 levels |
| Tables | Sticky header, zebra off, row hover, sortable headers (aria-sort), empty state required |
| Badges | Status always badge-form: dot + label; color per §2 table |
| Dialogs | Radix; `aria-describedby` required; focus restored on close; `X` + Esc + overlay-click all close |
| Dropdowns | Keyboard navigable; item `disabled` with reason shown via tooltip |
| Upload dropzone | Drag + click; inline validation (type/size) before submit; progress bar; retry |
| Skeletons | Every async view shows skeleton matching final layout (no spinners-only screens) |
| Toasts | Auto-dismiss 5s; undo actions where destructive-ish (e.g. "Mark all read") |

---

## 6. Layout shell

- **Sidebar** (desktop, collapsible): logo + role-aware nav, collapsed = icons-only.
- **Topbar**: page title, global search (Phase 8+), notification dropdown, user menu (name, role, office, change password, logout).
- **Mobile**: drawer sidebar; bottom-safe sticky nav; larger tap targets (≥ 44px).
- **Banner slot** under topbar for deadline warnings (info/warning/destructive variants).
- Max content width `max-w-7xl` centered; dashboards may use full width.

---

## 7. Print rules (annexes, OPCR, strategy reviews)

- `@media print`: hide sidebar/topbar/banners/interactions; force light tokens; page margins 10mm; A4; tables avoid page-break inside rows; status badges print as monochrome text labels.

---

## 8. Icons & imagery

- Icons: lucide-react (shadcn default); consistent size `size-4` in UI, `size-5` in nav.
- Logos: vector (SVG) versions; text always alongside logo mark.
- Gallery: real photos only; exif-stripped; responsive via media pipeline.

---

## 9. Governance

- Token changes go through a PR touching only `app.css` + related components; CI contrast check must stay green.
- No ad-hoc hex colors in components — ESLint rule flags hardcoded colors.
- Design debt goes to `docs/Design.md` ADR log, not silent local overrides.

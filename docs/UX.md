# UX

User-experience standard for the PGS app. Fixes the legacy app's UX debt: alert-box dead ends, unstyled dropdowns, inconsistent states, no mobile story.

---

## 1. Users & goals

| Persona | Role | Top tasks | Friction points today |
|---|---|---|---|
| Admin (PGS team) | System owner | User management, backups, deadlines, monitoring | Backup via raw UI, no audit trail, mixed Bootstrap/Tailwind |
| Focal (unit lead) | Approver | Review uploads, approve/return, statuses, notices | Status feedback buried, no notification clarity |
| Employee (staff) | Contributor | Submit deliverables, track status, roadmap visibility | Unclear where uploads land; deadline surprise |
| Reviewer/guest | Read-only | View roadmaps, annexes, survey | Print layouts messy; navigation inconsistent |

**UX principle**: every task is ≤ 3 clicks from the dashboard; every action has an outcome (state change + notification + undo/retry where applicable).

---

## 2. Information architecture

- **Sidebar (role-filtered)**: Dashboard · Roadmaps · Deliverables · Reviews · Communication Plan · Notices · Resources · Gallery · User Management (admin) · System (admin: deadlines, backup, audit).
- Module pages follow one pattern: **List → (Filters/Search) → Row actions → Detail**.
- Global search (Phase 8): users, deliverables, notices, roadmaps — one box in topbar.
- Breadcrumbs on every page ≥ 2 levels deep; page title = H1 = route name.

---

## 3. State & feedback rules

| State | Pattern |
|---|---|
| Loading | Skeleton matching layout (never spinner-only) |
| Empty | Icon + title + reason + primary action ("No deliverables yet — Upload your first") |
| Error | Friendly title, retry button, no stack traces; Sentry captures details |
| Success | Toast + destination (redirect); visible state change on origin page |
| Partial/failure | Inline field errors; form data preserved on reload |
| Offline | Banner when network drops; retry semantics on uploads |

- **Every mutation shows feedback within 150ms** of the request settling — no silent saves.
- Destructive actions: ConfirmDialog with typed confirmation for irreversible ops (restore backup, delete user).

---

## 4. Forms

- One column on mobile, up to two on desktop; labels always visible (no placeholder-only).
- Validation: inline errors under fields on submit; disabled submit while pending; `aria-describedby` wiring.
- Upload forms: dropzone with drag feedback, size/type check before send, progress %, cancel, resume on error (retry button).
- Drafting: auto-save indicator for long forms (strategy reviews) — "Saved 2 min ago".

---

## 5. Notifications UX

- Badge with unread count (live via 60s polling; Reverb later); grouped by day; types color-coded (upload/approved/returned/edit).
- Clicking a notification navigates to the related item (`related_id`); mark-read on click; "Mark all read" with undo toast.
- Empty state for zero notifications; link to notification history (paginated).

## 6. Deadlines

- Banner (topbar slot): role-aware message + remaining time; warning color ≤ 3 days; destructive when expired.
- Deadline respected at submit: clear inline error explaining why submission closed + contact note.
- Admins preview banner exactly as each role sees it (settings screen).

---

## 7. Tables & data

- Sortable columns with `aria-sort`; persisted sort/filter in URL query (shareable, back-button safe).
- Pagination: numbered + count text ("Showing 21–40 of 412"); jump-to-page.
- Row actions: hover-visible icon buttons on desktop; full-width action sheet on mobile.
- Status shown as colored badge with dot — consistent everywhere (Design §2 mapping).
- Export (CSV/PDF) buttons always explicit and audited.

---

## 8. Mobile & responsive

- Breakpoints: mobile < 640, tablet ≥ 768, desktop ≥ 1024. Test matrix: iPhone SE (small), Pixel 7, 1366×768 desktop.
- Sidebar → drawer; tables → horizontal scroll or card-ification for the 3 most-used tables (deliverables, users, notifications).
- Tap targets ≥ 44×44px; sticky primary action (e.g., "Upload") on mobile lists.

---

## 9. Accessibility (WCAG 2.1 AA)

- Full keyboard path: skip link, focus order = visual order, visible focus rings (token `--ring`).
- Screen readers: landmarks (header/nav/main), heading hierarchy H1→H3 max, `aria-current` on active nav, live regions for toasts/notifications.
- Content: plain language, no jargon-only labels; PDF/annex exports have text layer.
- Motion: reduce-motion respected (no spinning skeletons under `prefers-reduced-motion`).

---

## 10. UX acceptance for a page to ship

- [ ] 3-click task completion verified on the page's core tasks
- [ ] Loading/empty/error/success states all present (tested)
- [ ] Keyboard-only pass; axe scan clean
- [ ] Mobile layout reviewed at 375px and 768px
- [ ] Notifications + audit wired for state-changing actions

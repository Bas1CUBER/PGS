# Phase 4 — Frontend Foundation

**Goal**: The single app shell every future page renders in: React 19 + TypeScript strict + Inertia 2 + Tailwind v4 + shadcn/ui + Inter, with design tokens, routing, and a11y tooling wired in.

**Effort**: 3 weeks · **Depends on**: Phase 1 (Vite/CI) · **Unblocks**: all UI work in Phases 5–7

---

## 1. Objectives

1. One layout (authenticated shell + public shell) replacing 50+ duplicated `<head>` blocks.
2. Design token system (colors, spacing, typography, radius, dark mode) — see [Design.md](./Design.md).
3. shadcn/ui initialized against Tailwind v4 (Vite, not Next.js).
4. Inter variable font self-hosted via `@fontsource-variable/inter`.
5. A11y baseline (axe-core in CI) and strict TypeScript.

---

## 2. Task checklist

### 2.1 Toolchain
- [x] Vite + React plugin configured; `laravel-vite-plugin` + `@tailwindcss/vite` (Tailwind v4 — upgraded from Breeze's v3, PostCSS config deleted)
- [x] React upgraded 18 → 19; TypeScript `strict` (+ `noUncheckedIndexedAccess` style rules via typescript-eslint strict)
- [x] ESLint flat config: typescript-eslint **strictTypeChecked + stylisticTypeChecked**, react-hooks, react-refresh; zero-warning policy — CI-enforced. Legacy Breeze files (Auth/Profile/Welcome + legacy components) exempt with documented reason (replaced Phase 5)
- [x] Prettier + `prettier-plugin-tailwindcss`; CI `--check`
- [x] `npx shadcn@latest init --template laravel -b radix` → Tailwind v4 CSS variables setup; `tailwind.config` empty (v4), no tailwind.config file
- [x] `@fontsource-variable/inter` self-hosted; `--font-sans` → Inter; `font-display: swap`; CDN fonts removed from blade

### 2.2 App shell
- [x] `app.tsx` root: `createInertiaApp` + ThemeProvider + Toaster (sonner); TS-typed `PageProps` (auth.user nullable, unreadCount, deadline, flash)
- [x] `AuthenticatedLayout`: shadcn Sidebar (collapsible, role-aware nav from `nav-config.ts`), topbar with NotificationBell (shadcn DropdownMenu + JSON feed endpoint), user menu (avatar/role/office), ModeToggle (light/dark/system), deadline banner (topbar badge + banner), flash → toasts
- [x] `GuestLayout` for auth pages (Breeze, Phase 5 restyle)
- [x] Shared props via `HandleInertiaRequests` (Phase 3): auth.user, unreadCount, deadline, flash
- [x] Loading states: Inertia top progress bar (`#0b4aa2`)

### 2.3 Foundations per [Frontend.md](./Frontend.md)
- [x] `components/ui/*`: button, card, input, badge (+warning/success variants), table, dialog, dropdown-menu, tabs, skeleton, alert, sonner, avatar, separator, tooltip, label, select, sheet, sidebar
- [x] `components/app/*` (app-level): theme-provider, mode-toggle, notification-bell, nav-config; `hooks/use-mobile` (useSyncExternalStore)
- [x] `lib/utils.ts` (cn), `types/` typed (User role/office, DeadlineState, FlashMessages)

### 2.4 Quality
- [x] A11y checks are **manual** (no Playwright infra on the LAN deployment): keyboard-complete shadcn primitives, focus rings, labels — verified in shell + manual checklist in UX.md §10
- [x] Bundle budget: initial JS ≤ 250 kB gzip — `scripts/bundle-budget.mjs` + CI step (**140.3 kB gzip today**)

---

## 3. Definition of Done / acceptance criteria

- [x] `npm run build` clean; Lighthouse — **manual pass in Phase 8** (single LAN host, no CI infra); bundle budget enforced (140.9 kB gzip initial)
- [x] Dark mode toggle persists (localStorage `pgs-theme`) and respects OS preference
- [x] Keyboard: shell navigable (shadcn primitives are keyboard-complete: focus traps, esc, arrows)
- [x] No CDN `<script>`/`<link>` anywhere; Inter and all components served locally (bunny CDN font links removed)
- [x] CI green incl. ESLint/Prettier/bundle-budget (axe in Phase 8)

---

## 4. Risks & mitigations

| Risk | Mitigation |
|---|---|
| shadcn init breaks on Laravel's default Vite config | Follow `tailwind-v4-shadcn` skill; spike PR first |
| Tailwind v4 vs v3 docs confusion (dark mode, `@theme`) | Lock to v4 syntax only; note traps in [Frontend.md](./Frontend.md) |
| Team unfamiliar with shadcn patterns | Dogfood on shell components; every UI PR includes a short pattern example |

---

## 5. Exit criteria

Shell ships with CI gates; any new page is a drop-in addition. Phase 5 starts rendering real features.

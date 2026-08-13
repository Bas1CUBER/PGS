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
- [ ] `npm i` Vite + React plugin; `laravel-vite-plugin` configured
- [ ] TypeScript `strict: true`, `noUncheckedIndexedAccess: true`, `verbatimModuleSyntax`
- [ ] ESLint flat config: typescript-eslint recommended-type-checked, react-hooks, react-refresh; zero-warning policy
- [ ] Prettier config; CI `--check`
- [ ] `npx shadcn@latest init` → Tailwind v4 CSS variables setup (see skill `tailwind-v4-shadcn`; do not follow Next.js tutorials)
- [ ] `npm i @fontsource-variable/inter`; `font-sans` token → Inter; `font-display: swap`

### 2.2 App shell
- [ ] `app.tsx` root + Inertia `createInertiaApp` with TS-typed `PageProps`
- [ ] `AuthenticatedLayout`: sidebar (role-aware nav from one config array), topbar with notification dropdown (shadcn `DropdownMenu`), user menu, deadline banner slot, flash messages
- [ ] `GuestLayout` for login/reset pages
- [ ] Shared props via `HandleInertiaRequests` middleware: `auth.user`, `auth.roles`, `flash`, `unread_notifications`, `deadline`
- [ ] Breadcrumbs, page titles, loading states (Inertia `onStart/onFinish` + top progress bar)

### 2.3 Foundations per [Frontend.md](./Frontend.md)
- [ ] `components/ui/*` shadcn primitives: Button, Input, Card, Table, Dialog, DropdownMenu, Tabs, Badge, Skeleton, Alert, Toast
- [ ] Shared `components/app/*`: PageHeader, StatCard, EmptyState, DataTable wrapper, UploadDropzone, StatusBadge, ConfirmDialog
- [ ] `lib/utils.ts` (cn), typed `types/` for shared domain shapes, `hooks/` (useDebounce, usePolling for notifications)

### 2.4 Quality
- [ ] axe-core automated checks in CI against shell pages
- [ ] Storybook? — optional; **skip** unless team grows (recorded as ADR)
- [ ] Bundle budget: initial JS ≤ 250 kB gzip — enforced by CI (see [Optimization.md](./Optimization.md))

---

## 3. Definition of Done / acceptance criteria

- [ ] `npm run build` clean; Lighthouse shell ≥ 95 performance/a11y on mobile preset
- [ ] Dark mode toggle persists and respects OS preference
- [ ] Keyboard: full shell navigable without mouse; focus rings visible
- [ ] No CDN `<script>`/`<link>` anywhere; Inter and all components served locally
- [ ] CI green including ESLint/Prettier/axe

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

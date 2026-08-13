# Frontend Standards

React 19 + TypeScript + Inertia.js 2 + Tailwind CSS v4 + shadcn/ui + Vite. This file is the single source of truth for frontend conventions.

---

## 1. Stack & versions

| Tool | Version | Notes |
|---|---|---|
| React | 19.x | Server-rendered by Inertia; client components only |
| TypeScript | 5.8+ | `strict: true`, `noUncheckedIndexedAccess: true`, `verbatimModuleSyntax` |
| Inertia.js | 2.x | `createInertiaApp`, typed `PageProps`, `usePage`, `useForm`, `router` |
| Tailwind CSS | 4.x | `@theme` + CSS variables; **v4 syntax only** (dark mode via `@custom-variant`, not `dark:` class config from v3) |
| shadcn/ui | latest | Copy-paste components into `components/ui`; never edit primitives — extend via `components/app` wrappers |
| Vite | 6.x | `laravel-vite-plugin`; assets hashed; CSP-ready |
| Font | Inter Variable | `@fontsource-variable/inter`, imported in `resources/css/app.css`, mapped to `--font-sans` |
| Charts | Recharts | Single chart library (replaces Chart.js); wrapped in `TrendChartCard` |

---

## 2. Directory layout

```
resources/
├── js/
│   ├── app.tsx                    # createInertiaApp bootstrap
│   ├── css/app.css                # Tailwind import + @theme tokens + Inter
│   ├── types/index.ts             # shared domain types (mirrors backend enums)
│   ├── lib/utils.ts               # cn(), formatters (dates, numbers, weights)
│   ├── hooks/                     # useDebounce, usePolling, usePermission, useToast
│   ├── components/
│   │   ├── ui/                    # shadcn primitives (generated, untouched)
│   │   └── app/                   # app components: PageHeader, StatCard, EmptyState,
│   │                              #   DataTable, UploadDropzone, StatusBadge,
│   │                              #   ConfirmDialog, TrendChartCard
│   ├── layouts/                   # AuthenticatedLayout, GuestLayout
│   └── pages/                     # one folder per module, one file per route
│       ├── Auth/  Dashboard/  Users/  Roadmaps/  Deliverables/ ...
```

- **One page component per route**, named after the route (`users/index.tsx`, `users/create.tsx`).
- **No business logic in components** — format data in a `transform` helper or server-side; components render.

---

## 3. Conventions

### 3.1 Forms
- Use Inertia `useForm` (not axios/state) for every form — inherits CSRF, validation, and history semantics.
- Form fields: `<Input>`, `<Select>` from shadcn wrapped with `<FormField>`, server errors from `errors` map shown inline.
- Validation lives server-side (Form Requests); client-side only for UX (max length hints).

### 3.2 State
- Server state via Inertia props. Local ephemeral UI state via React hooks only.
- No Redux/Zustand unless cross-page shared state is proven necessary (record ADR first).
- Refetch via `router.reload({ only: [...] })` — never full page reloads for partial data.

### 3.3 Styling
- Tailwind utilities only; component styles inside shadcn `components/ui` tokens.
- Custom CSS allowed **only** in `app.css` design-token layer or print styles — never random `<style>` blocks in pages.
- Dark mode: use tokens (`bg-background`, `text-foreground`), never hardcoded colors.
- The notification dropdown bug from the legacy app (CSS file with broken brace swallowing styles) is impossible here: styles live with components and are built locally, not concatenated by hand.

### 3.4 Accessibility
- Dialog/Dropdown: shadcn Radix handles focus trap; verify with keyboard test.
- Icons: aria-hidden + label on interactive elements.
- Toasts: `role="status"`; destructive toasts `role="alert"`.
- Color contrast must meet WCAG 2.1 AA against tokens (see [Design.md](./Design.md)).

### 3.5 Error states
- Every data view has: loading skeleton, empty state (icon + action), error state (retry button).
- `PageError` boundary component catches render errors and logs them (Laravel log; no Sentry on LAN).

---

## 4. Routing & navigation

- All navigation via Inertia `router.visit`/`<Link>`; no raw `<a href>` for internal routes.
- Sidebar menu config: single array in a shared module (like legacy `navbar.php` `$menus`, but typed), role-filtered via `usePermission`.
- Active state via `route().current()` — replace legacy `aria-current` basename hack.

---

## 5. Performance rules

- Route-level code splitting (Inertia auto); charts/PDF libs lazy-loaded per page.
- Bundle budget: initial JS ≤ 250 kB gzip; enforced in CI (see [Optimization.md](./Optimization.md)).
- Tables: server-side pagination/sort/search via Inertia `preserveState` + `preserveScroll`; never load whole tables.
- Images: use the media pipeline; explicit `width`/`height` or `aspect-ratio` to avoid CLS.

---

## 6. Testing

- Component tests: Vitest + React Testing Library for `components/app` logic.
- Verification: feature tests (Pest) for critical flows; manual LAN smoke checklist.
- CI runs all; axe-core scans on key routes.

# Inertia Migration — Spec 1: Foundation & Template

**Date:** 2026-07-14
**Status:** Approved (design)
**Part of:** FilamentPHP → React/Inertia migration (Spec 1 of 9)

## Background

Inventorix is a self-hostable asset/inventory manager on Laravel 13 + Filament 5.
The Filament layer auto-generates the entire admin UI: 7 CRUD resources, a
dashboard with 5 widgets, 8 evaluation reports, a settings cluster, and
hardware/browser features (QR generation, camera scanning, WebUSB label
printing, Microsoft Entra SSO).

We are migrating the **presentation layer** to React + Inertia for full UI/UX
control, richer client-side interactivity, and a true SPA feel. The Laravel
backend (models, services, jobs, policies, PDF, mail) is framework-agnostic and
stays largely as-is.

### Migration strategy: strangler-in-place

The whole migration runs inside this repo, keeping the app shippable throughout:

- The existing Filament panel moves from path `/app` to `/app-old`.
- The new React/Inertia app is built at `/app`.
- Both run side by side against the same DB, session, and auth guard, so every
  new screen can be diffed against its Filament equivalent.
- Filament is deleted at cutover (Spec 9).

### Roadmap (each is its own spec → plan → build cycle)

1. **Foundation & template** *(this spec)* — coexistence, stack, app shell,
   shared UI kit, auth, and Manufacturers migrated end-to-end as the reference.
2. Lookup resources — Places, Asset Types, Asset Models.
3. Users — CRUD, roles/policies, assets relation.
4. Assets — table+filters, form, detail, importer/exporter, QR-print,
   attachments/history/incidents panels.
5. Handovers — wizard, signature pad, PDF, signed email.
6. Dashboard & Reports — 5 widgets + 8 evaluation reports.
7. Settings — mail / warranty / general / storage / auth.
8. QR & label printing — generator, scanner, WebUSB (rewire existing TS).
9. Cutover — delete Filament, move `/app` → root, prune deps.

## Locked decisions

- **Strategy:** strangler-in-place; Filament → `/app-old`, new app → `/app`.
- **Stack:** Laravel React starter kit conventions → Inertia v2 + React 19 +
  shadcn/ui + Tailwind 4 + TypeScript.
- **Shell:** grouped left sidebar + top bar (search, user menu, dark-mode toggle).
- **Style:** clean neutral (zinc), light + dark, monochrome accent, no brand color.
- **Reference resource:** Manufacturers.
- **MFA:** deferred to a later spec (stays on `/app-old` in the meantime).
- **Tooling:** all `pnpm`/`node`/`php`/`artisan` commands run via `ddev exec …`.

## Goals

- `/app-old` serves the untouched Filament panel; `/app` serves a working
  React/Inertia shell — both functional at the same time.
- A polished, reusable app shell and design system (grouped sidebar, top bar,
  light/dark) matching the approved "clean neutral" direction.
- Two reusable building blocks proven end-to-end: a **server-driven DataTable**
  and a **CRUD form kit**.
- Manufacturers fully migrated to Inertia as the reference implementation later
  specs copy.
- Shared session auth: an Inertia login page + Entra SSO button; logging in on
  either app authenticates both.

## Non-goals

- Migrating any resource other than Manufacturers.
- MFA on the Inertia side.
- Changing root `/` routing or removing Filament (Spec 9).
- Any backend/domain-model changes beyond what Manufacturers needs.

## Design

### 1. Coexistence & routing

- In `app/Providers/Filament/AppPanelProvider.php`, change **only**
  `->path('app')` to `->path('app-old')`. Keep panel **id** `app` so all
  `filament.app.*` route names (e.g. `filament.app.auth.login`) keep resolving —
  they simply live under `/app-old/*` now. Filament remains `->default()`.
- `bootstrap/app.php` `redirectGuestsTo(route('filament.app.auth.login'))` and
  the `/` redirect keep working unchanged (they resolve to `/app-old/...`).
- New Inertia routes are grouped under the `/app` prefix in `routes/web.php`
  (or a dedicated `routes/app.php` included from `web.php`), behind `auth` +
  `ApplyRuntimeSettings` middleware.
- No path collision: `/app-old` (Filament) and `/app` (Inertia) are distinct.

### 2. Stack install (grafted into the existing repo)

The starter kit is normally a fresh scaffold; we graft its structure in instead
of running the installer against an existing app.

- **Composer:** add `inertiajs/inertia-laravel`, `tightenco/ziggy`.
- **pnpm:** add `@inertiajs/react`, `react`, `react-dom`, `@vitejs/plugin-react`,
  and dev types (`@types/react`, `@types/react-dom`).
- **Server glue:** `App\Http\Middleware\HandleInertiaRequests` (shares auth user,
  flash, ziggy routes); registered in `bootstrap/app.php` on the `web` group.
- **Root view:** `resources/views/app.blade.php` (Inertia root) — separate from
  Filament's Blade shell.
- **Vite:** add a **separate Inertia entry** (`resources/js/app.tsx` +
  `resources/css/app-inertia.css`) alongside the existing Filament entries in
  `vite.config.ts`, so Filament's compiled CSS/JS is untouched. React plugin
  added; Filament's asset registration in `AppPanelProvider` stays as-is.
- **shadcn/ui:** initialized with neutral/zinc base and the `class` dark
  strategy; `components.json`, `lib/utils.ts` (`cn`), Tailwind 4 tokens.

**Risk — Tailwind/CSS isolation:** Filament v5 ships its own compiled CSS. The
Inertia app must use its own CSS entry and must not restyle Filament. Vite
multi-entry keeps them separate; verify both panels render correctly after setup.

### 3. App shell & design system

- **Layout components** (`resources/js/layouts/`): `AppLayout` (sidebar + topbar +
  content slot), `Sidebar` (grouped nav rendered from a typed TS nav config),
  `TopBar` (global-search stub, `UserMenu`, `ThemeToggle`), `Breadcrumbs`.
- **Nav config:** a single typed array describing groups → items (label, icon,
  route, active-match) so later specs add entries in one place.
- **shadcn primitives** to install now (repo-wide reuse): button, input, label,
  select, checkbox, dialog, dropdown-menu, sheet, table, badge, sonner (toast),
  tooltip, avatar, card, skeleton, separator, pagination.
- **Theme:** neutral/zinc tokens, monochrome accent, light + dark via `class`;
  preference persisted (localStorage + SSR-safe initial class) and toggled from
  the top bar.
- **Placeholder dashboard** (`/app`): a simple Inertia page proving the shell and
  the server↔client round-trip render correctly.

### 4. Shared building blocks

#### DataTable (server-driven)

Replaces Filament tables. Client: TanStack Table (headless) rendered with the
shadcn table primitives — sortable column headers, a search box, per-column
filters, pagination controls, row-selection checkboxes, and a bulk-actions bar.

**State lives in the URL query string** and is resolved server-side, so tables
are shareable, bookmarkable, and back-button friendly:

```
/app/manufacturers?search=acme&sort=-name&page=2&perPage=25
```

Server: a small reusable helper (e.g. `App\Support\Table\TableQuery`) that reads
`search`, `sort`, `filters`, `page`, `perPage` from the request, applies them to
an Eloquent query (whitelisted sortable/searchable columns per resource), and
returns a paginator the controller hands to Inertia. Column definitions live on
the React side; the searchable/sortable whitelist lives on the server side.

#### CRUD form kit

- react-hook-form + zod for client ergonomics, but **Laravel validation is the
  source of truth** — server `ValidationException` errors flow back through
  Inertia's `errors` bag and map onto fields.
- Field components (text, textarea, select, etc.) + a form shell that can render
  inside a page or a shadcn `Sheet` (slide-over).
- Standard submit/cancel/loading + toast-on-success conventions.

#### Resource page conventions

Typed Inertia controller pattern + index/create/edit page skeletons, so a new
resource is mostly: migration-free model reuse + a controller + column config +
zod schema.

### 5. Reference resource — Manufacturers

Full end-to-end slice proving the kit:

- `App\Http\Controllers\App\ManufacturerController` — `index` (paginated via
  `TableQuery` → Inertia table page), `create`/`store`, `edit`/`update`,
  `destroy` + bulk `destroy`, each authorized via the existing policy.
- React pages under `resources/js/pages/manufacturers/` using `DataTable` and the
  form kit.
- zod schema mirroring the Laravel validation rules.
- Reuses the existing `Manufacturer` model, factory, and policy — no schema
  changes.

### 6. Auth

- Reuse the existing **web session guard**. Because Filament and Inertia share
  the session, logging in on either app authenticates both.
- **Inertia login page** at `/app/login` (namespaced under the new app, reachable
  while unauthenticated): email + password against the web guard, honoring the
  `login_enabled` gate (mirrors Filament's `canAccessPanel`). Failed/disabled
  logins get the same treatment as Filament.
- **Entra SSO button** wired to the existing `MicrosoftAuthController`
  redirect/callback routes (no backend change).
- `/app` routes protected by `auth`; unauthenticated access to `/app` redirects
  to the Inertia login.
- **MFA deferred:** Filament app-authentication MFA remains active on `/app-old`;
  re-implementing MFA on the Inertia login is a later spec.

### 7. Testing & verification

- **Pest feature tests:** Manufacturer controller — index (search/sort/paginate),
  store/update (incl. validation failures), destroy + bulk destroy, auth/policy
  enforcement; login (success, wrong password, `login_enabled=false`).
- **Vitest:** `DataTable` behavior (sort toggling, query-string sync, selection).
- **Manual verify (ddev, browser):** `/app` shell renders in light + dark;
  Manufacturers list/create/edit/delete works; `/app-old` Filament still fully
  functional and unrestyled.

## Open questions / follow-ups (not this spec)

- MFA re-implementation on the Inertia login (later spec).
- Global search wiring (stubbed here; real implementation later).
- Root `/` routing flip and Filament removal (Spec 9).
- Carrying over Entra-only / SSO-forced login policies if configured.

# Inertia Migration — Spec 6a: Dashboard

**Date:** 2026-07-14
**Status:** Approved (design)
**Part of:** FilamentPHP → React/Inertia migration — Spec 6 (Dashboard & Reports), sub-spec **6a of 6a–6b**
**Depends on:** Specs 1–5 (shell, auth, Assets/Incidents/Attachments, `attachments.open` route). Filament remains at `/app-old`. Spec **6b** (the 8 evaluation reports) follows.

## Background

The `/app` dashboard is still the Spec 1 placeholder (four "—" KPI cards). This
sub-spec makes it real by reproducing the five Filament dashboard widgets:

- `StatsWidget` — Assets / Licences / Total counts. **Licences is hardcoded `0`
  (no Licence model exists)**, so Total == Assets. Per the decision below, only
  the Assets count is shown (Licences + the now-redundant Total are dropped).
- `WarrantyStatsWidget` — `expired` (guarantee_end `< today`), `soon_30`
  (`today..+30`), `soon_90` (`today..+90`) counts.
- `LatestDocumentsTableWidget` — `Attachment` where `type='document'`, `->latest()`.
- `OpenIncidentsTableWidget` — `Incident` `whereNull('closed_date')->orderByDesc('open_date')`.
- `WarrantyExpiringTableWidget` — `Asset` `whereNotNull('guarantee_end')->orderBy('guarantee_end')`.

No charts, no new dependencies (matches Filament, which used stat + table widgets).

## Locked decisions

- Stat cards + mini-tables only (no charting library).
- **Drop the Licences and Total cards** (Licences has no model → 0; Total then
  duplicates Assets). Show a single **Assets** count card + the three warranty cards.
- Mini-table lists limited to **10** rows each (latest/soonest).
- Warranty cards are **not** linked in 6a (they'll link to the guarantee-status
  report once 6b builds it).
- No policy (gate on `auth`); PHPUnit; Vitest; ddev; pnpm.

## Goals

- The `/app` dashboard shows live stats + three recent-activity mini-tables,
  replacing the placeholder. `/app-old` Filament dashboard unchanged.

## Non-goals

- Charts/charting lib; a Licences feature; per-widget pagination/refresh; the
  guarantee-status report link (6b); the 8 reports (6b). No DB/schema changes.

## Design

### Backend — `App\Http\Controllers\App\DashboardController@index`

Replace the `/app` route's inline `Inertia::render('dashboard')` closure with
`DashboardController@index` (route name `app.dashboard`, authenticated group),
returning:

- **`stats`**: `{ assets: Asset::count() }`.
- **`warranty`**: `{ expired, soon_30, soon_90 }` — computed with `$today =
  now()->startOfDay()`:
  - `expired`: `Asset::whereNotNull('guarantee_end')->whereDate('guarantee_end','<',$today)->count()`
  - `soon_30`: `... ->whereDate('guarantee_end','>=',$today)->whereDate('guarantee_end','<=',$today->copy()->addDays(30))->count()`
  - `soon_90`: same with `addDays(90)`.
- **`latestDocuments`** (limit 10): `Attachment::where('type','document')->with('uploadedBy')->latest()->limit(10)->get()`
  → `{ id, title, category_label: category?->getLabel(), attached_to: class_basename(attachable_type), uploaded_by: uploadedBy?->name, created_at: created_at?->format('d.m.Y H:i'), url: route('attachments.open', $a) }`.
- **`openIncidents`** (limit 10): `Incident::whereNull('closed_date')->with('asset.model')->orderByDesc('open_date')->limit(10)->get()`
  → `{ id, title, model: asset?->model?->name, serial: asset?->serial_number, open_date: open_date?->format('d.m.Y'), days_open: open_date ? open_date->diffInDays(now()) : null, asset_url: asset ? "/app/assets/{asset_id}" : null }`.
- **`warrantyExpiring`** (limit 10): `Asset::whereNotNull('guarantee_end')->with('owner','model')->orderBy('guarantee_end')->limit(10)->get()`
  → `{ id, owner: owner?->name, model: model?->name, serial: serial_number, guarantee_end: guarantee_end?->format('d.m.Y'), days_left: guarantee_end ? now()->startOfDay()->diffInDays($a->guarantee_end, false) : null, asset_url: "/app/assets/{id}" }`.

`days_left` is signed (negative = past). `days_open` is a positive count.

### Frontend — `resources/js/pages/dashboard.tsx`

Rebuild the placeholder page (keep `AppLayout`, title "Dashboard"):

- **Stat cards** (a responsive grid): **Assets** (plain), **Warranty expired**
  (destructive tone), **≤ 30 days** (amber), **≤ 90 days** (blue) — using the
  same Tailwind badge/tone idiom as the asset `StateBadge`.
- **Three mini-table Cards** (each a titled `Card` with a compact `Table` and an
  empty state):
  - *Latest documents* — Title, Category, Attached to, Uploaded by, Date; each
    row's title links (new tab) to `url` (the `attachments.open` stream).
  - *Open incidents* — Title, Model, Serial, Opened, Days open; row links to
    `asset_url` (asset detail) when present.
  - *Warranty expiring* — Owner, Model, Serial, Guarantee end, Days left; row
    links to `asset_url`.

Props: `{ stats: { assets: number }, warranty: { expired: number; soon_30: number; soon_90: number }, latestDocuments: Doc[], openIncidents: Incident[], warrantyExpiring: Warranty[] }`.

### Reuse

`Card`, `Badge`, `Table` primitives; `attachments.open`; the asset detail route.
No new deps.

## Testing

- **PHPUnit `DashboardControllerTest`**:
  - `stats.assets` equals the created asset count.
  - warranty buckets: seed assets with `guarantee_end` yesterday / +10d / +60d /
    +200d → `expired=1`, `soon_30=1`, `soon_90=2` (the +60 is within 90 but not
    30; the +200 is outside) — assert exact counts.
  - `latestDocuments` returns only `type='document'` attachments, newest first,
    capped at 10, with the mapped shape (`url`, `attached_to`).
  - `openIncidents` excludes closed incidents, orders by `open_date` desc, caps
    at 10.
  - `warrantyExpiring` orders by `guarantee_end` asc, only rows with a
    `guarantee_end`, caps at 10.
  - guest → redirect.
- **Vitest `dashboard.test.tsx`** (light): renders the Assets stat value and the
  three warranty values from props; renders a row in each mini-table; shows the
  empty state when a list is empty.

## Open questions / follow-ups (later)

- Link the warranty cards to the guarantee-status report (6b).
- A Licences feature (would restore the Licences/Total cards).
- 6b: the 8 evaluation reports (shared framework + filter/table/export UI).

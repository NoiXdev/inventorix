# Inertia Migration — Spec 2: Lookup Resources

**Date:** 2026-07-14
**Status:** Approved (design)
**Part of:** FilamentPHP → React/Inertia migration (Spec 2 of 9)
**Depends on:** Spec 1 (Foundation) — app shell, `TableQuery`, `DataTable`, CRUD form kit, shared auth. Filament remains at `/app-old`.

## Background

Spec 1 delivered the React/Inertia foundation at `/app` and migrated **Manufacturers**
end-to-end as the reference resource (server-driven `DataTable`, CRUD form kit,
session auth). Spec 2 migrates the three remaining lookup resources —
**Places**, **Asset Types**, **Asset Models** — reusing those patterns.

Places and Asset Types are trivial (`name`-only, near-identical to Manufacturers).
Asset Models is the substantive one: it has a `belongsTo` Manufacturer, so it is
the reference for **relation-backed resources** that Users and Assets will build
on. Reaching Filament parity for Asset Models requires extending three reusable
primitives, which is the real work of this spec.

## Locked decisions

- **Full parity for Asset Models:** manufacturer Select in the form; table shows
  name + `manufacturer.name` (sortable + searchable) + `assets_count`; filter by
  manufacturer.
- **`TableQuery` stays generic:** relation columns are handled by the controller
  pre-joining and selecting an aliased column (e.g. `manufacturer_name`);
  `TableQuery` only ever sorts/searches/filters on columns present in the query.
  No generic relation-introspection inside the helper.
- **Only the select-filter** of the deferred `DataTable` feature set is built now
  (Asset Models needs it). Per-column text filters, row selection, and bulk
  destroy remain deferred.
- No per-resource policies (gate on `auth`, matching Manufacturers).
- All commands via ddev; PHP tests are PHPUnit; JS tests are Vitest; pnpm.

## Goals

- Places, Asset Types, Asset Models fully usable at `/app`, added to the sidebar
  Inventory group, with `/app-old` Filament still available for comparison.
- Three reusable primitives extended and proven: `TableQuery` filtering,
  `DataTable` select-filters, and a form-kit `SelectField`.
- Asset Models reaches parity with its current Filament table/form.

## Non-goals

- Relation managers / nested sub-tables (Filament `ModelsRelationManager`,
  `AssetsRelationManager`) — deferred to detail/infolist views (Assets spec or later).
- Inline "create manufacturer from the model form" (Filament `createOptionForm`) —
  Asset Models picks from existing manufacturers only.
- Per-column text filters, row-selection checkboxes, bulk destroy — still deferred.
- Any change to Users, Assets, Handovers, reports, settings, or Filament.

## Design

### Reusable primitives (extended)

#### `TableQuery` — add `filterable`

Add a chainable `->filterable(array $filters)` that reads `filter[<key>]`
from the request query string and applies a whitelisted exact-match `where` for
each present, non-empty value:

```
/app/asset-models?filter[manufacturer_id]=<uuid>&sort=-manufacturer_name&search=x
```

- `$filters` accepts either a plain list (`['status']` → request key `status`
  applied to column `status`) or an associative map to qualify the column
  (`['manufacturer_id' => 'asset_models.manufacturer_id']` → request key
  `manufacturer_id` applied to the qualified column). This keeps request keys
  clean while avoiding join ambiguity.
- Only keys in the whitelist are honored (others ignored, no error).
- Composes with the existing `searchable`/`sortable`/`paginate`; the paginator
  keeps the full query string via the existing `appends($request->query())`.
- Filter values are bound parameters. Column identifiers come from the
  developer whitelist only.

Relation sort/search need **no new `TableQuery` code**: the controller joins and
aliases the related column, so `searchable(['manufacturers.name'])` /
`sortable(['manufacturer_name'])` operate on columns already in the query. The
existing grammar-wrapping + LIKE-escape logic covers `table.column` identifiers.

#### `DataTable` — add `filters` prop

Add an optional prop `filters?: { key: string; label: string; options: { value: string; label: string }[]; active: string | null }[]`.
For each entry, render a shadcn `Select` in the toolbar (beside the search box).
Changing a filter calls `visitTable(baseUrl, { 'filter[<key>]': value })`
(empty value clears it), which — via the existing `buildTableUrl` — resets
`page` to 1 and preserves other params. Search, sortable headers, and pagination
are unchanged. Tables without `filters` render exactly as today.

#### Form kit — add `SelectField`

A reusable field mirroring `TextField`'s contract, built on shadcn `Select`:

```
SelectField props: {
  id: string; label: string;
  value: string; onChange: (v: string) => void;
  options: { value: string; label: string }[];
  error?: string; required?: boolean; placeholder?: string;
}
```

Renders label (+ required marker), the Select, and a `FormError` for `error`.

### Resources

All controllers live under `App\Http\Controllers\App`, registered inside the
existing authenticated `/app` route group as `Route::resource(...)->except('show')`
with names `app.places.*`, `app.asset-types.*`, `app.asset-models.*`. React pages
live under `resources/js/pages/{places,asset-types,asset-models}/`. Each resource
is added to the `navGroups` Inventory group.

#### Places (and Asset Types — identical shape)

- Model: `Place` / `AssetType`, UUID pk, fillable `name`.
- `index`: `TableQuery::for(Place::query(), $request)->searchable(['name'])->sortable(['name'])->paginate()`,
  returning `places: { data: [{id,name}], meta }`.
- `create`/`store`, `edit`/`update`, `destroy`. Validation: `name` =
  `required|string|max:255` (a `PlaceRequest` / `AssetTypeRequest` FormRequest,
  `authorize()` returns true).
- React: `index` (DataTable, columns `name` + row actions, sortable `['name']`),
  `create`/`edit` (a shared `<resource>-form` using `TextField`).

#### Asset Models (relation-backed reference)

- Model: `AssetModel`, fillable `name`, `manufacturer_id`; `manufacturer()`
  belongsTo, `assets()` hasMany (already present).
- `AssetModelController@index`:

  ```php
  $query = AssetModel::query()
      ->leftJoin('manufacturers', 'manufacturers.id', '=', 'asset_models.manufacturer_id')
      ->withCount('assets')
      ->select(
          'asset_models.id',
          'asset_models.name',
          'asset_models.manufacturer_id',
          'manufacturers.name as manufacturer_name',
      );

  $models = TableQuery::for($query, $request)
      ->searchable(['asset_models.name', 'manufacturers.name'])
      ->sortable(['asset_models.name', 'manufacturer_name', 'assets_count'])
      ->filterable(['manufacturer_id' => 'asset_models.manufacturer_id'])
      ->paginate();
  ```

  Row shape: `{ id, name, manufacturer_id, manufacturer_name, assets_count }`.
  Also passes `manufacturerOptions: { value: id, label: name }[]` (all
  manufacturers, ordered by name) for the table filter and the form Select.
- **Ambiguity rule:** because the `leftJoin` puts two `name` columns in scope,
  every sort/search token is qualified or aliased — the base model name is
  `asset_models.name`, the manufacturer name is `manufacturer_name` (the SELECT
  alias) for sorting and `manufacturers.name` for searching, and the count is
  the `withCount` alias `assets_count`. No bare `name` token is ever emitted.
- **DataTable sort token = column id.** Since Spec 1's `DataTable` derives the
  sort param from the TanStack column `id`, the Asset Models columns set explicit
  ids equal to their sort tokens (`asset_models.name`, `manufacturer_name`,
  `assets_count`) with an `accessorFn`/`cell` reading the matching row field
  (`row.name`, `row.manufacturer_name`, `row.assets_count`). Non-relation
  resources (Places, Asset Types) keep `name` as both id and token — no join,
  no ambiguity.
- `create`/`edit` pass `manufacturerOptions`; `edit` also passes
  `assetModel: { id, name, manufacturer_id }`.
- Validation (`AssetModelRequest`): `name` = `required|string|max:255`,
  `manufacturer_id` = `required|uuid|exists:manufacturers,id`.
- React: `index` DataTable with columns Name (`asset_models.name`), Manufacturer
  (`manufacturer_name`), Assets (`assets_count`, badge), all sortable;
  `filters=[{ key:'manufacturer_id', label:'Manufacturer', options: manufacturerOptions, active }]`.
  `create`/`edit` use a shared `asset-model-form` with `TextField` (name) +
  `SelectField` (manufacturer).

### Testing

- **PHPUnit** per controller:
  - Places / Asset Types: index (list, search, sort), store/update (incl.
    validation failure), destroy, auth redirect. (Mirror Manufacturers tests.)
  - Asset Models: index returns `manufacturer_name` + `assets_count` (with a
    fixture asserting exact counts and that the join doesn't duplicate rows);
    **filter by `manufacturer_id`** returns only matching models; sort by
    `manufacturer_name` orders correctly; search matches on both the model name
    and the manufacturer name; store/update validation including the
    `manufacturer_id` `exists` rule; destroy; auth.
  - `TableQuery`: a test for `filterable` (whitelisted exact match; unknown
    filter key ignored) and a relation sort/search test over a joined query.
- **Vitest**: `SelectField` (renders options, fires `onChange`, shows error);
  `DataTable` filter behavior (selecting a filter builds the correct
  `filter[key]=...` URL and resets page).

## Open questions / follow-ups (not this spec)

- Relation managers / detail views for these resources (later spec).
- Row selection + bulk destroy + per-column text filters across the DataTable
  (when a resource needs them).

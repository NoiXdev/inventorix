# Inertia Migration — Spec 4a: Assets Core (CRUD + Detail)

**Date:** 2026-07-14
**Status:** Approved (design)
**Part of:** FilamentPHP → React/Inertia migration — Spec 4 (Assets), sub-spec **4a of 4a–4e**
**Depends on:** Specs 1–3 (shell, `TableQuery` incl. `filterable`, `DataTable` incl. select-filters + sortable headers, form kit: Text/Select/Switch/Password, resource pattern, session auth). Filament remains at `/app-old`.

## Background

Assets is the core domain resource and far too large for one spec, so Spec 4 is
decomposed: **4a** core CRUD + a detail page; **4b** attachments panel; **4c**
incidents panel; **4d** history (activity-log) panel; **4e** import/export. The
Handover action is Spec 5; QR-print/label printing is Spec 8. This document
covers **4a** only.

`Asset` columns: `id` (uuid), `state` (enum `AssetState`, 8 values w/ German
labels + colors), `asset_type_id` (required FK), `owner_id` (nullable FK →
users), `place_id` (nullable FK), `model_id` (nullable FK → asset_models),
`serial_number`, `buy_date` (date), `buy_type` (enum `BuyType`, nullable),
`buy_price` (float, nullable), `guarantee_end` (date), `invoice`, timestamps.
`manufacturer_id` was moved off `assets` onto `asset_models` in an earlier
migration — an asset's manufacturer is reached via `model.manufacturer`. `Asset`
uses `HasUuids`, `HasTags` (spatie), `LogsActivity` (spatie — the 4d history),
`HasAttachments` (4b), `incidents()` hasMany, `handovers()` belongsToMany (Spec
5), and is `ObservedBy(AssetObserver)`.

Assigning an asset's **owner** in 4a completes the user↔assets assignment that
Spec 3 deferred.

## Locked decisions

- Decomposition 4a–4e, build 4a first (the detail page is where 4b/4c/4d mount).
- Detail page uses **tabs**: *General* + empty *Attachments* / *Incidents* /
  *History* tabs (filled by later sub-specs).
- **Tags included** in 4a (basic add/remove tag input, spatie `HasTags`).
- **No inline "create related"** (pick existing records only — matches Spec 2).
- **Replicate/duplicate action deferred** (YAGNI).
- `TableQuery` stays generic: the controller left-joins + aliases relation
  columns; every sort/search/filter token is qualified/aliased (no bare `name`).
- No policy (gate on `auth`); PHPUnit; ddev; pnpm.

## Goals

- Assets fully usable at `/app` — index (with state/type/manufacturer filters,
  relation columns, `incidents_count`), create/edit form (all core fields +
  tags), a **detail page** (`show`) with tabs, added to the sidebar Inventory
  group. `/app-old` Filament intact.
- Owner assignment works from the asset form.
- Form kit gains `DateField`, `NumberField`, `TagsInput`.

## Non-goals

- Attachments/incidents/history panel *contents* (empty tabs only here) → 4b–4d.
- Import/export → 4e. Handover action → Spec 5. QR-print → Spec 8.
- Inline creation of related records; the Replicate action; branded/i18n polish.
- Any change to Filament, other resources, or DB schema.

## Design

### Enums exposed to the frontend

- A small controller helper returns `stateOptions` and `buyTypeOptions` as
  `[{ value, label }]` (labels from the enums' `getLabel()`), used by the form
  Select and the table state-filter.
- **State badge color:** the enums' Filament `Color` doesn't map to CSS, so the
  frontend keeps a TS constant `STATE_BADGE` keyed by the 8 `AssetState` values →
  a shadcn Badge variant / Tailwind class. The row carries `state` (value) +
  `state_label`; the badge looks up color by value.

### Index (table)

Controller query (all left joins so nullable FKs don't drop rows):

```php
$query = Asset::query()
    ->leftJoin('asset_types', 'asset_types.id', '=', 'assets.asset_type_id')
    ->leftJoin('asset_models', 'asset_models.id', '=', 'assets.model_id')
    ->leftJoin('manufacturers', 'manufacturers.id', '=', 'asset_models.manufacturer_id')
    ->leftJoin('users', 'users.id', '=', 'assets.owner_id')
    ->withCount('incidents')
    ->select(
        'assets.id', 'assets.state', 'assets.serial_number', 'assets.buy_price',
        'assets.asset_type_id', 'assets.model_id', 'assets.owner_id',
        'asset_types.name as asset_type_name',
        'asset_models.name as model_name',
        'asset_models.manufacturer_id as manufacturer_id',
        'manufacturers.name as manufacturer_name',
        'users.name as owner_name',
    );

TableQuery::for($query, $request)
    ->searchable(['assets.serial_number', 'asset_types.name', 'manufacturers.name', 'asset_models.name', 'users.name'])
    ->sortable(['assets.state', 'asset_type_name', 'manufacturer_name', 'model_name', 'owner_name', 'assets.serial_number', 'assets.buy_price', 'incidents_count'])
    ->filterable([
        'state' => 'assets.state',
        'asset_type_id' => 'assets.asset_type_id',
        'manufacturer_id' => 'asset_models.manufacturer_id',
    ])
    ->paginate();
```

Row shape: `{ id, state, state_label, asset_type_name, manufacturer_name, model_name, owner_name, serial_number, buy_price, incidents_count }`.
Columns: State (badge via `STATE_BADGE`), Asset type, Manufacturer, Model, Owner,
Serial, Buy price, Incidents (badge). Sortable per the whitelist; the three
filters use `DataTable` select-filters with options: state → `stateOptions`,
asset type → all asset types, manufacturer → all manufacturers.
`manufacturer_name`/`model_name`/`owner_name` render `—` when null.

### Form & validation

React form fields (all options passed from the controller):

- `state` — `SelectField`, `stateOptions`, **required**.
- `asset_type_id` — `SelectField` (asset types), **required**.
- `owner_id` — `SelectField` (users, `"firstname lastname"` label), nullable
  (include an empty "—" option).
- `place_id` — `SelectField` (places), nullable.
- `model_id` — `SelectField` (asset models, `"(Manufacturer) Model"` label),
  nullable.
- `serial_number` — `TextField`.
- `buy_date`, `guarantee_end` — `DateField`.
- `buy_type` — `SelectField` (`buyTypeOptions`), nullable.
- `buy_price` — `NumberField`, nullable.
- `invoice` — `TextField`.
- `tags` — `TagsInput` (array of strings).

`AssetRequest` rules:
- `state` = `required`, `Rule::enum(AssetState::class)`.
- `asset_type_id` = `required|uuid|exists:asset_types,id`.
- `owner_id` = `nullable|uuid|exists:users,id`.
- `place_id` = `nullable|uuid|exists:places,id`.
- `model_id` = `nullable|uuid|exists:asset_models,id`.
- `serial_number` = `nullable|string|max:255`.
- `buy_date`, `guarantee_end` = `nullable|date`.
- `buy_type` = `nullable`, `Rule::enum(BuyType::class)`.
- `buy_price` = `nullable|numeric|min:0`.
- `invoice` = `nullable|string|max:255`.
- `tags` = `nullable|array`; `tags.*` = `string|max:255`.
- `authorize()` returns true.

Store/update use `$request->validated()` (minus `tags`) for the model; then
`$asset->syncTags($validated['tags'] ?? [])`. Empty-string select values are
normalized to `null` for nullable FKs (a `prepareForValidation` converts `''` →
`null`).

### Detail page (`show`)

`Route::resource('assets', AssetController::class)` **including** `show`
(Assets is the first resource with a detail page). `show` eager-loads
`assetType`, `model.manufacturer`, `owner`, `place`, `tags` and returns an
`asset` prop with all display fields + related names + `tags: string[]` +
timestamps + `incidentsCount`.

React `assets/show.tsx` uses shadcn `Tabs`:
- **General** — read-only display: state badge, type, manufacturer, model, owner,
  place, serial, buy date/type/price, guarantee end, invoice, tags (badges),
  created/updated. An "Edit" button → `/app/assets/{id}/edit`.
- **Attachments / Incidents / History** — empty tab panels with a
  "Coming soon" placeholder (filled by 4b/4c/4d). These tabs exist now so the
  later sub-specs only add panel bodies, not restructure the page.

### Reusable form-kit additions

- **`DateField`** — `{ id, label, value, onChange, error?, required? }`,
  `Input type="date"` + `Label` + `FormError` (value is the `YYYY-MM-DD` string).
- **`NumberField`** — `{ id, label, value, onChange, error?, required?, step?, min? }`,
  `Input type="number"` + `FormError`; `value`/`onChange` are strings (server
  casts). Default `step="0.01"` acceptable for prices, or pass explicitly.
- **`TagsInput`** — `{ id, label, value: string[], onChange: (v: string[]) => void, error? }`.
  Renders existing tags as removable chips + a text input; Enter/comma adds a
  trimmed non-empty, non-duplicate tag; clicking a chip's × removes it.

### Routes & nav

- `Route::resource('assets', AssetController::class)` (with `show`) in the
  authenticated `/app` group; names `app.assets.*`.
- Add **Assets** to the sidebar Inventory group (icon e.g. `Package`), first
  entry (it's the primary resource).

### Testing

- **PHPUnit** (`AssetControllerTest`), with real fixtures (Manufacturer →
  AssetModel, AssetType, Place, User, Incident):
  - index: relation columns present (`asset_type_name`, `manufacturer_name`,
    `model_name`, `owner_name`), `incidents_count` correct; search matches a
    relation column (e.g. manufacturer name); sort by `manufacturer_name`;
    **each filter** (state, asset_type_id, manufacturer_id) narrows correctly.
  - store: required `state`/`asset_type_id`; invalid enum `state` rejected;
    nullable FKs accept null and reject non-existent ids; `buy_price` numeric;
    dates validated; **tags synced** (create with tags → `$asset->tags` match);
    empty-string select normalized to null.
  - update: changes persist incl. tag add/remove.
  - show: returns the detail props incl. related names + `tags` array.
  - destroy: deletes (AssetObserver/attachments cascade unaffected — no
    attachments in fixture).
  - auth: unauthenticated `/app/assets` redirects.
- **Vitest:** `DateField` (renders date input, fires onChange), `NumberField`
  (type=number, onChange), `TagsInput` (add via Enter, dedupe, remove chip,
  onChange emits the array).

## Open questions / follow-ups (later sub-specs / specs)

- Attachments (4b), incidents (4c), history (4d), import/export (4e) panel bodies.
- Handover action (Spec 5); QR-print + the detail page's QR area (Spec 8).
- Replicate/duplicate action; branded/i18n labels; asset "name"/display string
  conventions if needed later.

# Inertia Migration — Spec 4e: Asset Import / Export

**Date:** 2026-07-14
**Status:** Approved (design)
**Part of:** FilamentPHP → React/Inertia migration — Spec 4 (Assets), sub-spec **4e of 4a–4e** (final)
**Depends on:** Spec 4a (Assets core, `TableQuery` usage, index page). Specs 1–3. Filament remains at `/app-old`.

## Background

The Filament Assets resource has a queued CSV importer (`AssetImporter`) and
exporter (`AssetExporter`). This sub-spec rebuilds import/export in the Inertia
app **synchronously** (no queue/imports-table/notification infra), which is
appropriate for self-hosted inventory sizes. It closes out Spec 4.

Canonical column set (snake_case machine headers, used by **both** export and
import so a file round-trips): `id, state, asset_type, manufacturer, model,
place, owner, serial_number, buy_date, guarantee_end, buy_price, buy_type, tags`.
Native `fputcsv`/`fgetcsv` — no new dependency.

The Filament importer's resolution rules (ported here): `state`/`buy_type` accept
the enum backing value **or** the German label; dates accept ISO `Y-m-d` or
German `d.m.Y`; `asset_type`/`place` are find-or-created by name; `manufacturer`+
`model` together (model requires a manufacturer; both find-or-created, model
scoped to its manufacturer); `owner` is resolved by display name or a
login-disabled `User` is auto-created; `tags` comma-split; a supplied `id` that
already exists fails the row, a new `id` is used as the asset UUID; per-row
failures are collected, not fatal.

## Locked decisions

- **Synchronous** export (streamed download) and import (processed in the request,
  returns a summary). Import **row cap 2000** (larger → rejected with a
  "split the file / queued import is a later spec" message).
- **Export respects the current index filters/search** (exports the filtered set).
- **Match Filament resolution:** find-or-create asset_type/manufacturer/model/place;
  auto-create login-disabled owner users; enum value-or-label; ISO/`d.m.Y` dates;
  id-collision = row error.
- **Fixed canonical headers** (no mapping UI). Import validates the header row.
- No policy (gate on `auth`); PHPUnit; Vitest; ddev; pnpm.

## Goals

- Export the (filtered) asset list to CSV from the index; import a CSV to
  create assets with resolved relations/tags, returning imported/failed counts +
  per-row errors. `/app-old` Filament import/export unchanged.

## Non-goals

- Queued processing; a downloadable failed-rows CSV; a column-mapping UI;
  update-existing-by-id (beyond the collision check); import/export for other
  resources. No DB schema changes; the Filament importer/exporter classes stay
  (used by `/app-old`).

## Design

### Canonical columns (shared)

A single source of truth for the header order + keys (a `const` array on a shared
class, e.g. `App\Support\Assets\AssetCsv::HEADERS`), reused by the exporter and
the import header validation:
`['id','state','asset_type','manufacturer','model','place','owner','serial_number','buy_date','guarantee_end','buy_price','buy_type','tags']`.

### Export — `AssetExportController@index`

- Route `GET /app/assets/export` (`app.assets.export`), authenticated group.
- Build the same query the index uses (reuse `TableQuery` with the request's
  `search`/`sort`/`filter[...]`), but call `->get()` (all matching rows, no
  pagination) after eager-loading `assetType`, `model.manufacturer`, `owner`,
  `place`, `tags`. **Manufacturer filter maps through `asset_models.manufacturer_id`**
  exactly as the index (share the same join/whitelist setup as
  `AssetController@index`; extract a small private helper or a query builder so
  both stay in sync).
- `return response()->streamDownload(function () use ($assets) { $out = fopen('php://output','w'); fputcsv($out, AssetCsv::HEADERS); foreach ($assets as $a) fputcsv($out, [...]); fclose($out); }, 'assets.csv', ['Content-Type' => 'text/csv']);`
- Row values: `id`; `state` → `$a->state->getLabel()`; `asset_type` →
  `$a->assetType?->name`; `manufacturer` → `$a->model?->manufacturer?->name`;
  `model` → `$a->model?->name`; `place` → `$a->place?->name`; `owner` →
  `$a->owner?->name`; `serial_number`; `buy_date`/`guarantee_end` →
  `optional(...)->format('Y-m-d')`; `buy_price`; `buy_type` →
  `$a->buy_type?->getLabel()`; `tags` → `$a->tags->pluck('name')->implode(', ')`.
- Index toolbar gains an **Export** button linking to `/app/assets/export` with
  the current query string appended (so it exports the filtered view). Because
  it's a file download, a plain `<a href>` (not an Inertia visit) is used.

### Import — `App\Support\Assets\AssetImport` service (the core)

A framework-plain, unit-testable service holding the resolution logic:

```php
final class AssetImport
{
    /** @param iterable<int, array<string,string>> $rows  keyed by canonical header
        @return array{imported:int, failed: array<int, array{row:int, message:string}>} */
    public function import(iterable $rows): array;
}
```

- For each row (1-indexed for error reporting, header row excluded), inside a DB
  transaction:
  - resolve `id`: if non-empty and an Asset with that key exists → throw
    `RowImportException("An asset with id [$id] already exists.")`; else new Asset
    (set the key if id provided).
  - `state` (required): value-or-label via `resolveEnum(AssetState, ...)` (unknown
    → throw). `buy_type`: value-or-label via `resolveEnum(BuyType, ...)` when present.
  - `asset_type` (required) → `firstOrCreateByName(AssetType)`.
  - `manufacturer`+`model`: if `model` present, require non-empty `manufacturer`
    (else throw); `firstOrCreateByName(Manufacturer)`, then find-or-create the
    model scoped to that manufacturer (case-insensitive name).
  - `place` → `firstOrCreateByName(Place)` when present.
  - `owner` → `resolveOwner(name)` (case-insensitive match, else create User with
    `login_enabled=false`, `splitName`) when present.
  - `serial_number`, `buy_price` raw (nullable); dates via `parseDate` (ISO or
    `d.m.Y`; invalid → throw).
  - save the asset; `syncTags(explode(',', tags) trimmed/filtered)`.
  - on success `imported++`; on `RowImportException` (or validation), record
    `failed[] = {row, message}` and roll that row back — continue others.
- Helpers (`resolveEnum`, `parseDate`, `firstOrCreateByName`, `resolveOwner`,
  `splitName`) mirror the Filament importer's behavior. Case-insensitive matching
  uses `whereRaw('LOWER(name) = ?', [mb_strtolower($name)])` (note: SQLite `LOWER`
  is ASCII-only — tests use ASCII names).

### Import — `AssetImportController@store`

- Route `POST /app/assets/import` (`app.assets.import`), authenticated group.
- `AssetImportRequest`: `file` = `required|file|mimes:csv,txt|max:5120`.
- Parse with `fgetcsv`: read the header row, **validate it equals
  `AssetCsv::HEADERS`** (order-insensitive membership at least; on mismatch
  redirect back with an error naming the expected columns). Read data rows into
  associative arrays keyed by header. Enforce the **2000-row cap** (over → redirect
  back with an error). Pass to `AssetImport::import()`.
- Redirect to the assets index with `->with('importResult', $result)`
  (`{imported, failed[]}`), flashing it to the session.
- **Delivery to the page:** `AssetController@index` adds
  `'importResult' => $request->session()->get('importResult')` to its Inertia
  props (present only on the request right after an import; null otherwise). This
  keeps the flash localized to the index rather than adding it to the global
  shared `flash`.

### Frontend

- **Assets index toolbar**: add **Export** (`<a href={`/app/assets/export${window.location.search}`}>`)
  and **Import** (opens a Dialog) buttons next to "New asset".
- **`asset-import-dialog.tsx`**: a shadcn `Dialog` with a file `<input type=file accept=".csv,.txt">`,
  a hint ("Expected columns: … — or use Export as a template"), and a submit that
  Inertia-`post`s the file to `/app/assets/import` with `forceFormData: true`,
  `onSuccess: () => { close; }`. Shows `form.errors.file`.
- **Result panel** on the index: when the `importResult` prop is present, render
  a dismissible `Card` — "Imported N, failed M" and, if `failed` non-empty, a
  scrollable list of `Row {row}: {message}`.
- The assets index page props gain an optional
  `importResult?: { imported: number; failed: { row: number; message: string }[] } | null`
  (from the flashed session value, per the controller change above).

### Reuse

`TableQuery` (export query), shadcn `Dialog`/`Button`/`Card`. The Filament
importer/exporter classes are left intact for `/app-old`.

## Testing

- **PHPUnit `AssetImportTest`** (service, primary coverage): enum value AND label;
  ISO + `d.m.Y` dates + invalid→failed; find-or-create asset_type/place;
  manufacturer+model (+ model-without-manufacturer → failed row); owner match +
  auto-create (login disabled, first/last split, `-` placeholder); tags synced;
  `id` present+new used as key; `id` existing → failed row; one malformed row
  fails while the other rows import (counts correct).
- **PHPUnit `AssetExportControllerTest`**: response is a CSV attachment whose
  first line is the canonical header and whose data row has resolved
  names/labels/ISO dates/joined tags; a `filter[state]=…` param limits the rows.
- **PHPUnit `AssetImportControllerTest`**: upload a valid CSV → redirect + an
  `importResult` with the right counts; a mixed file reports imported + failed;
  a wrong-header file is rejected with a clear error; > 2000 rows rejected;
  bad mime rejected; guest redirected.
- **Vitest**: import Dialog (file input present, submit posts) and the result
  panel (renders "Imported/failed" + per-row error lines; hidden when no result).

## Open questions / follow-ups (later)

- Queued import/export for very large files; downloadable failed-rows CSV;
  column-mapping UI; update-existing-by-id; import/export for other resources.
- Spec 4 is complete after this; the migration continues with Spec 5 (Handovers).

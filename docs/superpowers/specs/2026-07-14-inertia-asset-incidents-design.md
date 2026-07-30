# Inertia Migration — Spec 4c: Asset Incidents Panel

**Date:** 2026-07-14
**Status:** Approved (design)
**Part of:** FilamentPHP → React/Inertia migration — Spec 4 (Assets), sub-spec **4c of 4a–4e**
**Depends on:** Spec 4a (asset detail page + empty Incidents tab), 4b (attachments pattern for asset-scoped panels). Specs 1–3 (kit, auth). Filament remains at `/app-old`.

## Background

The asset detail page (`assets/show`) has an Incidents tab that is currently an
empty placeholder (the tab label already shows the incident count from 4a). This
sub-spec fills it with a CRUD panel.

`Incident` model: **bigint auto-increment `id`** (not UUID, unlike the rest of the
app), `asset_id` (FK, cascade delete), `title` (string, required), `notes` (text,
nullable), `open_date` (datetime, **NOT NULL**), `closed_date` (datetime,
nullable), timestamps. `belongsTo` asset; `LogsActivity` (logs
title/notes/open_date/closed_date). `Asset::incidents()` is `hasMany(...)->orderBy('open_date','asc')`.
An incident is **open** when `closed_date` is null, **closed** otherwise.

## Locked decisions

- Create/edit via a **modal Dialog** (shadcn `Dialog`, already available).
- Include one-click **Mark closed** (sets `closed_date = today`) / **Reopen**
  (clears it) quick actions + an Open/Closed status badge.
- Dates are **date-granularity** (`Y-m-d` via `DateField`), stored into the
  datetime columns (matches Filament's DatePicker).
- List order: keep the model's existing `open_date asc` ordering.
- Incidents ride on the `show` props; mutations are **asset-scoped** endpoints
  that redirect back (same pattern as 4b attachments).
- No policy (gate on `auth`); PHPUnit; ddev; pnpm.

## Goals

- The Incidents tab lists an asset's incidents and supports create/edit/delete +
  quick close/reopen, all inline. `/app-old` Filament incidents unchanged.

## Non-goals

- Incident attachments/comments; severity/assignment fields (not in the model);
  the History tab (4d) and import/export (4e).
- Any change to the `Incident` model/migration or the `Asset::incidents()` relation.
  No DB schema changes.

## Design

### Backend

**`AssetController@show`** — add an `incidents` prop (load the relation):

```php
$asset->load('assetType', 'model.manufacturer', 'owner', 'place', 'tags',
             'attachments.uploadedBy', 'incidents')->loadCount('incidents');
// …
'incidents' => $asset->incidents->map(fn (\App\Models\Incident $i) => [
    'id' => $i->id,
    'title' => $i->title,
    'notes' => $i->notes,
    'open_date' => optional($i->open_date)->format('Y-m-d'),
    'closed_date' => optional($i->closed_date)->format('Y-m-d'),
    'status' => $i->closed_date ? 'closed' : 'open',
    'created_at' => optional($i->created_at)->toDateTimeString(),
])->all(),
```

(Existing `asset`, `attachments`, `attachmentCategoryOptions` props unchanged.)

**`App\Http\Controllers\App\IncidentController`** (new):

- `store(IncidentRequest $request, Asset $asset)` — `$asset->incidents()->create($request->validated())`; redirect back with success.
- `update(IncidentRequest $request, Asset $asset, Incident $incident)` — guard
  (below), `$incident->update($request->validated())`; redirect back.
- `destroy(Asset $asset, Incident $incident)` — guard, `$incident->delete()`;
  redirect back.
- `close(Asset $asset, Incident $incident)` — guard, `$incident->update(['closed_date' => now()])`; redirect back.
- `reopen(Asset $asset, Incident $incident)` — guard, `$incident->update(['closed_date' => null])`; redirect back.
- **Ownership guard** (a private helper on all mutating actions):
  `abort_unless($incident->asset_id === $asset->id, 403)`.

**`IncidentRequest`**:
- `title` = `required|string|max:255`
- `open_date` = `required|date`
- `closed_date` = `nullable|date|after_or_equal:open_date`
- `notes` = `nullable|string`
- `authorize()` returns true.

**Routes** (authenticated `/app` group):

```php
Route::post('assets/{asset}/incidents', [IncidentController::class, 'store'])->name('assets.incidents.store');
Route::put('assets/{asset}/incidents/{incident}', [IncidentController::class, 'update'])->name('assets.incidents.update');
Route::delete('assets/{asset}/incidents/{incident}', [IncidentController::class, 'destroy'])->name('assets.incidents.destroy');
Route::post('assets/{asset}/incidents/{incident}/close', [IncidentController::class, 'close'])->name('assets.incidents.close');
Route::post('assets/{asset}/incidents/{incident}/reopen', [IncidentController::class, 'reopen'])->name('assets.incidents.reopen');
```

(`{asset}` binds by UUID, `{incident}` by bigint id.)

### Frontend

**`resources/js/components/assets/asset-incidents.tsx`** (new), rendered in
`assets/show.tsx`'s Incidents `TabsContent`, props `{ assetId: string; incidents: IncidentItem[] }`:

- **State:** a local `open`/`editing` state for the Dialog; an Inertia
  `useForm({ title, open_date, closed_date, notes })`.
- **"New incident"** button → opens the Dialog with an empty form (`open_date`
  defaults to today's `Y-m-d`); **Edit** on a row → opens it prefilled with that
  incident. Submit: create → `form.post('/app/assets/${assetId}/incidents')`;
  edit → `form.put('/app/assets/${assetId}/incidents/${id}')`; `onSuccess` closes
  the dialog + resets. Errors shown per field.
- **Dialog form** fields: `TextField` title (required), `DateField` open_date
  (required), `DateField` closed_date, `Textarea` notes.
- **List rows** (empty state when none): status `Badge`
  (`open` → default/secondary, `closed` → outline/muted), title, `open_date`,
  `closed_date` (or "—"), truncated notes, and actions:
  - Edit (opens dialog prefilled)
  - **Mark closed** (`status==='open'`) → `router.post('.../${id}/close')`
  - **Reopen** (`status==='closed'`) → `router.post('.../${id}/reopen')`
  - Delete → `router.delete('.../${id}')` behind `confirm`.

**`show.tsx`** change: add `incidents: IncidentItem[]` to the page props,
destructure it, render `<AssetIncidents assetId={asset.id} incidents={incidents} />`
in the Incidents tab (replace the placeholder). Keep the History placeholder.
`IncidentItem` = `{ id: number; title: string; notes: string | null; open_date: string | null; closed_date: string | null; status: 'open' | 'closed'; created_at: string | null }`.

### Reuse

No new primitives — `Dialog`, `DateField`, `Textarea`, `TextField`, `Badge`,
`FormError` all exist. No backend model/migration/relation changes.

## Testing

- **PHPUnit** (`IncidentControllerTest`):
  - store creates an incident linked to the asset; status is open when
    `closed_date` is null.
  - validation: `title` required; `open_date` required; `closed_date`
    `after_or_equal:open_date` (a closed_date before open_date is rejected).
  - update changes fields.
  - `close` sets `closed_date` (incident becomes closed); `reopen` clears it.
  - destroy removes the incident.
  - **ownership 403**: update/destroy/close/reopen of an incident belonging to a
    *different* asset → 403, row untouched.
  - auth: guest redirected.
  - `AssetController@show` returns the `incidents` prop (shape incl. `status`,
    `open_date`).
- **Vitest** (`asset-incidents.test.tsx`): renders Open vs Closed badges from
  status; "New incident" opens the dialog; an Edit button prefills the dialog
  with the row's values; close/reopen/delete buttons appear per status.

## Open questions / follow-ups (later)

- History tab (4d); import/export (4e).
- Incident-level attachments/comments; severity/assignee (would need schema).

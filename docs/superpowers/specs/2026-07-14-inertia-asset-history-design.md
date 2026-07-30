# Inertia Migration — Spec 4d: Asset History Panel

**Date:** 2026-07-14
**Status:** Approved (design)
**Part of:** FilamentPHP → React/Inertia migration — Spec 4 (Assets), sub-spec **4d of 4a–4e**
**Depends on:** Spec 4a (asset detail page + empty History tab), 4b/4c (panel patterns). Specs 1–3. Filament remains at `/app-old`.

## Background

The asset detail page's History tab is the last empty placeholder. `Asset` uses
spatie `LogsActivity` (logName `asset`), logging changes to `asset_type_id`,
`model_id`, `owner_id`, `place_id`, `serial_number`, `buy_date`, `buy_type`,
`buy_price`, `guarantee_end`, `state` (`logOnlyDirty`, `dontLogEmptyChanges`).
Each `activity_log` row has `event` (created/updated/deleted), `description`,
`causer_*`, `subject_*`, `properties` (JSON: `attributes` = new values, `old` =
previous values), and timestamps. There is **no `activities()` relation** on the
model; a subject's entries are fetched from the `Activity` model by
`subject_type`/`subject_id` (as the existing Filament panel does).

The existing Filament History relation manager is elaborate (semantic events like
`owner_changed`, a `Summary` service, an add-note write action, `handover_completed`
integration). This sub-spec deliberately ships only the **core read-only change
timeline**; the enrichments are deferred (some depend on Handovers = Spec 5).

## Locked decisions

- **Read-only** change timeline (no add-note write action).
- Deferred: semantic events, `Summary` service, `handover_completed`, incident-log
  entries (those are `subject=Incident`, not on the asset).
- **Value resolution:** enum changes (`state`, `buy_type`) → their labels; FK-id
  changes (`asset_type_id`, `model_id`, `owner_id`, `place_id`) → the referenced
  record's current `name`, fallback `"(removed)"`; dates/price/serial as-is.
  Best-effort per entry (a few extra queries per FK change on one asset's bounded
  history — acceptable; batch-optimize later if needed).
- **Show all** history entries (no pagination/limit) for now.
- No policy (gate on `auth`, via the existing show route); PHPUnit; ddev; pnpm.

## Goals

- The History tab shows a newest-first, read-only timeline of the asset's activity
  (created/updated/deleted) with who/when and friendly field-level old→new changes.
  `/app-old` Filament history unchanged; all three detail-panel tabs now filled.

## Non-goals

- Add-note (write); semantic events; the `Summary` service; handover events;
  incident-log entries; pagination. No change to the `Asset` model, activity-log
  config, or DB schema.

## Design

### Backend — `App\Support\Assets\AssetHistory`

A small, unit-testable helper that turns an asset's activities into a
view-model array (keeps `AssetController` thin):

```php
AssetHistory::for(Asset $asset): array   // newest first
// each entry:
// [
//   'id' => int,
//   'event' => 'created'|'updated'|'deleted'|string,
//   'event_label' => 'Created'|'Updated'|'Deleted'|ucfirst(event),
//   'causer_name' => string,            // name | 'System' | 'Former user'
//   'created_at' => 'Y-m-d H:i:s',
//   'changes' => [ ['field' => string, 'label' => string, 'old' => ?string, 'new' => ?string], ... ],
// ]
```

- **Source:** query the spatie `Activity` model by subject (matching how the
  entries were written):
  `\Spatie\Activitylog\Models\Activity::query()->where('subject_type', $asset->getMorphClass())->where('subject_id', $asset->id)->with('causer')->latest()->get()`.
  (No model relation is used; `->with('causer')` eager-loads the causer morphTo.)
- **causer_name:** if `activity->causer_id === null` → `"System"`; elseif
  `activity->causer` (the related user) is null → `"Former user"`; else
  `activity->causer->name`.
- **changes:** read `properties`:
  - new values = `properties['attributes'] ?? []`; old values =
    `properties['old'] ?? []`.
  - For each `field` present in the new-values set (the logged fields), build
    `{ field, label, old: resolve(field, old[field] ?? null), new: resolve(field, new[field] ?? null) }`.
  - `label` from a field→label map (Asset type / Model / Owner / Place /
    Serial number / Buy date / Buy type / Buy price / Guarantee end / State).
  - `resolve(field, value)`:
    - `null`/`''` → `null`.
    - `state` → `AssetState::tryFrom($value)?->getLabel() ?? $value`.
    - `buy_type` → `BuyType::tryFrom($value)?->getLabel() ?? $value`.
    - `asset_type_id` → `AssetType::find($value)?->name ?? '(removed)'`.
    - `model_id` → `AssetModel::find($value)?->name ?? '(removed)'`.
    - `owner_id` → `User::find($value)?->name ?? '(removed)'`.
    - `place_id` → `Place::find($value)?->name ?? '(removed)'`.
    - otherwise (serial_number, buy_date, buy_price, guarantee_end) → `(string) $value`.
  - `created` entries: new values only (old absent → all `old` are null).
    `deleted`: last known values (spatie stores the final attributes).

### Backend — `AssetController@show`

Add a `history` prop (the existing `->load(...)` list is unchanged — `AssetHistory`
runs its own `Activity` query with `->with('causer')`):

```php
// existing show() load(...) stays as-is (assetType, model.manufacturer, owner,
// place, tags, attachments.uploadedBy, incidents) + loadCount('incidents')
'history' => AssetHistory::for($asset),
```

(Existing `asset`, `attachments`, `attachmentCategoryOptions`, `incidents` props
are unchanged.)

### Frontend — `asset-history.tsx`

Rendered in `assets/show.tsx`'s History `TabsContent` (replacing the placeholder),
props `{ history: HistoryEntry[] }` where `HistoryEntry` matches the backend shape:

```ts
interface HistoryChange { field: string; label: string; old: string | null; new: string | null; }
interface HistoryEntry {
    id: number; event: string; event_label: string; causer_name: string;
    created_at: string | null; changes: HistoryChange[];
}
```

- A vertical timeline: each entry a row/card with an event `Badge`
  (Created/Updated/Deleted), `causer_name`, `created_at`, and (when `changes`
  non-empty) a compact list `label: old → new` (render `old ?? '—'` and
  `new ?? '—'`). Entries with no changes (e.g. a bare created/deleted) just show
  the event line. Empty state ("No history yet.") when the list is empty.

`show.tsx`: add `history: HistoryEntry[]` to the page `Props`, destructure it, and
render `<AssetHistory history={history} />` in the History tab.

### Reuse

No new primitives (`Badge`, `Card` exist). No model/activity-log/schema changes.

## Testing

- **PHPUnit** (`AssetHistoryTest` and/or the show `history` prop):
  - Acting as a user, create then update an asset via the controller → `history`
    has a `Created` and an `Updated` entry, both with `causer_name` = the user's
    name (newest first: the update precedes the create).
  - An `owner_id` change resolves **both** old and new to the owner **names**
    (not UUIDs).
  - A `state` change shows the enum **labels** (e.g. old/new German labels).
  - A change referencing a since-deleted related record resolves to `"(removed)"`.
  - An activity with a null causer shows `"System"`.
  - (Optional) a causer whose user was deleted shows `"Former user"`.
- **Vitest** (`asset-history.test.tsx`): renders entries with event label +
  causer + `label: old → new` change lines; shows the empty state for `[]`.

## Open questions / follow-ups (later)

- Add-note write action; semantic events + `Summary`; `handover_completed`
  integration (Spec 5); pagination/batch value-resolution if history grows large.
- Import/Export (4e) closes out Spec 4.

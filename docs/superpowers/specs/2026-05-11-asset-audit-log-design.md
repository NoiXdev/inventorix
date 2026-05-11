# Asset & Incident Audit Log — Design

**Status:** approved, ready for planning
**Date:** 2026-05-11
**Author:** Daniel Elskamp (with Claude)

## Goal

Give every Asset and every Incident a complete, immutable history that records who
changed what and when, plus a way for users to attach free-text notes to the
timeline. The history is viewable as a tab on each record in the Filament panel.

## Out of scope (v1)

- A global cross-system audit page (per-record tab only).
- A standalone Incident UI (Filament Resource) and its own History tab. Incident activity is still captured and appears inside the Asset's timeline.
- Audit for `AssetModel`, `AssetType`, `Manufacturer`, `Place`, `User`.
- Auth / Entra-sync / CLI-action logging beyond what falls out of model events.
- Editing or deleting history entries from the UI (logs are immutable).
- Retention / pruning jobs.
- Performance and cross-browser test coverage.

## Architecture overview

```
Asset / Incident model        Filament UI
       │                              │
       │  uses LogsActivity trait     │  HistoryRelationManager
       │  AssetObserver               │  (on AssetResource only — see below)
       ▼                              ▼
   spatie/laravel-activitylog ── activity_log table ── timeline query w/ de-dup
```

One package (`spatie/laravel-activitylog`, latest release compatible with
Laravel 13 — installation step verifies this), one polymorphic table, one
relation manager.

**UI surface note.** There is no `IncidentResource` in this codebase today —
incidents are managed only through `IncidentsRelationManager` on Asset. v1
therefore exposes the History tab **only on Asset**, and the asset's timeline
includes both:

- Activity where `subject` is this Asset.
- Activity where `subject` is an Incident with `asset_id` = this asset.

So the user sees one merged "everything that happened to this asset" view.
Incident-level changes are still captured in `activity_log` (the trait is on
the Incident model), so adding a standalone Incident UI later — or a global
audit page — surfaces them automatically with no data backfill.

## Data model

The package's published migration is modified before running so the
polymorphic `subject_id` can hold both UUIDs (Asset) and bigints (Incident).
The Incident table uses an auto-increment bigint PK today (`$table->id()`)
while Asset and User use `HasUuids` — `string(36)` accommodates both
(UUIDs are 36 chars; int64 max is 19 chars as text):

| column | type | notes |
|---|---|---|
| `id` | bigIncrements | PK |
| `log_name` | string, nullable, indexed | `"asset"` or `"incident"` |
| `description` | string | machine code: `created`, `updated`, `deleted`, `owner_changed`, `place_changed`, `state_changed`, `note` |
| `subject_type` | string, nullable, indexed | model class |
| `subject_id` | **string(36), nullable, indexed** | overridden from the package's `unsignedBigInteger` default |
| `causer_type` | string, nullable | `App\Models\User` |
| `causer_id` | **string(36), nullable, indexed** | User uses UUID PKs |
| `properties` | json, nullable | see "Event payloads" below |
| `created_at`, `updated_at` | timestamps | |

Indexes:
- `(subject_type, subject_id)` — from the package
- `(subject_type, subject_id, created_at)` — added by us, for fast per-record timelines sorted newest first

### Event payloads (`properties`)

| `description` | `properties` shape |
|---|---|
| `created` | `{ "attributes": { ...allowlisted fields with initial values... } }` |
| `updated` | `{ "old": { ...changed fields, prior values... }, "attributes": { ...changed fields, new values... } }` |
| `deleted` | `{ "old": { ...allowlisted fields at time of delete... } }` |
| `owner_changed` / `place_changed` / `state_changed` | `{ "from": <id-or-enum>, "to": <id-or-enum> }` |
| `note` | `{ "body": "<user text, max 2000 chars>" }` |

## Capturing changes

Three layers, in order from automatic to manual.

### 1. Automatic field diffs

`LogsActivity` trait on `Asset` and `Incident`:

```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logOnly([...allowlist...])
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs()
        ->useLogName('asset'); // or 'incident'
}
```

Allowlists:

- **Asset:** `asset_type_id`, `model_id`, `owner_id`,
  `place_id`, `serial_number`, `buy_date`, `buy_type`, `buy_price`,
  `guarantee_end`, `invoice`, `state` (`manufacturer_id` was moved to
  `asset_models` by migration `2025_06_03_070938_new_manufacturer_model_logic.php`
  and no longer exists on the `assets` table)
- **Incident:** all fillable fields (the existing migration is the source of truth)

This produces `created` / `updated` / `deleted` rows automatically.

### 2. Semantic "called-out" events

`AssetObserver` on `updating`: when `owner_id`, `place_id`, or `state` is
dirty, write an extra activity:

```php
activity()
    ->performedOn($asset)
    ->causedBy(auth()->user())
    ->useLog('asset')
    ->withProperties(['from' => $asset->getOriginal($field), 'to' => $asset->$field])
    ->log("{$field}_changed");
```

(`field` is mapped to event name: `owner_id` → `owner_changed`, `place_id` →
`place_changed`, `state` → `state_changed`.)

Incidents do not have semantic events in v1.

### 3. Manual notes

A Filament header action "Add note" on the `HistoryRelationManager` opens a
modal with a required `textarea` (`min:1`, `max:2000`). On submit it writes:

```php
activity()
    ->performedOn($parent)
    ->causedBy(auth()->user())
    ->useLog($parent instanceof Asset ? 'asset' : 'incident')
    ->withProperties(['body' => $body])
    ->log('note');
```

### Causer resolution

`CausesActivity` trait on `User`. Spatie auto-resolves `auth()->user()` from
the Filament panel. For CLI / seeder / system actions the causer is `null`
and the UI renders "System".

### De-duplication rule

Semantic events run *in addition to* the automatic `updated` row in the same
request. The relation-manager query excludes any `updated` row that has a
sibling `owner_changed` / `place_changed` / `state_changed` row with the same
`subject_id`, same `causer_id` (NULL-safe), and `|created_at diff| ≤ 1 second`.

Implementation: `whereNotExists` subquery on the same `activity_log` table —
one DB roundtrip.

## UI: `HistoryRelationManager`

One class, registered on `AssetResource` only (see "UI surface note" above).
Mirrors the existing `IncidentsRelationManager` pattern.

- **Tab placement:** label `__('history.label')`, icon `Heroicon::OutlinedClock`,
  ordered after Incidents on the Asset edit page.
- **Default sort:** `created_at desc`.
- **Empty state:** `__('history.empty_state')`.

### Query

The relation manager's base query is **not** the default
`$ownerRecord->activities()` relation. It is a union-shaped query against
`activity_log` selecting rows where either:

- `subject_type = Asset AND subject_id = $ownerRecord->id`, OR
- `subject_type = Incident AND subject_id IN (SELECT id FROM incidents WHERE asset_id = $ownerRecord->id)`

This pulls incident events into the asset timeline. Implementation can be a
single `where(...)->orWhereIn(...)` against `activity_log` scoped by both
`subject_type` clauses.

### Columns

| column | render |
|---|---|
| When | `created_at` as relative diff; tooltip with absolute datetime in user locale |
| Who | causer name; `null` → badge `__('history.causer.system')` |
| Event | translated label from `description`, with a colored badge per kind |
| Summary | one-liner — see "Summary builder" below |
| Details (expand) | per-field old→new diff for `updated`; full body for `note`; nothing extra for semantic events |

### Summary builder

- `created` → `__('history.summary.created')`
- `updated` → `__('history.summary.fields_changed', ['count' => N])`
- `deleted` → `__('history.summary.deleted')`
- `owner_changed` → `"<old user name> → <new user name>"` (resolved via User lookup; null → "—")
- `place_changed` → `"<old place name> → <new place name>"`
- `state_changed` → `"<AssetState old label> → <AssetState new label>"` via the enum's `getLabel()`
- `note` → `__('history.event.note') . ': ' . Str::limit($body, 80)`

For rows whose `subject_type = Incident`, the summary is prefixed with
`"Incident #<short-id>: "` so the user can tell at a glance that the entry
came from an incident rather than the asset itself. If the incident has been
hard-deleted, the prefix becomes `"Incident (removed): "`.

Field labels for the diff view resolve via `__('asset.<field>')` /
`__('incident.<field>')`; missing keys fall back to the raw column name.

### Filters

- Event kind (multi-select over `description` values)
- Date range on `created_at`

No causer filter — overkill on a per-record timeline.

### Actions

- **Header — Add note** (see "Manual notes"). The note is always recorded
  against the Asset (`subject = $ownerRecord`), never against an incident,
  even though incident rows appear in the same list.
- **Row actions: none.** Logs are immutable from the UI.

### Permissions

| action | rule |
|---|---|
| View history tab | anyone who can `view` the parent |
| Add note | anyone who can `update` the parent |
| Edit / delete a log row | nobody (no UI) |

No new policies — the relation manager defers to the parent resource policy.

## i18n

New translation files at `lang/de/history.php` and `lang/en/history.php`:

```
label, label-plural, tab, empty_state
add_note, add_note_body, add_note_save
event.created, event.updated, event.deleted, event.note
event.owner_changed, event.place_changed, event.state_changed
summary.created, summary.deleted, summary.fields_changed,
summary.set, summary.cleared
causer.system
```

Field labels and enum values reuse existing `__('asset.<field>')`,
`__('incident.<field>')`, and enum `getLabel()` methods.

## Lifecycle

- **Hard delete of an Asset/Incident:** activity rows are kept; `subject_id`
  stays, `subject()` returns `null`. The relation manager only queries via a
  live parent, so orphan rows are unreachable from the UI but preserved on
  disk for any future global view or DB-level inspection.
- **User deletion:** `causer()` returns `null`. UI rule: `causer_id IS NOT NULL
  AND causer IS NULL` → "System (former user)"; `causer_id IS NULL` → "System".
- **Soft deletes:** not used on any affected model today, no change.

## Testing strategy

PHPUnit 12 (the project's existing stack), DDEV-run.

1. **`AssetActivityLogTest`** (feature)
   - Create → one `created` row with correct `log_name`, `subject`, `causer`.
   - Update non-semantic fields → one `updated` row with the dirty fields only in `properties.old` and `properties.attributes`.
   - Update `owner_id`, `place_id`, `state` → both an `updated` row and the semantic row with `from`/`to`.
   - Delete → one `deleted` row.
   - CLI update (no auth) → `causer_id` is null.

2. **`IncidentActivityLogTest`** (feature) — same shape, narrower allowlist, no semantic rows.

3. **`HistoryRelationManagerTest`** (Filament/Livewire)
   - De-dup rule hides the generic `updated` row when a same-request semantic row exists; shows it otherwise.
   - The asset's timeline includes activity rows whose `subject` is an Incident belonging to that asset, and excludes incidents belonging to other assets.
   - Incident rows from a hard-deleted incident still render with the "Incident (removed):" prefix.
   - "Add note" writes a `note` activity with the typed body and `subject = Asset`; empty body fails validation.
   - Filters by event kind and date range narrow results.
   - A user without `view` on the parent gets 403.

4. **`HistoryRenderingTest`** (unit)
   - Summary builder returns the expected string for each event kind, including the "System" / "System (former user)" fallbacks.
   - Field-label resolver returns the translation when present, raw column name when not.

## Open items

None. All design questions resolved during brainstorming.

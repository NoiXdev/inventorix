# Asset Attachments (Dokumente & Medien) — Design

**Date:** 2026-06-29
**Branch:** new-features2
**Status:** Approved (brainstorming)

## Goal

Allow users to upload documents and photos/videos to assets, view them in the
Filament asset edit page, download them, and delete them. Each file carries a
title, an optional note, and a user-set category. The storage layer is
polymorphic so the same mechanism can later be reused for other models
(incidents, handovers, etc.).

## Non-goals

- No `spatie/laravel-medialibrary` dependency — we use a dedicated table.
- No image conversions / responsive variants / thumbnails generation pipeline.
- No public sharing links. Files are served only through authenticated Filament
  actions.

## Data model

### `attachments` table (new migration)

Polymorphic, UUID-based. Follows the project's UUID convention — use
`uuidMorphs()` and `foreignUuid()`, **not** the default bigint variants (see the
UUID migrations gotcha).

| Column | Type | Notes |
|---|---|---|
| `id` | uuid, primary key | via `HasUuids` |
| `attachable_type` / `attachable_id` | `uuidMorphs('attachable')` | polymorphic owner (Asset for now) |
| `path` | string | stored file path on the default disk |
| `original_name` | string | original uploaded filename |
| `mime_type` | string | detected MIME type |
| `size` | unsignedBigInteger | file size in bytes |
| `type` | string | auto-detected: `document` / `image` / `video` |
| `category` | string, nullable | user-set `AttachmentCategory` enum value |
| `title` | string, nullable | defaults to original filename when blank |
| `note` | text, nullable | free-text note |
| `uploaded_by` | `foreignUuid('uploaded_by')` → users, nullable, nullOnDelete | who uploaded |
| `created_at` / `updated_at` | timestamps | |

**Disk:** Not stored. Files always resolve against the current default disk
(`FILESYSTEM_DISK`). The app assumes the configured disk is stable.

### `Attachment` model

- `App\Models\Attachment`
- Traits: `HasUuids`, `HasFactory`
- Fillable: `path`, `original_name`, `mime_type`, `size`, `type`, `category`,
  `title`, `note`, `uploaded_by`
- Casts: `category` => `AttachmentCategory::class`
- Relationships:
  - `attachable()` — `morphTo`
  - `uploadedBy()` — `belongsTo(User::class, 'uploaded_by')`
- Helpers:
  - `getUrl()` / download handling resolves `Storage::disk()` (default disk).
  - `isImage()`, `isVideo()`, `isDocument()` derived from `type`.

### `HasAttachments` trait

`App\Models\Concerns\HasAttachments` providing:

```php
public function attachments(): MorphMany
{
    return $this->morphMany(Attachment::class, 'attachable')->latest();
}
```

Applied to `Asset` now; available for other models later.

### `AttachmentCategory` enum

`App\Enums\AttachmentCategory`, German labels (matching project convention with
`getLabel()`):

| Case | Value | Label |
|---|---|---|
| `RECHNUNG` | `rechnung` | Rechnung |
| `FOTO` | `foto` | Foto |
| `VIDEO` | `video` | Video |
| `DOKUMENT` | `dokument` | Dokument |
| `SONSTIGES` | `sonstiges` | Sonstiges |

`type` (document/image/video) is auto-detected from MIME and used for badges /
previews; `category` is the user's manual grouping and is independent.

### Type auto-detection

From the uploaded file's MIME type:
- `image/*` → `image`
- `video/*` → `video`
- everything else → `document`

## UI — `AttachmentsRelationManager` ("Anhänge")

Located at
`app/Filament/App/Resources/Assets/RelationManagers/AttachmentsRelationManager.php`,
registered on `AssetResource` alongside the existing Incidents and History
managers. Modeled after `IncidentsRelationManager`.

### Create / Edit form

- `FileUpload` field bound to `path`:
  - `multiple()` on create (single file rows on edit).
  - Accepted MIME types: documents (`application/pdf`, common Office docs,
    `text/plain`), images (`image/jpeg`, `image/png`, `image/webp`,
    `image/heic`), videos (`video/mp4`, `video/quicktime`, `video/webm`).
  - Max size ~50 MB per file.
  - `storeFileNamesIn('original_name')` to retain original filenames.
- `category` — `Select` of `AttachmentCategory`.
- `title` — `TextInput`, optional.
- `note` — `Textarea`, optional.

On save (`mutateFormDataBeforeCreate` / before save), derive and persist:
- `mime_type`, `size` from the stored file via `Storage::disk()->mimeType()` /
  `->size()`.
- `type` from MIME auto-detection.
- `uploaded_by` = `auth()->id()`.
- `title` defaults to `original_name` when left blank.

> Implementation note: with `multiple()`, the form value is an array of paths.
> The relation manager create flow must fan out into one `Attachment` row per
> uploaded file. If the single-step relation-manager create cannot cleanly
> create N rows from one form submission, fall back to a custom create action
> that loops over the uploaded paths and creates one record each. Decide during
> implementation; either way the user uploads several files at once.

### Table

Columns:
- `type` badge (and/or `category` badge with German label).
- `title` (falls back to `original_name`).
- `original_name`.
- `size` — human-readable (e.g. `formatStateUsing` → KB/MB).
- `uploadedBy.name`.
- `created_at`.

Image rows show an inline thumbnail via a signed/temporary URL.

Row actions:
- **Download** — streams the file via `Storage::download($path, $original_name)`,
  works on any disk and respects auth.
- **Edit** — title / note / category.
- **Delete**.

### Tab badge

Attachment count badge on the relation manager tab, like Incidents
(`getTitleBadge` / relation manager badge).

## Serving files

Files are served only through authenticated Filament actions:
- **Download:** Laravel `Storage::download()` stream — no public URL required,
  disk-agnostic.
- **Image thumbnails/preview:** signed or temporary URL generated on render.

No public direct links are created.

## File deletion

`AttachmentObserver` (or model `deleting` event): when an `Attachment` row is
deleted, delete the underlying file from the default disk. Guard against missing
files.

## Activity log

`AttachmentObserver` logs **created** and **deleted** events to the existing
Spatie activity log, using the **parent asset (attachable) as the subject**
(`activity()->performedOn($attachment->attachable)->...->log(...)`), so entries
surface in the Asset's existing **History** tab. Log message includes the file's
title/original name.

## Testing (Pest, with `Storage::fake()`)

- Uploading a file creates an `Attachment` with correct `original_name`,
  `mime_type`, `size`, and auto-detected `type`.
- Type auto-detection: image, video, and document MIME types map correctly.
- Validation rejects a disallowed MIME type and an oversized file.
- `uploaded_by` is set to the authenticated user.
- `title` defaults to `original_name` when blank.
- Deleting an attachment removes the underlying file from storage.
- Deleting/creating an attachment records an activity-log entry against the
  asset.
- `AttachmentCategory` German labels resolve correctly.

## Files to add / change

**Add:**
- `database/migrations/XXXX_create_attachments_table.php`
- `app/Models/Attachment.php`
- `app/Models/Concerns/HasAttachments.php`
- `app/Enums/AttachmentCategory.php`
- `app/Observers/AttachmentObserver.php`
- `app/Filament/App/Resources/Assets/RelationManagers/AttachmentsRelationManager.php`
- `database/factories/AttachmentFactory.php`
- Tests under `tests/Feature/` (e.g. `AssetAttachmentsTest.php`)

**Change:**
- `app/Models/Asset.php` — apply `HasAttachments` trait.
- `app/Filament/App/Resources/Assets/AssetResource.php` — register the relation
  manager.
- Register `AttachmentObserver` (model attribute or `AppServiceProvider`).
- Translation files — German labels for the relation manager / fields if the
  project uses translation keys (follow existing `menu.*` / label conventions).

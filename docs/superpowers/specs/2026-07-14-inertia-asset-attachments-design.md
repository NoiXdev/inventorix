# Inertia Migration — Spec 4b: Asset Attachments Panel

**Date:** 2026-07-14
**Status:** Approved (design)
**Part of:** FilamentPHP → React/Inertia migration — Spec 4 (Assets), sub-spec **4b of 4a–4e**
**Depends on:** Spec 4a (Assets core + the tabbed detail page with an empty Attachments tab). Specs 1–3 (kit, auth). Filament remains at `/app-old`.

## Background

Spec 4a shipped the asset detail page (`/app/assets/{asset}`, `assets/show`) with
a tabs layout whose *Attachments* tab is an empty placeholder. This sub-spec
fills it. The attachments backend already exists (built in an earlier Filament
spec):

- `Attachment` model — `morphTo` `attachable`, `belongsTo` `uploadedBy`; fields
  `path, original_name, mime_type, size (int), type (image|video|document), category
  (AttachmentCategory enum, nullable), title (nullable), note (nullable), uploaded_by`.
  `Attachment::detectType($mime)` maps mime → type. UUID pk.
  `ObservedBy(AttachmentObserver)` — the observer deletes the file from disk and
  writes an activity-log entry on model delete.
- `HasAttachments` concern on `Asset` — `attachments(): morphMany(...)->latest()`
  and a `deleting` hook that cascades attachment deletes (→ observer removes files).
- `AttachmentCategory` enum: rechnung/foto/video/dokument/sonstiges (German labels).
- Existing route `attachments.open` (`GET /attachments/{attachment}/open`, `auth`)
  streams a file inline via `Storage::disk()->response(...)`.
- Uploads/reads use the app's **default** filesystem disk (S3-configurable via
  the storage settings).

## Locked decisions

- Metadata (title/category/note) is set **at upload only**; existing attachments'
  metadata is not editable (delete + re-upload).
- Presentation: **image-type → thumbnail grid; others → list rows**. Thumbnails
  reuse `attachments.open` as the `<img>` source, CSS-scaled — no server-side
  thumbnail generation.
- Attachment endpoints are **asset-scoped** for now (generalize when
  handovers/others need attachments).
- Basic multipart upload (no drag-drop/progress/chunking).
- Accepted types: a broad set (images, PDF, common office docs, video), ~50 MB cap
  (matches Filament).
- No policy (gate on `auth`); PHPUnit; ddev; pnpm.

## Goals

- The Attachments tab lists an asset's attachments (thumbnails for images, rows
  for others) and supports upload (multiple files + optional metadata) and delete.
- `/app-old` Filament attachments still work unchanged.

## Non-goals

- Editing existing attachments' metadata; server-side thumbnail generation;
  generic (non-asset) attachment endpoints; drag-drop/chunked/progress uploads;
  the Incidents (4c) / History (4d) tabs.
- Any change to the `Attachment` model, observer, migration, or the `attachments.open`
  route. No DB schema changes.

## Design

### Backend

**`AssetController@show`** — add an `attachments` prop and an
`attachmentCategoryOptions` prop:

```php
$asset->load('assetType', 'model.manufacturer', 'owner', 'place', 'tags', 'attachments.uploadedBy')
      ->loadCount('incidents');
// …
'attachments' => $asset->attachments->map(fn (Attachment $a) => [
    'id' => $a->id,
    'type' => $a->type,
    'category_label' => $a->category?->getLabel(),
    'title' => $a->title,
    'original_name' => $a->original_name,
    'size' => $a->size,
    'size_label' => $this->humanSize($a->size),
    'uploaded_by_name' => optional($a->uploadedBy)->name,
    'created_at' => optional($a->created_at)->toDateTimeString(),
    'url' => route('attachments.open', $a),
])->all(),
'attachmentCategoryOptions' => array_map(
    fn (AttachmentCategory $c) => ['value' => $c->value, 'label' => $c->getLabel()],
    AttachmentCategory::cases(),
),
```

`humanSize(int $bytes): string` — a small private helper (B/KB/MB) on the
controller (or a shared `Support` helper if preferred).

**`App\Http\Controllers\App\AssetAttachmentController`** (new):

- `store(AssetAttachmentRequest $request, Asset $asset)`:
  - Validation (`AssetAttachmentRequest`): `files` = `required|array|min:1`;
    `files.*` = `file|max:51200` + a `mimes:`/`mimetypes:` allowlist (jpg, jpeg,
    png, gif, webp, pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv, mp4, mov,
    webm); `title` = `nullable|string|max:255`; `note` = `nullable|string`;
    `category` = `nullable`, `Rule::enum(AttachmentCategory::class)`.
  - For each `$file`: `$path = $file->store('attachments')` (default disk);
    `$asset->attachments()->create([...])` with `path`, `original_name =
    $file->getClientOriginalName()`, `mime_type = $file->getMimeType()`, `size =
    $file->getSize()`, `type = Attachment::detectType($mime)`, `category`, `title`,
    `note`, `uploaded_by = $request->user()->id`.
  - Redirect back with success flash.
- `destroy(Asset $asset, Attachment $attachment)`:
  - `abort_unless($attachment->attachable_type === $asset->getMorphClass() && $attachment->attachable_id === $asset->id, 403)`.
  - `$attachment->delete()` (observer removes file + logs). Redirect back.

**Routes** (authenticated `/app` group):

```php
Route::post('assets/{asset}/attachments', [AssetAttachmentController::class, 'store'])->name('assets.attachments.store');
Route::delete('assets/{asset}/attachments/{attachment}', [AssetAttachmentController::class, 'destroy'])->name('assets.attachments.destroy');
```

(Reuse the existing `attachments.open` for viewing/thumbnails — no new view route.)

### Frontend

**`resources/js/components/assets/asset-attachments.tsx`** (new), rendered in
`assets/show.tsx`'s Attachments `TabsContent` (replacing the placeholder), props
`{ assetId: string; attachments: Attachment[]; categoryOptions: {value,label}[] }`:

- **Upload form** — `useForm({ files: [] as File[], title: '', category: '', note: '' })`;
  a multiple `<input type="file">` (`onChange` → `setData('files', Array.from(e.target.files ?? []))`),
  optional `TextField` title, `SelectField` category (nullable), a shadcn
  `Textarea` note; submit `form.post(`/app/assets/${assetId}/attachments`, { forceFormData: true, onSuccess: () => form.reset() })`.
  Show `form.errors['files']` / `files.0` etc.
- **List** — split `attachments` by `type`:
  - `type === 'image'` → a responsive thumbnail grid: `<a href={url} target="_blank"><img src={url} className="h-28 w-full object-cover rounded" /></a>` with the title/filename beneath + a delete button.
  - others → list rows: type `Badge`, `category_label`, title, `original_name`,
    `size_label`, `uploaded_by_name`, `created_at`, an "Open" link (`url`,
    new tab), delete button.
- **Delete** — `router.delete(`/app/assets/${assetId}/attachments/${id}`)` behind a `confirm`.
- Empty state when no attachments.

`assets/show.tsx` change: import `AssetAttachments`, pass `attachments` +
`attachmentCategoryOptions` from props, render it in the Attachments tab.

### Reusable additions

- shadcn **`Textarea`** primitive (`resources/js/components/ui/textarea.tsx`) via
  the shadcn CLI — used by the note field (and reusable later, e.g. incidents 4c).
- No new form-kit wrapper is required; the note uses `Textarea` + `Label`
  inline, or a tiny `TextareaField` wrapper may be added if it reads cleaner
  (implementer's discretion, kept consistent with the other `*Field` components).

## Testing

- **PHPUnit** (`AssetAttachmentControllerTest`, `Storage::fake()`):
  - upload a single file → an `Attachment` row is created with correct
    `type`/`size`/`original_name`/`mime_type`/`uploaded_by`, linked to the asset,
    and the file exists on the faked disk.
  - upload multiple files → multiple rows.
  - category enum validation (bad category rejected); oversized file rejected;
    disallowed mime rejected; empty `files` rejected.
  - destroy removes the row **and** the file (`Storage::disk()->assertMissing`);
    destroy of an attachment belonging to a *different* asset → 403 (and row/file
    untouched); auth redirect for guests.
  - `AssetController@show` returns the `attachments` prop (shape incl. `url`,
    `size_label`, `type`) and `attachmentCategoryOptions`.
- **Vitest** (`asset-attachments.test.tsx`): renders image attachments as
  `<img>` thumbnails and non-image ones as rows with an "Open" link; the file
  input updates form state; the category select uses the options.

## Open questions / follow-ups (later)

- Editing existing attachment metadata; server-side thumbnails; generic
  attachment endpoints for other attachables; drag-drop/progress uploads.
- Incidents (4c) and History (4d) tabs.

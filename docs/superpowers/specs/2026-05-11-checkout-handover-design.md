# Checkout / Handover Workflow with Digital Signature — Design

**Status:** approved, ready for planning
**Date:** 2026-05-11
**Author:** Daniel Elskamp (with Claude)

## Goal

Provide a guided, auditable workflow for handing assets out to and receiving
them back from people, ending in a recipient-signed PDF that is archived on the
asset and emailed to the recipient. The workflow drives the asset's `state` and
`owner_id` deterministically, so an asset's lifecycle in the database always
matches the paper trail.

## Scenarios in scope

| Type | Use case |
|---|---|
| `issue` | Hand asset out for permanent use (typically employee onboarding) |
| `lend` | Short-term loan |
| `return` | Asset comes back in working order |
| `return_defect` | Asset comes back damaged / needs repair |

Both internal users (Filament `User` rows) and external parties (free-text
name + optional email) are supported as recipients.

## Out of scope (v1)

- Expected return date and overdue-loan dashboard (LEND state has no enforced
  return-by date in v1).
- Auto-create an Incident on `return_defect` — user opens one manually if
  desired.
- Cross-type handovers in one document (e.g. return A and issue B in one
  signature). To swap assets between two users, do a return then an issue.
- Per-tenant / per-asset-type configurable terms text — v1 uses one global
  template from `config/handover.php`, edited by deploy.
- Recipient signs on a separate device (email-link or QR flow).
- Counter-signature from the handing-over employee. They are identified by
  their logged-in Filament account; the audit log captures their identity, no
  drawn signature is required.
- Editing or deleting handovers from the UI — handovers are immutable. Mistakes
  are corrected by creating a new compensating handover.
- Soft deletes.
- Headless-Chrome / Browsershot PDF rendering — dompdf is sufficient for v1.
- Performance and cross-browser test coverage.

## Architecture overview

```
Filament UI (Wizard in Action modal)
   │
   │  4 entry points → same wizard
   │  - row action on Assets table
   │  - header action on Asset edit/view page
   │  - bulk action on Assets table
   │  - "New handover" on the dedicated Handovers page
   ▼
HandoverService::commit()  ── DB::transaction()
   │
   ├─ insert handovers row
   ├─ write signature PNG to disk
   ├─ for each asset:
   │     - insert handover_asset pivot (with snapshots)
   │     - update asset state + owner   (AssetObserver fires existing
   │                                     state_changed / owner_changed
   │                                     audit rows)
   │     - write `handover_completed`   activity entry
   └─ dispatch GenerateHandoverPdf job
              │
              ├─ render Blade → barryvdh/laravel-dompdf
              ├─ store PDF to disk
              └─ chain SendHandoverEmail (if recipient_email set)
```

One new domain (`Handover` + pivot), one new Filament Resource, one wizard,
one queued PDF job, one queued mailable. Existing audit-log infrastructure is
reused; no new logging package.

## Data model

Two new tables. UUID PKs to match the rest of the schema.

### `handovers`

| column | type | notes |
|---|---|---|
| `id` | uuid | PK |
| `type` | string | enum `HandoverType`: `issue`, `lend`, `return`, `return_defect` |
| `recipient_kind` | string | `internal` or `external` |
| `recipient_user_id` | uuid, nullable, FK → `users` (nullOnDelete) | set when `recipient_kind = internal` |
| `recipient_name` | string | denormalised — for `internal`, snapshot of user's name at the time; for `external`, free-text |
| `recipient_email` | string, nullable | optional for both; used to email the PDF |
| `accessories` | text, nullable | free-text list |
| `condition_notes` | text, nullable | free-text |
| `terms_text` | text | snapshot of the acknowledgement text the recipient agreed to, so future term edits don't change historical records |
| `signature_path` | string | disk path to signature PNG, e.g. `handovers/{id}/signature.png` |
| `signature_ip` | string, nullable | IP at time of signing |
| `signature_user_agent` | string, nullable | UA at time of signing |
| `pdf_path` | string, nullable | disk path to generated PDF; nullable so a failed render doesn't block the record |
| `signed_at` | datetime | when the recipient submitted the signature |
| `created_by` | uuid, FK → `users` | the Filament user who drove the handover |
| `email_sent_at` | datetime, nullable | populated by the mail job on success |
| `created_at`, `updated_at` | timestamps | |

### `handover_asset` (pivot)

| column | type | notes |
|---|---|---|
| `id` | uuid | PK |
| `handover_id` | uuid, FK → `handovers` cascadeOnDelete | |
| `asset_id` | uuid, FK → `assets` cascadeOnDelete | |
| `state_from` | string | AssetState before handover, snapshot |
| `state_to` | string | AssetState after handover, snapshot |
| `owner_from_id` | uuid, nullable | snapshot of prior `owner_id` |
| `owner_to_id` | uuid, nullable | snapshot of new `owner_id` after handover |
| unique index `(handover_id, asset_id)` | | one asset per handover |

**Why snapshot fields:** the asset's state/owner can change again later; the
handover record must remain a truthful artifact of what was transferred and
to/from whom.

**No soft deletes.** Handovers are immutable.

**Files on disk:** Signature PNG and PDF live on a configurable disk (default
`local`, private) under `handovers/{id}/`. Paths are stored in DB; binaries
are not. Filament `FileUpload` is not used — both files are written
server-side from the workflow.

## State transition rules

Each `HandoverType` deterministically maps to the new `(state, owner_id)`:

| Type | Allowed `state_from` | `state_to` | `owner_to_id` |
|---|---|---|---|
| `issue` | `NEW`, `STORAGE` | `IN_USE` | recipient (internal) or `null` (external) |
| `lend` | `NEW`, `STORAGE` | `LEND` | recipient (internal) or `null` (external) |
| `return` | `IN_USE`, `LEND` | `STORAGE` | `null` |
| `return_defect` | `IN_USE`, `LEND` | `NEED_REPAIR` | `null` |

Rules:

- The wizard validates `state_from` against the allowed set for the chosen
  `type` before showing the signature pad. Disallowed combinations are
  filtered out at asset-pick time.
- For bulk: every selected asset must satisfy the allow-list for the chosen
  type, or the workflow refuses to start and tells the user which assets
  blocked it.
- For external recipients on `issue` / `lend`, `owner_to_id` is `null`. The
  asset's `state` still transitions, so dashboards reflect that the asset is
  not in storage. The recipient identity lives only on the Handover record.
  The Asset's History tab shows `owner_changed: <prior> → —` plus the new
  `handover_completed` event linking to the Handover (which carries the
  external name).
- The handing-over employee is identified by `created_by` (their logged-in
  Filament user). No second signature is required.

## UI

One Filament v5 Wizard rendered inside an Action modal. The same wizard
powers all four entry points; only the "which assets" step is pre-filled
differently.

### Entry points & pre-fill

| Entry point | Pre-filled assets |
|---|---|
| Row action on Assets table | the one row |
| Header action on Asset edit/view page | the current asset |
| Bulk action on Assets table | the selected rows |
| `Handovers` page → "New handover" header action | none — user picks assets in step 1 |

Action labels are translation keys (e.g. `__('handover.action.handover')`); a
single component controls labelling so the four entry points stay in lock-step.

### Wizard steps

**Step 1 — Type & Assets**
- Radio group: `Issue`, `Lend`, `Return`, `Return (defect)` (translated).
  Default depends on entry context — e.g. an asset currently `IN_USE` defaults
  to `Return`.
- Asset list: read-only chips for pre-filled entries; on the standalone page
  it's a multi-select scoped to assets whose current `state` is allowed for
  the chosen type **and** which the current user can `update` (same check
  that gates the row / bulk action visibility). Switching type re-filters
  the list.
- One handover always has exactly one recipient. Bulk handover therefore
  means "N assets to the same person in one signed document"; to hand assets
  to two different people, run the workflow twice.
- Inline validation banner if any pre-filled asset is in a disallowed state,
  with a "Remove from handover" link per offending row.

**Step 2 — Recipient**
- Radio: `Internal user` / `External party`.
- Internal → searchable `Select` user (by name/email), scoped to
  `login_enabled = true`. On select, name/email pre-fill (read-only).
- External → two text inputs: name (required, max 255), email (nullable,
  validated as email).

**Step 3 — Details**
- Accessories (textarea, max 2000).
- Condition notes (textarea, max 2000).
- Terms preview: the global terms text rendered read-only at the top of the
  step. Snapshotted onto `handovers.terms_text` on submit.

**Step 4 — Sign & confirm**
- Recipient name shown as a confirmation header.
- Signature pad — HTML `<canvas>` 600×200, "Clear" button. Required: submit
  is disabled until a non-empty stroke is recorded.
- Submit label: `Hand over and email` (or `Hand over` if no recipient email).
- On submit: the base64 PNG + form data POST to a Livewire handler which
  calls `HandoverService::commit()` inside a DB transaction.

### Post-submit

- Modal closes. Filament notification: "Handover signed — PDF generated soon."
  with a link to the new Handover view page (PDF appears there once the job
  finishes).
- Redirect: from row/header/bulk → back to where the user was; from the
  Handovers page → the new handover's view page.

### Handovers resource (list page)

- Columns: `signed_at`, `type` (coloured badge), `recipient_name` (with an
  internal/external indicator), asset count, `created_by`, PDF download link.
- Filters: `type`, date range on `signed_at`, `recipient_kind`, `created_by`.
- Row action: View (read-only infolist with full details, signature image,
  and PDF download). No edit, no delete.
- Header action: `New handover`.

### Asset History integration

Every commit writes one `handover_completed` activity-log row per asset
(log_name `asset`, properties `{ handover_id, type, recipient_name }`). The
existing `HistoryRelationManager` summary builder gets a new case rendering
"Issued to \<name\>" / "Returned (defect)" / etc., with a link to the
Handover view page.

The Handover commit deliberately does **not** suppress the `state_changed` /
`owner_changed` rows the existing `AssetObserver` writes — those rows already
power the audit log and remain the source of truth for "what changed on the
asset." The `handover_completed` row is additive and provides the link to the
signed document. The existing de-duplication rule in the History relation
manager (hide generic `updated` rows when a semantic row exists) is unaffected.

## Signature pad

Vanilla JS in a small Alpine component (Filament already ships Alpine).
Pointer events → array of stroke points → on submit,
`canvas.toDataURL('image/png')`. No external npm dependency.

Server-side validation on the base64 payload:
- Magic-byte check confirms it's a real PNG.
- Decoded size capped at 200 KB.
- Non-empty: the canvas must contain at least one non-transparent pixel
  (covered by a "stroke recorded" flag set by the client, plus a server-side
  empty-image guard).

## Capture flow (`HandoverService::commit`)

Single `DB::transaction()`:

1. **Re-validate state under row lock.** For each asset, `SELECT … FOR UPDATE`
   on the `assets` row to serialise concurrent handovers, then confirm its
   current `state` is still in the allowed `state_from` set for
   `$data->type`. If any asset has changed state since the wizard started,
   throw `HandoverStateConflictException` → user sees "Asset X is no longer
   in a valid state; please restart the handover." (No partial commit.) The
   lock prevents two simultaneous handovers of the same asset from both
   succeeding.
2. **Insert** `handovers` row (everything except `pdf_path` and
   `email_sent_at`).
3. **Write signature PNG** to `handovers/{id}/signature.png` on the
   configured disk. The base64 payload is decoded, validated (PNG magic
   bytes, ≤ 200 KB), and written.
4. **For each asset:** insert pivot row with `state_from` / `owner_from_id`
   snapshots; update asset `state` and `owner_id` to target values. The
   existing `AssetObserver` fires and writes the usual `state_changed` /
   `owner_changed` audit rows.
5. **Write one `handover_completed`** activity-log entry per asset with
   `{ handover_id, type, recipient_name }`.
6. **Dispatch** `GenerateHandoverPdf` job (queue: default).

If anything in steps 1–5 throws, the transaction rolls back and the signature
file is deleted (try/finally). State changes, owner changes, audit rows, and
the signature file are all-or-nothing.

## PDF generation

`GenerateHandoverPdf` queued job:

- Renders Blade view `resources/views/pdf/handover.blade.php` through
  `barryvdh/laravel-dompdf`.
- View receives the Handover (with assets, recipient, terms snapshot, and the
  signature image as a base64 data URI), the company name from
  `config/handover.php`, and a generated-at timestamp.
- Writes the PDF to `handovers/{id}/handover.pdf` on the configured disk,
  then updates `pdf_path` on the Handover.
- Chains `SendHandoverEmail` if `recipient_email` is set.
- Retries 3 times with exponential backoff. Final failure logs and surfaces a
  banner on the Handover view page ("PDF generation failed — retry"). The
  Handover record itself is valid without a PDF; the retry action re-dispatches
  the job.

### PDF layout (A4 portrait, one template)

```
┌────────────────────────────────────────────────┐
│  [Company logo]      Handover document         │
│                      Type: Issue / Return / …  │
├────────────────────────────────────────────────┤
│  Recipient:  Jane Doe (external)               │
│  Email:      jane@example.com                  │
│  Handed by:  Daniel Elskamp                    │
│  Signed at:  2026-05-11 14:32 UTC (IP: …)      │
├────────────────────────────────────────────────┤
│  Assets:                                       │
│   • MacBook Pro 14"  S/N ABC123  STORAGE→IN_USE│
│   • Magic Mouse      S/N XYZ789  STORAGE→IN_USE│
├────────────────────────────────────────────────┤
│  Accessories: charger, USB-C cable             │
│  Condition:   slight scratch on lid            │
├────────────────────────────────────────────────┤
│  Terms (snapshot):                             │
│  I acknowledge that I am responsible for…      │
├────────────────────────────────────────────────┤
│  Signature:                                    │
│  ╭────────────────────────────╮                │
│  │     [signature image]      │                │
│  ╰────────────────────────────╯                │
│  Jane Doe — 2026-05-11 14:32                   │
└────────────────────────────────────────────────┘
```

## Email

`SendHandoverEmail` mailable (queued):

- `to($handover->recipient_email)`, BCC the `created_by` user.
- Attaches `handover.pdf`.
- Subject and body localised (translation keys under `mail.*`).
- On send: sets `email_sent_at`. Email failures are logged but do not roll
  back anything earlier in the pipeline.

## Storage

`local` disk by default, private. PDF download links use Laravel signed
temporary routes; files are never publicly browsable. Disk is configurable via
`config/handover.php → disk`, so production can swap to S3 later without code
changes.

## Permissions

No new policy classes for Assets — the workflow defers to existing resource
policies.

| Action | Rule |
|---|---|
| Start a handover (any entry point) | User can `update` every Asset in scope |
| View Handovers list / individual handover | User can `viewAny` / `view` on `AssetResource` |
| Download PDF | Same as `view` |
| Edit / delete a Handover | Nobody from the UI — immutable |

Bulk action visibility uses the same `can('update')` check on each selected
row; if any row fails, the action is hidden.

The `Handover` model gets a `HandoverPolicy` with only `viewAny` / `view`
mapped through to the user's panel access. No `update`, `delete`, or `create`
— creation goes through the service, not a resource form.

## i18n

Two new translation files (`lang/de/handover.php`, `lang/en/handover.php`):

```
nav.label, nav.group
resource.label, resource.plural
type.issue, type.lend, type.return, type.return_defect
recipient_kind.internal, recipient_kind.external
recipient.name, recipient.email
form.accessories, form.condition_notes, form.terms_header
sign.pad_label, sign.clear, sign.required
wizard.step.type, wizard.step.recipient, wizard.step.details, wizard.step.sign
action.handover, action.return, action.bulk
notification.success, notification.pdf_failed, notification.email_sent
pdf.title, pdf.recipient, pdf.handed_by, pdf.signed_at, pdf.assets,
pdf.accessories, pdf.condition, pdf.terms, pdf.signature, pdf.external
mail.subject, mail.intro, mail.outro
history.event.handover_completed
```

`AssetState` labels stay in the existing enum. New labels for the
`HandoverType` enum implementing `HasLabel` + `HasColor` (mirroring
`AssetState`).

## Config

`config/handover.php`:

```php
return [
    'disk' => env('HANDOVER_DISK', 'local'),
    'company' => [
        'name' => env('APP_COMPANY_NAME'),
        'logo' => env('APP_COMPANY_LOGO'),
    ],
    'terms' => <<<'TXT'
        I acknowledge that I am responsible for the assets listed above…
        TXT,
    'pdf' => [
        'paper' => 'a4',
        'orientation' => 'portrait',
    ],
];
```

## Lifecycle / edge cases

- **User deletion** (recipient): `recipient_user_id` is `nullOnDelete`;
  `recipient_name` snapshot preserves who received the asset. UI rendering
  for "internal but user gone" follows the existing audit-log convention
  ("former user").
- **Asset hard delete:** pivot row cascade-deletes. Handover row remains. UI
  on the Handover view page renders deleted assets as "Asset (removed)".
- **Created_by user deletion:** FK is `nullOnDelete` is not used here —
  `created_by` is required at creation time, and historical accuracy matters.
  Use `restrictOnDelete` so an attempt to delete a user who has driven a
  handover is refused at DB level (matches our immutability stance).
- **Migration order:** `handovers` must be created before `handover_asset`;
  both must come after `assets` and `users` exist.

## Testing strategy

PHPUnit 12 (the project's existing stack), DDEV-run.

1. **`HandoverServiceTest`** (feature)
   - `commit()` for each `HandoverType`: asset `state` + `owner_id` change
     correctly, snapshots match, signature file written.
   - Concurrent state change → `HandoverStateConflictException`, transaction
     rolls back (no DB rows, no signature file left on disk).
   - Bulk handover writes one pivot row + one `handover_completed` activity
     per asset.
   - External recipient → `recipient_user_id` is null, `owner_to_id` is null,
     asset state still transitions.

2. **`HandoverPolicyTest`**
   - View rules pass for users who can view assets; no create / update / delete.

3. **`HandoverWizardTest`** (Filament/Livewire)
   - Wizard advances only when each step's validation passes.
   - Type radio changes filter the asset list at step 1.
   - Bulk-action refuses when one asset is in a disallowed state, listing
     which assets blocked it.
   - Submit with empty canvas → validation error.
   - Signature PNG > 200 KB or non-PNG payload → validation error.
   - Successful submit → notification + history entry on Asset.

4. **`HandoverPdfTest`**
   - Runs the job synchronously; asserts PDF file exists at expected path,
     `pdf_path` is set, and the rendered HTML (before passing to dompdf) is
     substring-grepped for recipient name, each asset's serial number, and
     the terms snapshot.

5. **`HandoverEmailTest`**
   - `Mail::fake()`; asserts mail dispatched with PDF attachment,
     `email_sent_at` populated, recipient_email validation rules enforced
     (invalid email at step 2 → wizard validation fails).

## Open items

None. All design questions resolved during brainstorming.

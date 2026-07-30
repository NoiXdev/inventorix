# Inertia Migration — Spec 5: Handovers

**Date:** 2026-07-14
**Status:** Approved (design)
**Part of:** FilamentPHP → React/Inertia migration (Spec 5 of 9)
**Depends on:** Specs 1–4 (kit, auth, Assets + the asset/owner/state model). Filament remains at `/app-old`.

## Background

Handovers are signed documents recording asset issue/lend/return between the org
and a recipient. The **entire backend is already framework-agnostic and reused
verbatim** — this spec is almost entirely frontend + a thin controller.

Reused as-is:
- `App\Services\HandoverService::commit(HandoverData $data): Handover` — inside one
  DB transaction: validates every selected asset is in an allowed source state for
  the type (collects conflicts and throws on mismatch), decodes + validates the
  signature PNG (base64, PNG magic bytes, `config('handover.signature.max_bytes')`)
  and stores it, creates the `Handover`, writes the `handover_asset` pivot
  (`state_from/to`, `owner_from/to`), updates each asset's `state`/`owner_id`, logs
  activity, and dispatches `GenerateHandoverPdf`.
- `App\DataObjects\HandoverData` DTO: `type` (HandoverType), `recipientKind`
  (RecipientKind), `recipientUserId?`, `recipientName`, `recipientEmail?`,
  `assetIds[]`, `accessories?`, `conditionNotes?`, `termsText`, `signaturePngBase64`,
  `signatureIp?`, `signatureUserAgent?`, `createdById`.
- `HandoverType` (issue/lend/return/return_defect) with `allowedStateFrom()`
  (issue/lend → NEW,STORAGE; return/return_defect → IN_USE,LEND), `stateTo()`,
  `assignsRecipientAsOwner()` (issue/lend). `RecipientKind` (internal/external).
- `GenerateHandoverPdf` (queued) → renders `pdf.handover` (dompdf), stores
  `pdf_path`, emails `HandoverSigned`. The signed `handover.pdf` download route.
- `Handover` model: `recipientUser`, `createdBy`, `assets()` belongsToMany pivot.

Handovers are **immutable** (Filament exposes List + View only; creation is a
wizard action). This spec preserves that: no edit/delete.

## Locked decisions

- **Stepped wizard** (4 steps: Type+assets → Recipient → Details → Review&sign).
- **PDF/email stay queued** (reuse `GenerateHandoverPdf` verbatim); the detail page
  shows "PDF generating…" until `pdf_path`, then a download link.
- **Read-only** list + detail; create via wizard; **no edit/delete**.
- Asset selection is **filtered by the chosen type's allowed source states**
  (client-side via a TS map mirroring `allowedStateFrom`), and re-validated server-side.
- Detail links to the **PDF** for the signature (no separate inline signature route).
- **New "Operations" sidebar group** for Handovers.
- No policy (gate on `auth`); PHPUnit; ddev; pnpm.

## Goals

- Create handovers through a guided wizard that reuses `HandoverService::commit`;
  list + view them; download the generated PDF. `/app-old` Filament unchanged.

## Non-goals

- Editing/deleting handovers; synchronous PDF; an inline signature-image route; a
  generic reusable Stepper; QR/scanning (Spec 8); changes to the service/job/mail/
  PDF/DTO/enums/pivot or DB schema.

## Design

### Backend — `App\Http\Controllers\App\HandoverController`

Routes (authenticated `/app` group), names `app.handovers.*`:
`GET handovers` (index), `GET handovers/create` (wizard), `POST handovers` (store),
`GET handovers/{handover}` (show). No update/destroy. The existing
`handover.pdf` signed route is reused for downloads.

- **index:** base query `Handover::query()->withCount('assets')->with('createdBy')->latest('signed_at')`
  (the `->latest('signed_at')` sets the default order; `TableQuery::applySort` only
  adds an `orderBy` when a `sort` param is present, so it's preserved otherwise),
  through `TableQuery` `->searchable(['recipient_name'])->sortable(['signed_at','type','recipient_name','assets_count'])->paginate()`. Rows:
  `{ id, type, type_label, recipient_name, recipient_kind_label, assets_count,
  created_by_name, signed_at, pdf_ready: (bool) $h->pdf_path, pdf_url: pdf_ready ? URL::signedRoute('handover.pdf', ['handover'=>$h]) : null }`.
- **create:** renders `handovers/create` with:
  - `typeOptions`: `HandoverType::cases()` → `{ value, label: getLabel(), allowedStates: [state values from allowedStateFrom()], assignsOwner: assignsRecipientAsOwner() }`.
  - `recipientKindOptions`: `{value,label}` from `RecipientKind`.
  - `userOptions`: users ordered by name → `{ value: id, label: name, email }`.
  - `assetOptions`: assets (with `assetType`, `model.manufacturer`) → `{ value: id,
    label: "(Manufacturer) Model — serial", state: state->value }`. (All assets;
    the wizard filters selectable ones by type client-side.)
  - `defaultTerms`: `config('handover.terms')`.
- **store(`HandoverRequest`):** build `HandoverData` from validated input +
  `createdById = $request->user()->id`, `signatureIp = $request->ip()`,
  `signatureUserAgent = $request->userAgent()`; wrap `HandoverService::commit` in a
  try/catch — on the service's state-conflict exception (assets no longer in an
  allowed state; a race the FormRequest already guards) redirect back with a
  `asset_ids` error; on the signature-decoding exception, a `signature_png` error.
  On success `to_route('app.handovers.show', $handover)->with('success', ...)`.
- **show:** `$handover->load('recipientUser','createdBy','assets.model.manufacturer','assets.assetType')`;
  return `{ handover: {...fields, type_label, recipient_kind_label, signed_at,
  pdf_ready, pdf_url}, assets: [{ id, label, state_from, state_to, owner_from,
  owner_to }] (from the pivot) }`.

### Validation — `HandoverRequest`

- `type`: required, `Rule::enum(HandoverType::class)`.
- `recipient_kind`: required, `Rule::enum(RecipientKind::class)`.
- `recipient_user_id`: `required_if:recipient_kind,internal`, `nullable`, `uuid`,
  `exists:users,id`.
- `recipient_name`: required, string, max 255.
- `recipient_email`: nullable, email, max 255.
- `asset_ids`: required, array, min 1; `asset_ids.*`: uuid, `exists:assets,id`.
- A `withValidator`/`after` rule: load the selected assets and fail `asset_ids` if
  any is not currently in `HandoverType::from($type)->allowedStateFrom()` (clean
  pre-check mirroring the service).
- `accessories`, `condition_notes`: nullable string.
- `terms_text`: required, string.
- `signature_png`: required, string (base64; content validated by the service).
- `authorize()` returns true.

### Frontend

- **`SignaturePad`** (`resources/js/components/handover/signature-pad.tsx`), props
  `{ value: string; onChange: (base64: string) => void; width?: number; height?: number }`:
  a `<canvas>` (default 600×200, white fill) with pointer + touch drawing, a
  **Clear** button; on stroke-end it emits `canvas.toDataURL('image/png')` with the
  `data:image/png;base64,` prefix stripped. Empty when cleared.
- **`handover-wizard.tsx`** (create page): client `step` state (1–4) with a progress
  indicator + Next/Back; one Inertia `useForm({ type, asset_ids: [], recipient_kind,
  recipient_user_id, recipient_name, recipient_email, accessories, condition_notes,
  terms_text: defaultTerms, signature_png })`:
  1. **Type + assets** — `SelectField` type; an **asset multi-select** (a scrollable,
     text-filterable checkbox list) restricted to `assetOptions` whose `state` is in
     the selected type's `allowedStates`; changing type clears now-ineligible picks.
  2. **Recipient** — `SelectField` kind; internal → user `SelectField` (on pick, set
     `recipient_name`/`recipient_email` from the option, still editable); external →
     `TextField` name + email.
  3. **Details** — `Textarea` accessories, condition_notes, terms_text.
  4. **Review & sign** — read-only summary (type, recipient, chosen assets) +
     `SignaturePad` bound to `signature_png`; **Create handover** submit
     (`form.post('/app/handovers')`). Next is disabled until each step's required
     fields are set; final submit disabled until the signature is non-empty.
     On a server error, jump to the step owning the errored field.
- **List** (`handovers/index`): `DataTable` — Signed at, Type (badge via a small
  type→variant map or plain Badge), Recipient (+ kind), Assets (count), Created by;
  row actions: **View** (→ show) and **PDF** (anchor to `pdf_url`, shown only when
  `pdf_ready`). "New handover" → the wizard.
- **Detail** (`handovers/show`): header (type badge + signed_at), recipient block,
  an assets table (label, state_from→state_to), and a **Download PDF** button when
  `pdf_ready` else a muted "PDF is generating…" line. Back to list.
- **Nav**: new **Operations** group with **Handovers** (icon e.g. `FileSignature`).

### Reuse

Entire backend flow; `DataTable`, `SelectField`, `TextField`, `Textarea`, `Badge`,
`Button`, `Card`. New: `SignaturePad`, the wizard, the asset multi-select
(inline in the wizard).

## Testing

- **PHPUnit `HandoverControllerTest`** (`Queue::fake()`):
  - store (type=issue): creates a `Handover` (signed_at set, created_by = actor),
    the selected NEW/STORAGE assets flip to IN_USE and get the recipient as owner,
    the `handover_asset` pivot has correct `state_from/to`/`owner_from/to`, and
    `GenerateHandoverPdf` was dispatched. store (type=return): IN_USE/LEND → STORAGE,
    owner cleared.
  - validation: required fields; `recipient_user_id` required_if internal; selecting
    an asset **not** in an allowed source state → `asset_ids` error, nothing
    committed; missing signature → error.
  - index lists handovers with `assets_count` + `pdf_ready` (false before the job
    runs); show returns the detail incl. pivot state transitions.
  - auth: guest redirected.
  - (The existing `HandoverService` unit tests remain the authority on the domain
    logic — not duplicated here.)
- **Vitest**: `SignaturePad` (drawing emits a non-empty base64; Clear empties it);
  `handover-wizard` (Next/Back step nav; the asset list filters by the selected
  type's allowed states; internal vs external recipient fields toggle; the final
  submit is gated on a signature).

## Open questions / follow-ups (later)

- Inline signature-image display on the detail page; handover edit/void flow; a
  reusable Stepper; QR-assisted asset selection (Spec 8).
- Migration continues with Spec 6 (Dashboard & Reports) after this.

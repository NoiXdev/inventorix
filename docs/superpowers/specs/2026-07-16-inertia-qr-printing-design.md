# Inertia Migration — Spec 8: QR / Printing

**Date:** 2026-07-16
**Status:** Approved (design)
**Part of:** FilamentPHP → React/Inertia migration (Spec 8 of 9)
**Depends on:** Specs 1–7 (kit, auth, assets, form kit, DataTable, topbar). Filament QR pages remain at `/app-old`.

## Background

Three related QR features form one loop: **generate** blank UUID labels → **print** them on a Brother label printer → scan the QR on a device → **open** its asset if it exists, else **create** it with that UUID pre-filled.

The heavy lifting is a framework-agnostic TypeScript print engine under `resources/js/plugins/qr-print/` with its own vitest tests (already in the JS suite):
- `controller.ts` (`PrintController`: `pair()`, `tryReconnect()`, `print(job, onProgress)`, `cancel()`, `close()`), `layout.ts` (`composeLabel(item, roll, layout): Promise<ImageData>`), `rasterizer.ts`, `brother-protocol.ts`, `webusb-transport.ts` (`WebUsbTransport`, `isWebUsbAvailable()`), `dk-rolls.ts` (`DK_ROLLS`, `getRollById`), `qr.ts` (encodes the **raw asset UUID**), `types.ts` (`LabelItem`, `RollSpec`, `LayoutKind` = `qr-only|qr-uuid|qr-asset`, `PrintJob`, `PrintProgress`, `OpenEventDetail`).
- `scanner-camera-error.ts` (`classifyCameraError`) — pure, reusable.

Only the **UI glue** is Filament/Alpine-coupled and is NOT reused: `modal.ts` (Alpine modal), `index.ts` (Alpine registration), `scanner.ts` (DOM-query wiring), the Filament `PrintQrAction`, the `QrCodeGenerator` Filament page + `QrCodeGeneratorController` (route `qg`), and the `Scanner` Livewire component. These stay at `/app-old`, untouched.

The QR encodes the raw UUID; the old `Scanner` validated `uuid` and redirected to the asset edit page (exists) or create with `forceId` (missing). The new `AssetController@store` currently auto-generates the UUID and has no forced-id path — this spec adds one.

## Locked decisions

- Port **all three** features (print, generator, scanner) — the full loop.
- **Scanner = a topbar dialog** (a global scan button), not a dedicated page.
- Reuse the print engine + `classifyCameraError` verbatim; replace only the Alpine/Filament UI with React.
- Add **opt-in row selection** to the shared `DataTable` (gated behind a new prop) for bulk print.
- German UI strings on the print modal (matching the original). Auth-only gate. PHPUnit; Vitest; ddev; pnpm.

## Goals

- From `/app`: print QR labels for one or many assets (Brother WebUSB); generate + download/print blank UUID labels; scan a QR to open or create an asset. `/app-old` unchanged.

## Non-goals

- Changing the print engine / roll specs / Brother protocol / QR encoding; a non-WebUSB print fallback; Firefox/Safari WebUSB (Chromium-only, inherent); touching the Filament QR pages, `QrCodeGeneratorController`, the `qg` route, or the `Scanner` Livewire component. No DB/schema changes.

## Design

### Backend (new `/app`, names `app.*`)

- **Forced UUID on asset create:**
  - `AssetRequest`: add `'id'` to the `prepareForValidation` nullable list (so `''` → `null`), and add rule `'id' => ['nullable', 'uuid', 'unique:assets,id']`.
  - `AssetController@create`: read `?forceId=` (validated uuid-or-null), pass `forceId` prop to `assets/create`.
  - `AssetController@store`: pull `id` out of validated data; build the asset, set the supplied `id` explicitly when present (else `HasUuids` auto-generates), then `syncTags`. Update path never sends `id` (nulled → ignored), so the `unique` rule can't fail on edit.
- **`ScanController@resolve`** (`GET scan/resolve`): validate `code` as uuid; `Asset::find($code)` → redirect to `app.assets.show`; else → redirect to `app.assets.create` with `?forceId=$code`; invalid/empty → back with an error flash.
- **`GeneratorController`** (names `app.qr-generator.*`):
  - `index` → `qr-generator/index` page.
  - `download` (`GET qr-generator/download?amount=N`) → `text/plain` attachment of N fresh UUIDs, each guaranteed not to match an existing asset (same loop as the Filament controller), `amount` clamped to a sane max (e.g. 1..1000).
  - `codes` (`POST qr-generator/codes {amount}`) → JSON `{ uuids: string[] }` (fresh, collision-free) for the print modal.

### Frontend (new `/app`)

- **`QrPrintModal`** (`resources/js/components/qr-print/qr-print-modal.tsx`), a shadcn `Dialog` replicating `modal.ts` via the reused engine. Props `{ open: boolean; items: LabelItem[]; onClose: () => void }`. State/behaviour: `isWebUsbAvailable()` gate + notice; `tryReconnect()` on open; **roll** select (`DK_ROLLS`, persisted `qrPrint.lastRollId`, default `dk-11209`); **layout** select (`qr-only`/`qr-uuid`/`qr-asset`, persisted `qrPrint.lastLayout`, default `qr-uuid`; `qr-asset` disabled unless every item has `metadata`); a **preview** `<canvas>` painted from `composeLabel(items[0], roll, layout)` via `useEffect`; **Pair**, **Print** (guarded on paired; `onProgress` → completed/total), **Cancel**; German status/error strings.
- **Asset list** (`assets/index.tsx`): a single-row **Print QR** action (opens the modal with `[{uuid, metadata}]`) and, via a new opt-in `DataTable` selection, a **Print QR (N)** bulk action (opens with all selected rows' items). Item metadata is `{ modelName: model_name, serial: serial_number }`, included only when both are present.
- **`DataTable`** (`resources/js/components/data-table/data-table.tsx`): new optional props `enableSelection?: boolean`, `getRowId?: (row) => string`, `renderBulkActions?: (selected: T[], clear: () => void) => ReactNode`. When enabled: a leading checkbox column (header = select-all-on-page), TanStack `rowSelection` state keyed by `getRowId`, and a bulk bar above the table rendering `renderBulkActions` when any row is selected. Tables that don't pass `enableSelection` are unchanged.
- **Scan dialog** (`resources/js/components/scan/scan-dialog.tsx` + a scan button in `app-topbar.tsx`): a `Dialog` with a `<video>` viewfinder driven by `qr-scanner`, a Start button, permission/error messages via `classifyCameraError`, and a manual-UUID text input + submit. On a decoded/submitted value → `router.get('/app/scan/resolve', { code })`. Camera stops on close/unmount.
- **QR Generator page** (`qr-generator/index.tsx`): an amount `NumberField`, a **Download TXT** anchor (`…/download?amount=`), and a **Print labels** button that `fetch`es `…/codes` then opens `QrPrintModal` with `uuids.map(u => ({ uuid: u }))`. New **Tools** nav group with a **QR Generator** entry (scan stays topbar-global).
- **`AssetForm`**: accept an optional `forceId?: string`; include `id` in the `useForm` data (`forceId ?? ''`) and, when `forceId` is set, show a read-only "Asset ID" field. The create page passes `forceId`; edit does not.

### Reuse

The whole `qr-print` engine + `classifyCameraError`; `qr-scanner`/`qrcode` deps; `Dialog`, `Button`, `NumberField`, `SelectField`, `DataTable`, `AppLayout`, `app-topbar`. New: `QrPrintModal`, `ScanDialog`, the generator page, `ScanController`, `GeneratorController`, the DataTable selection, the asset forced-id path.

## Testing

- **PHPUnit**: `AssetController@store` with a forced `id` persists that exact UUID; a duplicate `id` → validation error; an invalid `id` → error; update still works (no `id` collision). `ScanController@resolve`: existing UUID → redirect to the asset; unknown UUID → redirect to `create?forceId=`; invalid → error. `GeneratorController@download` returns N unique lines with the right content-type; `@codes` returns N collision-free UUIDs (JSON); `amount` clamped. Auth-gated.
- **Vitest** (mock `@/plugins/qr-print/*` engine + `isWebUsbAvailable` + WebUSB/camera): `QrPrintModal` — roll/layout selects, `qr-asset` disabled without metadata, Print gated on pair, progress reflected, WebUSB-unsupported notice. `DataTable` selection — select/select-all/clear, `renderBulkActions` receives the selected rows. `assets/index` — single + bulk Print buttons open the modal with the correct items. `ScanDialog` — manual submit navigates to resolve; camera-error message via `classifyCameraError`. `qr-generator` — download anchor href; Print fetches codes and opens the modal.

## Open questions / follow-ups (later)

- A non-WebUSB (PDF/system-print) fallback for non-Chromium browsers; multi-select across pages; scanning formats beyond the asset UUID. Cutover (Spec 9) removes the Filament QR pages, `PrintQrAction`, the Alpine `modal.ts`/`index.ts`/`scanner.ts`, the `Scanner` Livewire component, and the `qg` route; the `qr-print` engine + `classifyCameraError` remain.

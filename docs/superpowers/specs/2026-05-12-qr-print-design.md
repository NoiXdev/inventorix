# QR Print — Design

**Date:** 2026-05-12
**Status:** Draft for review
**Author:** Daniel Elskamp (with Claude)

## Summary

A "QR Print" feature for direct printing of QR-code labels to a **Brother QL-800** label printer over the **WebUSB API**. Three trigger points: the existing QR Code Generator page (batch), an Asset detail page (single), and an Asset list bulk action (selected). One shared modal handles roll selection, layout choice, preview, printer pairing, and progress.

No backend state, no print history. All printer communication happens in the browser.

## Goals

- Print QR-code labels directly to a Brother QL-800 without OS driver installation, from inside the Filament app.
- Reuse the existing UUID generation logic in `QrCodeGeneratorController` without disturbing the current TXT-download flow. QR rendering is browser-side (new `qrcode` npm dependency); we do **not** reuse the server-side `simplesoftwareio/simple-qrcode` package for printing — the simpler pipeline is browser-only end-to-end.
- Support multiple DK label rolls via a UI selector (DK-22205, DK-11209, DK-11201 in v1).
- Three label layouts (QR-only, QR + UUID, QR + asset info), with a layout architecture that admits more layouts later.
- Clear error handling for the predictable WebUSB and printer failure modes.

## Non-Goals (v1)

- Cross-browser support beyond Chromium. Safari and Firefox do not implement WebUSB and we accept that.
- Print history / audit log of which labels were printed when.
- Server-side rendering of label PDFs as a "fallback" path. We will document the limitation in the UI rather than build a parallel pipeline.
- Network/Bluetooth/AirPrint variants of the QL-800. USB only.
- TIFF Packbits raster compression. The uncompressed payload is small enough at USB 2.0 speeds.
- Authentication/authorization changes. The feature inherits whatever permissions the existing QR Generator page, Asset detail page, and Asset list table already enforce.

## Constraints

- **Browser:** Chromium-based browsers only. WebUSB requires a user gesture for pairing and a secure context (HTTPS or localhost).
- **Printer:** Brother QL-800 over USB. Vendor ID `0x04F9`, Product ID `0x209B`.
- **Project conventions:** Laravel 13 + Filament 5, TypeScript + Vite + Tailwind v4, DDEV for local. All CLI invocations go through `ddev exec`.

## Architecture

A single TypeScript module per concern under `resources/js/plugins/qr-print/`. PHP changes are limited to wiring up trigger actions; no new controllers, routes, models, or tables.

```
resources/js/plugins/qr-print/
  index.ts              # public entrypoint; registers Filament event listeners
  controller.ts         # orchestrates: collect items → render → rasterize → print
  qr.ts                 # QR generation (qrcode npm package) → ImageData
  layout.ts             # composes label on a <canvas>: QR-only | QR+UUID | QR+asset info
  rasterizer.ts         # canvas → 1-bit Brother raster lines (per DK roll)
  brother-protocol.ts   # builds the binary command stream for QL-800
  webusb-transport.ts   # navigator.usb wrapper: pair, claim, write, read status, release
  dk-rolls.ts           # DK roll definitions (px width/height, media-type byte)
  modal/                # Alpine-driven modal component (HTML + behavior)
  types.ts              # PrintJob, LabelItem, RollSpec, PrintProgress
```

### Why this shape

- **One TS module per concern** keeps each file under context-friendly size. The rasterizer can be tested with golden bitmap fixtures without knowing anything about the protocol; the protocol can be tested without knowing anything about USB; the transport can be mocked with a fake `navigator.usb`.
- **No new backend state** means the server's job is just to gather data and hand it to the browser. Server tests stay simple.
- **DOM event boundary between Livewire and TS** — Filament actions emit a single `qr-print:open` browser event with the payload; the TS controller handles everything from there. This means Filament has no awareness of WebUSB, and the TS layer has no awareness of Livewire.

## User Experience

### Trigger surfaces (Filament)

1. **`QrCodeGenerator` page** — alongside the existing "Generate" button (which still downloads `.txt`), add a **"Drucken"** button. On click: server generates N collision-free UUIDs using the existing logic in `QrCodeGeneratorController` and dispatches `qr-print:open` with `items = LabelItem[]` where each item has only a `uuid`.
2. **`AssetResource` detail page** — header `Action::make('printQr')` labeled **"QR drucken"**. Dispatches `qr-print:open` with a single-element `items` array containing the asset's UUID plus metadata (`modelName`, `serial`).
3. **`AssetResource` table** — `BulkAction::make('printQr')` labeled **"QR drucken"**. Dispatches `qr-print:open` with one `LabelItem` per selected asset, each including metadata.

### The print modal

Rendered once per panel page via a small Alpine component, hidden until `qr-print:open` fires.

- **Roll selector** — dropdown of `DK_ROLLS` entries (initially DK-22205 62mm continuous, DK-11209 29×62mm, DK-11201 29×90mm). Default is the last roll used (persisted in `localStorage`).
- **Layout selector** — radio: `QR only` / `QR + UUID` / `QR + asset info`. "QR + asset info" is disabled (with explanatory tooltip) when any item in the payload lacks metadata — i.e., for the batch-generator case.
- **Preview pane** — renders the first label using the same `layout.ts` + canvas pipeline used at print time. Updates live on roll/layout change.
- **Printer status row** — shows "Nicht verbunden" with a `[Drucker verbinden]` button when no device is paired; "Verbunden: Brother QL-800" with a `[Trennen]` link when paired. Pairing uses `navigator.usb.requestDevice({ filters: [{ vendorId: 0x04F9, productId: 0x209B }] })`; reconnection on subsequent loads via `navigator.usb.getDevices()`.
- **Footer** — quantity summary ("47 Etiketten"), `[Abbrechen]`, `[Drucken]`. During print, the Drucken button becomes a progress bar with a `[Stoppen]` button.

### Print flow

```
Filament action
  → server: build LabelItem[] (UUID, optional {modelName, serial})
  → Livewire dispatch('qr-print:open', { items, defaults })
  → TS controller opens modal, populates state
  → user picks roll + layout, clicks Drucken
  → for each item:
      qr.generate(uuid)  →  ImageData
      layout.compose(item, roll, layout) → HTMLCanvasElement
      rasterizer.rasterize(canvas, roll) → Uint8Array[]  (one entry per raster line)
      protocol.appendLabel(rasters, isLast)
  → protocol.finalize() → single Uint8Array containing the full job
  → transport.send(bytes, onProgress)
  → on success: close modal with toast "47 von 47 Etiketten gedruckt"
  → on cancel/error: keep modal open, show "Gedruckt: 23 von 47 — Druck pausiert"
```

### Job packing and cancel semantics

A single Brother raster stream contains multiple labels separated by `0x1A` (print-with-feed) terminators. We pack the whole batch into **one job stream** rather than one job per label — this avoids per-label reinit overhead and matches the printer's expected idiom.

Progress is reported by hooking the WebUSB write loop: the protocol builder annotates byte offsets per label, the transport reports bytes-written, and the controller maps that back to label index.

"Stoppen" stops sending further chunks immediately, then sends an **invalidate + initialize** sequence (`200 × 0x00` followed by `0x1B 0x40`) to put the printer back in a clean state for the next job. Brother's raster reference does not define a clean mid-stream "cancel current job" command; in practice, anything the printer has already buffered will still print. We surface this clearly in the UI: *"Bis zu 2 weitere Etiketten können noch ausgegeben werden."*

## Components in detail

### `dk-rolls.ts`

Static lookup. Measurements assume 300dpi nominal in both axes (QL-800 spec). The print head is 720 pins wide regardless of media; partial-width media uses a printable sub-range of those pins, which is why each entry below also defines `printableStartByte` / `printableEndByte`. Exact pixel heights for die-cut rolls are taken from Brother's reference; the smoke test in milestone 1 is the first check that they match reality.

| Roll | Width (mm) | Length (mm) | Print width (px) | Print height (px) | Media byte |
|---|---|---|---|---|---|
| DK-22205 (continuous 62mm) | 62 | user-chosen | 696 | variable (default 696 — i.e., ~62mm square) | `0x0A` |
| DK-11209 (29×62mm) | 29 | 62 | 306 | 731 | `0x0B` |
| DK-11201 (29×90mm) | 29 | 90 | 306 | 1064 | `0x0B` |

Each entry also defines `printableStartByte` and `printableEndByte` so the rasterizer can correctly zero-pad the 90-byte (720-pin) raster lines for partial-width media.

**Risk:** these numbers are from Brother's *Raster Command Reference (QL-800)*. They cannot be validated against a real printer from a code-only review. The plan includes a manual smoke test (print 1 label per roll size) as the first verifiable milestone.

### `qr.ts`

Wraps the `qrcode` npm package. Encodes a UUID at error-correction level `M`. Returns an `ImageData` (or canvas) at a fixed module-pixel size that the layout layer scales.

### `layout.ts`

Pure-canvas composition. Three layouts in v1:

- **QR only** — QR centered, scaled to fit within `min(printWidth, printHeight) − 2 × padding`.
- **QR + UUID** — QR on top, short UUID below in a small sans-serif font.
- **QR + asset info** — QR on left, two lines of text on right: `modelName` (truncated to fit, with ellipsis) and `serial`. Falls back to "QR + UUID" if metadata is missing on an item.

Adding a new layout means adding a new function and a new enum entry — nothing else changes.

### `rasterizer.ts`

Canvas → array of `Uint8Array` (one per raster line, 90 bytes / 720 bits). Pure function. Threshold-based 1-bit conversion (luminance < 128 → black). No dithering in v1.

### `brother-protocol.ts`

Builds the binary command stream. See *Brother raster protocol* section below for the exact byte sequence.

### `webusb-transport.ts`

Thin wrapper around `navigator.usb`. Responsibilities:
- `pair()` — invoke `requestDevice` with VID/PID filter; persist nothing.
- `getPairedDevice()` — call `getDevices()` and return the first matching QL-800 if any.
- `open(device)` — open, select configuration, claim interface, return endpoints.
- `send(bytes, onProgress)` — chunked bulk-OUT write with progress callbacks.
- `readStatus()` — async loop reading 32-byte status packets from bulk-IN.
- `close()` — release interface, close device.

### `modal/` (Alpine component)

The modal is registered in a global blade partial included by the panel's app layout, so all three trigger surfaces see the same modal. State is local to the Alpine component; cross-component communication is exclusively via the `qr-print:open` browser event.

## Brother QL-800 raster protocol

All byte sequences per Brother's public *Raster Command Reference (QL-800)*.

### Per-job header (sent once at job start)

1. **Invalidate** — 200 × `0x00`. Flushes any half-baked command in the printer.
2. **Initialize** — `1B 40` (`ESC @`).
3. **Switch to raster mode** — `1B 69 61 01`.
4. **Status notification on** — `1B 69 21 00`. Enables async status reads from bulk-IN so we can surface "Cover open", "No paper", etc.

### Per-label block (repeated for each label in the job)

5. **Print information** — `1B 69 7A` + 10 bytes: media type, width (mm), length (mm), raster line count (4 bytes LE), page index (4 bytes LE), fixed `0x00`. DK-roll spec supplies media type + width + length.
6. **Various mode** — `1B 69 4D 40` (auto-cut enabled).
7. **Advanced mode** — `1B 69 4B 08` (cut at end, no half-cut).
8. **Margin** — `1B 69 64 23 00` (35-dot feed margin, Brother default).
9. **Compression off** — `4D 00`.
10. **Raster lines** — for each of `N` lines: `67 00 5A` + 90 bytes of pixel data. The DK-roll spec defines `printableStartByte` / `printableEndByte` so unprinted pin positions are zero-padded correctly.
11. **End-of-page** — `0C` (print, feed to next label) for intermediate labels; `1A` (print, eject) for the final label of the job.

### Transport

- VID `0x04F9`, PID `0x209B`.
- Single configuration, single interface. Bulk OUT for raster, bulk IN for status (32-byte packets).
- Claim interface, chunk writes to 16KB or device-max-packet, release on close.
- Status reads run in parallel during the print so we surface real-time errors rather than a generic timeout.

## Error handling

| Failure | Where caught | User sees |
|---|---|---|
| WebUSB not available (non-Chromium) | Modal open | Inline notice: *"Web USB Druck wird nur in Chrome/Edge unterstützt. Bitte einen Chromium-Browser verwenden."* Modal stays open in read-only preview mode. |
| User dismisses `requestDevice` prompt | Pairing button click | Status row reverts to "Nicht verbunden". No toast — it's a user choice. |
| No QL-800 found / wrong device picked | After `requestDevice` resolves | *"Gerät ist kein Brother QL-800. Bitte Drucker erneut auswählen."* |
| Interface claim fails (printer held by OS spooler) | Pairing or print start | *"Drucker wird bereits vom System verwendet. Bitte den Brother-Drucker-Spooler beenden oder den Drucker neu einstecken."* |
| Status reports "Cover open" / "No paper" / "Wrong media" | Async status-read loop during print | Print pauses; modal shows the specific error from the status byte map; `[Erneut versuchen]` retries from the current label index. |
| Bulk write rejects / device disconnects mid-job | `transport.send` rejection | Print pauses; *"Verbindung zum Drucker verloren bei Etikett X von N"*; `[Wieder verbinden]` retries pairing then resumes. |
| QR encode failure (data too large for ECC) | `qr.generate` throws | Surface *"Layout zu klein für QR-Code auf diesem Etikett"* and abort the job. Not expected with UUIDs. |
| Cancel mid-job | `[Stoppen]` button | Stop further writes; send invalidate + initialize to reset the printer for the next job; show *"Gestoppt nach X von N Etiketten — bis zu 2 weitere Etiketten können noch ausgegeben werden."* |

### Idempotency

No persistence between attempts. "Retry" simply re-runs the print starting at a chosen label index. UUIDs from the batch generator are stateless — re-printing is harmless. Re-printing an asset's label is also harmless (same UUID encoded).

## Testing strategy

### Server-side (PHPUnit, existing `tests/Feature`)

- Feature test on `QrCodeGenerator` page: clicking "Drucken" dispatches `qr-print:open` with the expected `items` payload (N UUIDs, no metadata).
- Feature test on `AssetResource` detail action: dispatches with a single-item payload including `modelName` and `serial`.
- Feature test on `AssetResource` bulk action: dispatches with multiple items.
- Regression test on the existing TXT-download path: still produces `text/plain` with N newline-separated UUIDs.

### Client-side unit (Vitest — new; adding it to the project is part of the plan)

- `rasterizer.test.ts` — feed a known 8×8 canvas, assert exact byte output. Golden fixtures: all-black, all-white, single pixel per corner, a known small QR.
- `brother-protocol.test.ts` — given a `PrintJob` of 2 labels on DK-11209, assert the full byte stream matches a recorded golden fixture. This is the load-bearing test for protocol stability.
- `dk-rolls.test.ts` — pin the lookup table so typos can't escape review.
- `qr.test.ts` — encode a known UUID, assert module count.

### Mock-USB integration (Vitest with a fake `navigator.usb`)

One end-to-end test that mocks the USB device, runs a 3-label job, and asserts:
- The controller sent the expected concatenated byte stream.
- Progress callbacks fired exactly 3 times with the expected label indices.
- A simulated mid-job error pauses the controller in the expected state.

### Manual smoke tests (documented; required after first deploy and after any change to `dk-rolls.ts` or `brother-protocol.ts`)

1. Pair the printer in Chrome on a clean profile.
2. Print 1 label of each layout on DK-11209.
3. Print a batch of 5 labels — verify auto-cut between each.
4. Mid-print, open the cover — verify "Cover open" error appears.
5. Print with no labels loaded — verify "No paper" error.

### Risk acknowledgment

Golden fixtures pin our *understanding* of the Brother protocol. If our understanding of byte X is wrong, the tests confirm we send byte X consistently — they don't confirm the printer accepts it. Manual smoke tests are where ground truth lives. They are part of the spec, not an afterthought.

## New dependencies

- **`qrcode`** (npm, runtime) — QR generation in the browser.
- **`vitest`** (npm, dev) — client-side test runner. The project currently has no JS test runner; adding one is a prerequisite for the testing strategy below. Configured via `vite.config.ts` (Vitest reuses Vite config).
- **`@vitest/ui`** (npm, dev, optional) — pleasant test UI; cheap to add.

No new PHP packages. No new composer dependencies.

## Open questions / deferred decisions

- **Default DK roll** — first-run default before `localStorage` has a value. Tentatively DK-11209 (most likely asset-tag use case); confirm during implementation when we know what's loaded by default.
- **DK-22205 default label length** — 62mm square is a reasonable default for "QR-only", but for "QR + UUID" or "QR + asset info" we may want longer. Deferred to layout prototyping.
- **Localization** — strings in this spec are German to match the existing Filament panel. If/when an `en` locale is added, all user-facing strings go through `__()`.

## Implementation milestones (preview — full plan in writing-plans)

1. **Skeleton + smoke test:** stub `brother-protocol.ts` with hardcoded one-label-of-Xs payload; pair printer; print one label end-to-end. **Manual verification on real hardware before proceeding.**
2. **Rasterizer + DK-11209 layout:** real canvas → real raster; print a single real label with QR-only layout.
3. **All three layouts** + roll selector + preview modal.
4. **All three trigger surfaces** (generator, asset detail, bulk).
5. **Error handling + cancel + status reads.**
6. **Vitest + golden fixtures + integration test.**

Each milestone ends with the manual smoke tests for the surfaces touched.

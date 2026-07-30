# Inertia Migration — QR / Printing (Spec 8) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring the QR loop to the new `/app` — print QR labels (Brother WebUSB, single + bulk), a QR generator (batch UUIDs → TXT/print), and a topbar camera scanner (scan → open or create-with-forced-UUID) — reusing the existing framework-agnostic print engine.

**Architecture:** The TS print engine under `resources/js/plugins/qr-print/*` and `scanner-camera-error.ts` are reused verbatim. New React components (`QrPrintModal`, `ScanDialog`) drive them; new controllers (`ScanController`, `GeneratorController`) + an asset forced-UUID path back them; the shared `DataTable` gains opt-in row selection for bulk print.

**Tech Stack:** Laravel 13/PHP 8.4, Inertia v2 + React 19 + TS, shadcn/ui, `qr-scanner`, `qrcode`, WebUSB, PHPUnit, Vitest. ddev; pnpm.

## Global Constraints

- ddev for all commands; **PHP tests are PHPUnit**; JS tests are **Vitest** (`pnpm exec vitest run`); if `pnpm` missing, `ddev exec corepack enable`.
- **Reuse verbatim, do NOT modify:** everything under `resources/js/plugins/qr-print/` except that we ADD new files elsewhere; `resources/js/plugins/scanner-camera-error.ts`; the Filament QR pages, `PrintQrAction`, Alpine `modal.ts`/`index.ts`, `scanner.ts`, `Scanner` Livewire, `QrCodeGeneratorController`, the `qg` route — all untouched. No DB/schema changes.
- QR encodes the **raw asset UUID**. Import the engine via `@/plugins/qr-print/...` (`@` → `resources/js`).
- WebUSB is Chromium-only over HTTPS; the modal must degrade gracefully (`isWebUsbAvailable()` notice). Camera needs HTTPS + permission.
- Route names `app.*`, authenticated `/app` group. Auth-only gate. Pint is a CI gate — plain `<?php`+blank+namespace; run `pint` on new PHP files. Commit trailer:
  `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`

## Engine API (reused; for reference)

- `PrintController` (`@/plugins/qr-print/controller`): `pair()`, `tryReconnect(): Promise<boolean>`, `print(job: PrintJob, onProgress?: (p: PrintProgress) => void)`, `cancel()`, `close()`.
- `composeLabel(item: LabelItem, roll: RollSpec, layout: LayoutKind): Promise<ImageData>` (`@/plugins/qr-print/layout`).
- `isWebUsbAvailable(): boolean` (`@/plugins/qr-print/webusb-transport`).
- `DK_ROLLS`, `getRollById(id)` (`@/plugins/qr-print/dk-rolls`).
- Types (`@/plugins/qr-print/types`): `LabelItem = { uuid: string; metadata?: { modelName: string; serial: string } }`, `LayoutKind = 'qr-only'|'qr-uuid'|'qr-asset'`, `RollSpec`, `PrintJob = { items, roll, layout }`, `PrintProgress = { completedLabels, totalLabels }`.
- `classifyCameraError(err): 'permission'|'not_found'|'generic'` (`@/plugins/scanner-camera-error`).

---

### Task 1: Backend — asset forced UUID + scan resolve + generator

**Files:**
- Modify: `app/Http/Requests/App/AssetRequest.php`, `app/Http/Controllers/App/AssetController.php`, `routes/web.php`
- Create: `app/Http/Controllers/App/ScanController.php`, `app/Http/Controllers/App/GeneratorController.php`, `resources/js/pages/qr-generator/index.tsx` (placeholder — Task 5 replaces)
- Test: `tests/Feature/App/ScanControllerTest.php`, `tests/Feature/App/GeneratorControllerTest.php`, and additions to `tests/Feature/App/AssetControllerTest.php` (or a new `AssetForcedIdTest.php`)

**Interfaces:** Produces routes `app.scan.resolve`, `app.qr-generator.index|download|codes`; the `assets/create` `forceId` prop (Task 5 consumes); the asset store forced-id behaviour.

- [ ] **Step 1: `AssetRequest` — allow a forced id**

In `prepareForValidation`, add `'id'` to the `$nullable` array (so `''` → `null`). In `rules()`, add:
```php
'id' => ['nullable', 'uuid', 'unique:assets,id'],
```

- [ ] **Step 2: `AssetController` — create passes forceId, store honours id**

`create()`:
```php
public function create(Request $request): Response
{
    $forceId = $request->query('forceId');
    if (! is_string($forceId) || ! \Illuminate\Support\Str::isUuid($forceId)) {
        $forceId = null;
    }

    return Inertia::render('assets/create', array_merge($this->formOptions(), [
        'forceId' => $forceId,
    ]));
}
```
(Add `use Illuminate\Http\Request;` if not present.)

`store()` — pull `id` out and set it explicitly (mass-assignment excludes the non-fillable `id`):
```php
public function store(AssetRequest $request): RedirectResponse
{
    $data = $request->validated();
    $tags = $data['tags'] ?? [];
    $forcedId = $data['id'] ?? null;
    unset($data['tags'], $data['id']);

    $asset = new Asset($data);
    if (! empty($forcedId)) {
        $asset->id = $forcedId; // HasUuids only auto-generates when the key is empty
    }
    $asset->save();
    $asset->syncTags($tags);

    return to_route('app.assets.index')->with('success', 'Asset created.');
}
```
(`update()` is unchanged — it never receives `id`.)

- [ ] **Step 3: `ScanController`**

```php
<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScanController extends Controller
{
    public function resolve(Request $request): RedirectResponse
    {
        $code = (string) $request->query('code', '');

        if (! Str::isUuid($code)) {
            return back()->with('error', 'Ungültiger QR-Code.');
        }

        if (Asset::query()->whereKey($code)->exists()) {
            return to_route('app.assets.show', $code);
        }

        return to_route('app.assets.create', ['forceId' => $code]);
    }
}
```

- [ ] **Step 4: `GeneratorController`**

```php
<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class GeneratorController extends Controller
{
    private const MAX = 1000;

    public function index(): Response
    {
        return Inertia::render('qr-generator/index');
    }

    public function download(Request $request): HttpResponse
    {
        $codes = $this->freshUuids($this->amount($request));

        return response(implode("\n", $codes), 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="generated.txt"',
        ]);
    }

    public function codes(Request $request): JsonResponse
    {
        return response()->json(['uuids' => $this->freshUuids($this->amount($request))]);
    }

    private function amount(Request $request): int
    {
        return max(1, min(self::MAX, (int) $request->input('amount', 20)));
    }

    /** @return array<int, string> */
    private function freshUuids(int $amount): array
    {
        $codes = [];
        while (count($codes) < $amount) {
            $uuid = (string) Str::uuid();
            if (! Asset::query()->whereKey($uuid)->exists()) {
                $codes[] = $uuid;
            }
        }

        return $codes;
    }
}
```

- [ ] **Step 5: Routes** — in `routes/web.php`, inside the `/app` group, import the two controllers and add (place `scan/resolve` and the `qr-generator` routes near the other feature routes; none conflict with `assets/{asset}`):
```php
Route::get('scan/resolve', [ScanController::class, 'resolve'])->name('scan.resolve');
Route::get('qr-generator', [GeneratorController::class, 'index'])->name('qr-generator.index');
Route::get('qr-generator/download', [GeneratorController::class, 'download'])->name('qr-generator.download');
Route::get('qr-generator/codes', [GeneratorController::class, 'codes'])->name('qr-generator.codes');
```
(GET — generating UUIDs has no side effects, so the raw `fetch` needs no CSRF token.)

- [ ] **Step 6: Placeholder generator page** — create `resources/js/pages/qr-generator/index.tsx`:
```tsx
import AppLayout from '@/layouts/app-layout';

export default function QrGenerator() {
    return (
        <AppLayout title="QR Generator" breadcrumbs={[{ label: 'QR Generator' }]}>
            <h1 className="text-2xl font-semibold">QR Generator</h1>
        </AppLayout>
    );
}
```

- [ ] **Step 7: Tests**

```php
<?php // tests/Feature/App/ScanControllerTest.php
namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_existing_uuid_redirects_to_asset(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->actor())->get('/app/scan/resolve?code='.$asset->id)
            ->assertRedirect(route('app.assets.show', $asset->id));
    }

    public function test_unknown_uuid_redirects_to_create_with_force_id(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();

        $this->actingAs($this->actor())->get('/app/scan/resolve?code='.$uuid)
            ->assertRedirect(route('app.assets.create', ['forceId' => $uuid]));
    }

    public function test_invalid_code_flashes_error(): void
    {
        $this->actingAs($this->actor())->from('/app')->get('/app/scan/resolve?code=not-a-uuid')
            ->assertRedirect('/app')->assertSessionHas('error');
    }

    public function test_requires_auth(): void
    {
        $this->get('/app/scan/resolve?code=x')->assertRedirect();
    }
}
```

```php
<?php // tests/Feature/App/GeneratorControllerTest.php
namespace Tests\Feature\App;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneratorControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_download_returns_n_unique_uuids(): void
    {
        $res = $this->actingAs($this->actor())->get('/app/qr-generator/download?amount=5');
        $res->assertOk();
        $this->assertStringContainsString('text/plain', strtolower($res->headers->get('content-type') ?? ''));
        $lines = array_filter(explode("\n", $res->streamedContent() ?: $res->getContent()));
        $this->assertCount(5, $lines);
        $this->assertCount(5, array_unique($lines));
        foreach ($lines as $line) {
            $this->assertTrue(\Illuminate\Support\Str::isUuid(trim($line)));
        }
    }

    public function test_codes_returns_json_uuids(): void
    {
        $this->actingAs($this->actor())->getJson('/app/qr-generator/codes?amount=3')
            ->assertOk()
            ->assertJsonCount(3, 'uuids');
    }

    public function test_amount_clamped(): void
    {
        $this->actingAs($this->actor())->getJson('/app/qr-generator/codes?amount=99999')
            ->assertJsonCount(1000, 'uuids');
    }

    public function test_requires_auth(): void
    {
        $this->get('/app/qr-generator')->assertRedirect();
    }
}
```

```php
<?php // tests/Feature/App/AssetForcedIdTest.php
namespace Tests\Feature\App;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssetForcedIdTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'state' => AssetState::NEW->value,
            'asset_type_id' => AssetType::factory()->create()->id,
        ], $overrides);
    }

    public function test_store_uses_forced_uuid(): void
    {
        $uuid = (string) Str::uuid();

        $this->actingAs($this->actor())->post('/app/assets', $this->payload(['id' => $uuid]))
            ->assertRedirect();

        $this->assertDatabaseHas('assets', ['id' => $uuid]);
    }

    public function test_store_rejects_duplicate_id(): void
    {
        $existing = Asset::factory()->create();

        $this->actingAs($this->actor())->post('/app/assets', $this->payload(['id' => $existing->id]))
            ->assertSessionHasErrors('id');
    }

    public function test_store_without_id_autogenerates(): void
    {
        $this->actingAs($this->actor())->post('/app/assets', $this->payload())->assertRedirect();
        $this->assertSame(1, Asset::query()->count());
    }
}
```

- [ ] **Step 8: Run + commit** — `ddev exec ./vendor/bin/phpunit tests/Feature/App/ScanControllerTest.php tests/Feature/App/GeneratorControllerTest.php tests/Feature/App/AssetForcedIdTest.php` → PASS; Pint the new/changed PHP; full suite green.
```bash
git add app/Http/Requests/App/AssetRequest.php app/Http/Controllers/App/AssetController.php app/Http/Controllers/App/ScanController.php app/Http/Controllers/App/GeneratorController.php routes/web.php resources/js/pages/qr-generator/index.tsx tests/Feature/App/ScanControllerTest.php tests/Feature/App/GeneratorControllerTest.php tests/Feature/App/AssetForcedIdTest.php
git commit -m "feat(qr): asset forced-uuid + scan resolve + generator backend"
```

---

### Task 2: `QrPrintModal` React component

**Files:**
- Create: `resources/js/components/qr-print/qr-print-modal.tsx`
- Test: `resources/js/components/qr-print/__tests__/qr-print-modal.test.tsx`

**Interfaces:** Consumes the engine. Produces `QrPrintModal` (`{ open, items, onClose }`) — consumed by Tasks 3 and 5.

- [ ] **Step 1: Write the failing test** (mock the engine so no real canvas/WebUSB is needed)

```tsx
// resources/js/components/qr-print/__tests__/qr-print-modal.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

const pair = vi.fn().mockResolvedValue(undefined);
const print = vi.fn().mockResolvedValue(undefined);
const tryReconnect = vi.fn().mockResolvedValue(false);

vi.mock('@/plugins/qr-print/controller', () => ({
    PrintController: class {
        pair = pair; print = print; tryReconnect = tryReconnect; cancel = vi.fn(); close = vi.fn();
    },
}));
vi.mock('@/plugins/qr-print/layout', () => ({ composeLabel: vi.fn().mockResolvedValue({ width: 1, height: 1, data: new Uint8ClampedArray(4) }) }));
let webusb = true;
vi.mock('@/plugins/qr-print/webusb-transport', () => ({ isWebUsbAvailable: () => webusb }));

import { QrPrintModal } from '../qr-print-modal';

describe('QrPrintModal', () => {
    beforeEach(() => { webusb = true; pair.mockClear(); print.mockClear(); });

    const items = [{ uuid: 'u1', metadata: { modelName: 'X1', serial: 'SN1' } }];

    it('shows a WebUSB-unsupported notice when unavailable', () => {
        webusb = false;
        render(<QrPrintModal open items={items} onClose={() => {}} />);
        expect(screen.getByText(/WebUSB|nicht unterstützt/i)).toBeInTheDocument();
    });

    it('pairs then prints', async () => {
        render(<QrPrintModal open items={items} onClose={() => {}} />);
        fireEvent.click(screen.getByRole('button', { name: /verbinden|pair/i }));
        await vi.waitFor(() => expect(pair).toHaveBeenCalled());
        fireEvent.click(await screen.findByRole('button', { name: /^drucken|print/i }));
        await vi.waitFor(() => expect(print).toHaveBeenCalled());
    });

    it('disables the asset layout when an item lacks metadata', () => {
        render(<QrPrintModal open items={[{ uuid: 'u2' }]} onClose={() => {}} />);
        const assetOption = screen.getByLabelText(/Asset-Info/i) as HTMLInputElement;
        expect(assetOption).toBeDisabled();
    });
});
```

- [ ] **Step 2: Run to verify it fails**, then implement:

```tsx
// resources/js/components/qr-print/qr-print-modal.tsx
import { useEffect, useRef, useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { PrintController } from '@/plugins/qr-print/controller';
import { composeLabel } from '@/plugins/qr-print/layout';
import { isWebUsbAvailable } from '@/plugins/qr-print/webusb-transport';
import { DK_ROLLS, getRollById } from '@/plugins/qr-print/dk-rolls';
import type { LabelItem, LayoutKind } from '@/plugins/qr-print/types';

const STORAGE_ROLL = 'qrPrint.lastRollId';
const STORAGE_LAYOUT = 'qrPrint.lastLayout';

interface Props { open: boolean; items: LabelItem[]; onClose: () => void }

export function QrPrintModal({ open, items, onClose }: Props) {
    const controllerRef = useRef<PrintController>();
    if (!controllerRef.current) controllerRef.current = new PrintController();
    const controller = controllerRef.current;

    const webUsbSupported = isWebUsbAvailable();
    const canUseAsset = items.length > 0 && items.every((i) => !!i.metadata);

    const [rollId, setRollId] = useState<string>(() => localStorage.getItem(STORAGE_ROLL) ?? 'dk-11209');
    const [layout, setLayout] = useState<LayoutKind>(() => {
        const stored = (localStorage.getItem(STORAGE_LAYOUT) as LayoutKind | null) ?? 'qr-uuid';
        return stored === 'qr-asset' && !canUseAsset ? 'qr-uuid' : stored;
    });
    const [paired, setPaired] = useState(false);
    const [pairing, setPairing] = useState(false);
    const [printing, setPrinting] = useState(false);
    const [completed, setCompleted] = useState(0);
    const [error, setError] = useState<string | null>(null);
    const [status, setStatus] = useState<string | null>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);

    // reconnect on open
    useEffect(() => {
        if (!open || !webUsbSupported) return;
        controller.tryReconnect().then(setPaired).catch(() => {});
    }, [open, webUsbSupported, controller]);

    // live preview
    useEffect(() => {
        if (!open || !items[0]) return;
        let cancelled = false;
        (async () => {
            const image = await composeLabel(items[0], getRollById(rollId), layout);
            if (cancelled || !canvasRef.current) return;
            canvasRef.current.width = image.width;
            canvasRef.current.height = image.height;
            canvasRef.current.getContext('2d')?.putImageData(image, 0, 0);
        })();
        return () => { cancelled = true; };
    }, [open, items, rollId, layout]);

    const pair = async () => {
        setPairing(true); setError(null);
        try { await controller.pair(); setPaired(true); }
        catch (e) { const m = (e as Error).message ?? ''; if (!m.includes('No device selected')) setError(m); }
        finally { setPairing(false); }
    };

    const doPrint = async () => {
        if (!paired) { setError('Drucker nicht verbunden.'); return; }
        setPrinting(true); setError(null);
        localStorage.setItem(STORAGE_ROLL, rollId);
        localStorage.setItem(STORAGE_LAYOUT, layout);
        try {
            await controller.print({ items, roll: getRollById(rollId), layout }, (p) => setCompleted(p.completedLabels));
            setStatus(`${items.length} von ${items.length} Etiketten gedruckt.`);
        } catch (e) { setError((e as Error).message); }
        finally { setPrinting(false); }
    };

    const cancel = async () => { await controller.cancel(); setStatus(`Gestoppt nach ${completed} von ${items.length} Etiketten.`); setPrinting(false); };

    const layouts: { value: LayoutKind; label: string; disabled: boolean }[] = [
        { value: 'qr-only', label: 'Nur QR-Code', disabled: false },
        { value: 'qr-uuid', label: 'QR + UUID', disabled: false },
        { value: 'qr-asset', label: 'QR + Asset-Info', disabled: !canUseAsset },
    ];

    return (
        <Dialog open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
            <DialogContent>
                <DialogHeader><DialogTitle>QR drucken ({items.length})</DialogTitle></DialogHeader>

                {!webUsbSupported ? (
                    <p className="text-sm text-muted-foreground">
                        WebUSB wird von diesem Browser nicht unterstützt. Bitte Chrome oder Edge über HTTPS verwenden.
                    </p>
                ) : (
                    <div className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="grid gap-1 text-sm">Etikettenrolle
                                <select className="rounded-md border bg-background px-2 py-1" value={rollId} onChange={(e) => setRollId(e.target.value)}>
                                    {DK_ROLLS.map((r) => <option key={r.id} value={r.id}>{r.label}</option>)}
                                </select>
                            </label>
                            <fieldset className="grid gap-1 text-sm">
                                <legend>Layout</legend>
                                {layouts.map((l) => (
                                    <label key={l.value} className="flex items-center gap-2">
                                        <input type="radio" name="qr-layout" value={l.value} checked={layout === l.value} disabled={l.disabled}
                                            onChange={() => setLayout(l.value)} aria-label={l.label} />
                                        {l.label}
                                    </label>
                                ))}
                            </fieldset>
                        </div>

                        <canvas ref={canvasRef} className="max-h-[320px] max-w-full border object-contain" />

                        {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
                        {status && <p className="text-sm text-muted-foreground">{status}</p>}
                        {printing && <p className="text-sm text-muted-foreground">{completed} / {items.length}</p>}

                        <div className="flex gap-2">
                            {!paired
                                ? <Button type="button" onClick={pair} disabled={pairing}>{pairing ? 'Verbinde…' : 'Drucker verbinden'}</Button>
                                : <Button type="button" onClick={doPrint} disabled={printing}>Drucken</Button>}
                            {printing && <Button type="button" variant="outline" onClick={cancel}>Abbrechen</Button>}
                            <Button type="button" variant="ghost" onClick={onClose}>Schließen</Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 3: Run + build + commit** — vitest file green; `ddev exec pnpm run test` + `ddev exec pnpm run build` clean.
```bash
git add resources/js/components/qr-print/qr-print-modal.tsx resources/js/components/qr-print/__tests__/qr-print-modal.test.tsx
git commit -m "feat(qr): QrPrintModal React component"
```

---

### Task 3: DataTable row selection + asset list print (single + bulk)

**Files:**
- Modify: `resources/js/components/data-table/data-table.tsx`, `resources/js/pages/assets/index.tsx`
- Test: `resources/js/components/data-table/__tests__/data-table-selection.test.tsx`, additions to an asset-index test (or a new `resources/js/pages/assets/__tests__/index-print.test.tsx`)

**Interfaces:** Consumes `QrPrintModal`. Produces DataTable selection props.

- [ ] **Step 1: Add opt-in selection to `DataTable`** — extend `Props<T>` with:
```ts
    enableSelection?: boolean;
    getRowId?: (row: T) => string;
    renderBulkActions?: (selected: T[], clear: () => void) => React.ReactNode;
```
Import `getCheckboxColumn` inline via TanStack row-selection. In the component:
```tsx
import { type RowSelectionState } from '@tanstack/react-table';
import { Checkbox } from '@/components/ui/checkbox';
// ...
const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
const selectionColumn: ColumnDef<T> = {
    id: '__select__',
    header: ({ table }) => (
        <Checkbox
            checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate')}
            onCheckedChange={(v) => table.toggleAllPageRowsSelected(!!v)}
            aria-label="Select all"
        />
    ),
    cell: ({ row }) => (
        <Checkbox checked={row.getIsSelected()} onCheckedChange={(v) => row.toggleSelected(!!v)} aria-label="Select row" />
    ),
};
const effectiveColumns = enableSelection ? [selectionColumn, ...columns] : columns;
const table = useReactTable({
    data: rows,
    columns: effectiveColumns,
    getCoreRowModel: getCoreRowModel(),
    ...(enableSelection ? {
        enableRowSelection: true,
        state: { rowSelection },
        onRowSelectionChange: setRowSelection,
        getRowId: getRowId ? (row) => getRowId(row) : undefined,
    } : {}),
});
const selectedRows = enableSelection ? table.getSelectedRowModel().rows.map((r) => r.original) : [];
const clearSelection = () => setRowSelection({});
```
Render a bulk bar above the table when `enableSelection && renderBulkActions && selectedRows.length > 0`:
```tsx
{enableSelection && renderBulkActions && selectedRows.length > 0 && (
    <div className="flex items-center gap-3 rounded-md border bg-muted/50 px-3 py-2 text-sm">
        <span>{selectedRows.length} ausgewählt</span>
        {renderBulkActions(selectedRows, clearSelection)}
    </div>
)}
```
Replace the two `columns.length` / `columns` references used for `useReactTable` and the empty-state `colSpan` with `effectiveColumns.length`.

- [ ] **Step 2: Wire the asset list** — in `assets/index.tsx`:
  - Add a **Print QR** icon button to the row `actions` cell that opens the modal with a single item.
  - Convert the page to a component with modal state: `const [printItems, setPrintItems] = useState<LabelItem[] | null>(null);` and render `<QrPrintModal open={!!printItems} items={printItems ?? []} onClose={() => setPrintItems(null)} />`.
  - Build items from a row: `const itemFor = (r: Row): LabelItem => ({ uuid: r.id, ...(r.model_name && r.serial_number ? { metadata: { modelName: r.model_name, serial: r.serial_number } } : {}) });`.
  - Pass to `DataTable`: `enableSelection`, `getRowId={(r) => r.id}`, and `renderBulkActions={(rows, clear) => <Button size="sm" onClick={() => { setPrintItems(rows.map(itemFor)); clear(); }}>QR drucken ({rows.length})</Button>}`.
  - Because `columns` references the modal opener, move the `columns` definition inside the component (or pass a callback). Use a `QrCode` icon from lucide for the row action.

Concretely, the row action addition:
```tsx
<Button variant="ghost" size="icon" onClick={() => setPrintItems([itemFor(row.original)])} aria-label="Print QR"><QrCode className="h-4 w-4" /></Button>
```
(Import `QrCode` from `lucide-react`, `useState` from `react`, `QrPrintModal` from `@/components/qr-print/qr-print-modal`, and `LabelItem` type.)

- [ ] **Step 3: Tests**

```tsx
// resources/js/components/data-table/__tests__/data-table-selection.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { type ColumnDef } from '@tanstack/react-table';
import { DataTable } from '../data-table';

interface R { id: string; name: string }
const columns: ColumnDef<R>[] = [{ id: 'name', accessorFn: (r) => r.name, header: 'Name', cell: ({ row }) => row.original.name }];
const rows: R[] = [{ id: 'a', name: 'Alpha' }, { id: 'b', name: 'Bravo' }];
const pagination = { current_page: 1, last_page: 1, per_page: 25, total: 2 };

describe('DataTable selection', () => {
    it('selecting rows surfaces them to renderBulkActions', () => {
        const onBulk = vi.fn();
        render(<DataTable columns={columns} rows={rows} pagination={pagination as never} baseUrl="/x"
            searchable={false} enableSelection getRowId={(r) => r.id}
            renderBulkActions={(selected) => <button onClick={() => onBulk(selected)}>Bulk {selected.length}</button>} />);
        const checkboxes = screen.getAllByLabelText(/select row/i);
        fireEvent.click(checkboxes[0]);
        fireEvent.click(screen.getByText(/^Bulk/));
        expect(onBulk).toHaveBeenCalledWith([{ id: 'a', name: 'Alpha' }]);
    });
});
```

(Add a light asset-index test only if the existing suite doesn't already render the page; otherwise the selection test + Task 2's modal test cover the mechanism.)

- [ ] **Step 4: Run + build + commit** — vitest + full JS suite + build green.
```bash
git add resources/js/components/data-table/data-table.tsx resources/js/pages/assets/index.tsx resources/js/components/data-table/__tests__/data-table-selection.test.tsx
git commit -m "feat(qr): DataTable row selection + asset list print (single/bulk)"
```

---

### Task 4: Scan dialog in the topbar

**Files:**
- Create: `resources/js/components/scan/scan-dialog.tsx`
- Modify: `resources/js/components/app-topbar.tsx`
- Test: `resources/js/components/scan/__tests__/scan-dialog.test.tsx`

- [ ] **Step 1: `ScanDialog`**

```tsx
// resources/js/components/scan/scan-dialog.tsx
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import QrScanner from 'qr-scanner';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { classifyCameraError } from '@/plugins/scanner-camera-error';

interface Props { open: boolean; onClose: () => void }

const MESSAGES: Record<string, string> = {
    permission: 'Kamerazugriff wurde verweigert.',
    not_found: 'Keine Kamera gefunden.',
    generic: 'Kamera konnte nicht gestartet werden.',
};

export function ScanDialog({ open, onClose }: Props) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const scannerRef = useRef<QrScanner | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [manual, setManual] = useState('');

    const go = (code: string) => {
        if (!code) return;
        onClose();
        router.get('/app/scan/resolve', { code });
    };

    useEffect(() => {
        if (!open || !videoRef.current) return;
        const scanner = new QrScanner(videoRef.current, (result) => go(typeof result === 'string' ? result : result.data), {
            highlightScanRegion: true,
        });
        scannerRef.current = scanner;
        scanner.start().catch((e) => setError(MESSAGES[classifyCameraError(e)]));
        return () => { scanner.stop(); scanner.destroy(); scannerRef.current = null; };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <Dialog open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
            <DialogContent>
                <DialogHeader><DialogTitle>QR scannen</DialogTitle></DialogHeader>
                <video ref={videoRef} className="aspect-square w-full rounded-md bg-black object-cover" />
                {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
                <form onSubmit={(e) => { e.preventDefault(); go(manual.trim()); }} className="flex gap-2">
                    <Input value={manual} onChange={(e) => setManual(e.target.value)} placeholder="UUID manuell eingeben" />
                    <Button type="submit" variant="secondary">Öffnen</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 2: Topbar button** — in `app-topbar.tsx`, import `useState`, `ScanDialog`, `Button`, and a `QrCode`/`ScanLine` lucide icon; add a scan button and the dialog:
```tsx
const [scanOpen, setScanOpen] = useState(false);
// in the header, before <ThemeToggle />:
<Button variant="ghost" size="icon" onClick={() => setScanOpen(true)} aria-label="Scan QR"><ScanLine className="h-5 w-5" /></Button>
<ScanDialog open={scanOpen} onClose={() => setScanOpen(false)} />
```
(Convert `AppTopbar` to include state; it's currently a stateless function — add the `useState` and keep the same markup otherwise.)

- [ ] **Step 3: Test** (mock `qr-scanner` + inertia router)

```tsx
// resources/js/components/scan/__tests__/scan-dialog.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const get = vi.fn();
vi.mock('@inertiajs/react', () => ({ router: { get: (...a: unknown[]) => get(...a) } }));
const start = vi.fn().mockResolvedValue(undefined);
vi.mock('qr-scanner', () => ({ default: class { start = start; stop = vi.fn(); destroy = vi.fn(); constructor() {} } }));

import { ScanDialog } from '../scan-dialog';

describe('ScanDialog', () => {
    it('manual submit navigates to resolve', () => {
        get.mockClear();
        render(<ScanDialog open onClose={() => {}} />);
        fireEvent.change(screen.getByPlaceholderText(/UUID/i), { target: { value: 'abc-123' } });
        fireEvent.click(screen.getByRole('button', { name: /öffnen|open/i }));
        expect(get).toHaveBeenCalledWith('/app/scan/resolve', { code: 'abc-123' });
    });
});
```

- [ ] **Step 4: Run + build + commit** — vitest + full JS suite + build green.
```bash
git add resources/js/components/scan resources/js/components/app-topbar.tsx
git commit -m "feat(qr): topbar scan dialog"
```

---

### Task 5: QR Generator page + nav + AssetForm forced id

**Files:**
- Modify: `resources/js/pages/qr-generator/index.tsx`, `resources/js/config/nav.ts`, `resources/js/pages/assets/asset-form.tsx`, `resources/js/pages/assets/create.tsx`
- Test: `resources/js/pages/qr-generator/__tests__/index.test.tsx`

- [ ] **Step 1: Generator page**

```tsx
// resources/js/pages/qr-generator/index.tsx
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { NumberField } from '@/components/form/number-field';
import { QrPrintModal } from '@/components/qr-print/qr-print-modal';
import type { LabelItem } from '@/plugins/qr-print/types';

export default function QrGenerator() {
    const [amount, setAmount] = useState('20');
    const [items, setItems] = useState<LabelItem[] | null>(null);
    const [loading, setLoading] = useState(false);

    const n = Math.max(1, Math.min(1000, parseInt(amount || '0', 10) || 0));

    const printLabels = async () => {
        setLoading(true);
        try {
            const res = await fetch(`/app/qr-generator/codes?amount=${n}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            const data = (await res.json()) as { uuids: string[] };
            setItems(data.uuids.map((u) => ({ uuid: u })));
        } finally { setLoading(false); }
    };

    return (
        <AppLayout title="QR Generator" breadcrumbs={[{ label: 'QR Generator' }]}>
            <h1 className="mb-6 text-2xl font-semibold">QR Generator</h1>
            <div className="max-w-md space-y-4">
                <NumberField id="amount" label="Anzahl" step="1" min="1" value={amount} onChange={setAmount} />
                <div className="flex gap-2">
                    <Button asChild variant="outline"><a href={`/app/qr-generator/download?amount=${n}`}>TXT herunterladen</a></Button>
                    <Button type="button" onClick={printLabels} disabled={loading}>Etiketten drucken</Button>
                </div>
            </div>
            <QrPrintModal open={!!items} items={items ?? []} onClose={() => setItems(null)} />
        </AppLayout>
    );
}
```

(The `codes` endpoint is GET with no side effects, so the raw `fetch` needs no CSRF token — just the `X-Requested-With`/`Accept` headers so Laravel returns JSON.)

- [ ] **Step 2: Nav** — in `nav.ts`, import a `QrCode` lucide icon and append a **Tools** group:
```ts
{ label: 'Tools', items: [{ label: 'QR Generator', href: '/app/qr-generator', icon: QrCode, match: (p) => p.startsWith('/app/qr-generator') }] },
```

- [ ] **Step 3: AssetForm forced id** — in `asset-form.tsx`, add `forceId?: string | null` to `Props`; add `id: forceId ?? ''` as the first field in the `useForm({...})` object; when `forceId` is set, render a read-only field at the top of the form:
```tsx
{forceId && (
    <div className="rounded-md border bg-muted/50 px-3 py-2 text-sm">
        <span className="text-muted-foreground">Asset-ID: </span><span className="font-mono">{forceId}</span>
    </div>
)}
```
In `create.tsx`, accept `forceId` from props and pass it through:
```tsx
export default function CreateAsset({ forceId, ...props }: AssetOptions & { forceId?: string | null }) {
    return (
        <AppLayout title="New asset" breadcrumbs={[{ label: 'Assets', href: '/app/assets' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New asset</h1>
            <AssetForm options={props} submitUrl="/app/assets" method="post" forceId={forceId} />
        </AppLayout>
    );
}
```
(Edit page does NOT pass `forceId`, so `id` submits as `''` → server nulls/ignores it — `update()` never reads `id`.)

- [ ] **Step 4: Test**

```tsx
// resources/js/pages/qr-generator/__tests__/index.test.tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@/components/qr-print/qr-print-modal', () => ({ QrPrintModal: () => null }));

import QrGenerator from '../index';

describe('QrGenerator', () => {
    it('download link reflects the amount', () => {
        render(<QrGenerator />);
        const link = screen.getByRole('link', { name: /TXT/i });
        expect(link).toHaveAttribute('href', '/app/qr-generator/download?amount=20');
    });
});
```

- [ ] **Step 5: Run + build + commit** — vitest + full JS suite + build green.
```bash
git add resources/js/pages/qr-generator resources/js/config/nav.ts resources/js/pages/assets/asset-form.tsx resources/js/pages/assets/create.tsx
git commit -m "feat(qr): generator page + nav + asset forced-id form field"
```

---

### Task 6: Final verification

- [ ] **Step 1:** `ddev exec ./vendor/bin/phpunit` → green.
- [ ] **Step 2:** `ddev exec pnpm run test` → green.
- [ ] **Step 3:** `ddev exec pnpm run build` → succeeds.
- [ ] **Step 4:** `ddev exec ./vendor/bin/pint --test app/Http/Controllers/App/ScanController.php app/Http/Controllers/App/GeneratorController.php app/Http/Controllers/App/AssetController.php app/Http/Requests/App/AssetRequest.php` → clean.
- [ ] **Step 5: Manual verify** (Chromium over the ddev HTTPS URL) — asset row Print QR + bulk Print QR open the modal, roll/layout/preview work, pairing prompts a Brother device; the topbar scan button opens the camera dialog and manual UUID entry routes to an existing asset or to create with the id pre-filled; the QR Generator downloads a TXT of UUIDs and prints labels; `/app-old` QR pages still work.
- [ ] **Step 6:** Commit anything outstanding.

---

## Self-Review Notes

- **Spec coverage:** forced-uuid create + scan resolve + generator backend → Task 1; QrPrintModal → Task 2; DataTable selection + asset single/bulk print → Task 3; topbar scan dialog → Task 4; generator page + nav + AssetForm forced-id → Task 5; tests throughout + Task 6.
- **Reuse:** the entire `qr-print` engine + `classifyCameraError` are imported, never modified; Filament QR glue untouched.
- **Forced-id safety:** `AssetRequest` nulls `''` id and validates `unique`; `store` sets `id` explicitly (HasUuids only autogenerates when empty); `update` never reads `id`, so edits can't trip the unique rule.
- **Type/name consistency:** `LabelItem` items built as `{ uuid, metadata? }`; modal props `{ open, items, onClose }` match Tasks 3/5 usage; DataTable new props are optional (other tables unaffected); scan navigates to `app.scan.resolve`.
- **Browser reality:** WebUSB modal shows a notice when unsupported; scan uses `classifyCameraError`; raw `fetch` in the generator sends CSRF + JSON headers.
- **Deferred:** non-WebUSB print fallback; cross-page multi-select; Filament QR removal happens at cutover (Spec 9).

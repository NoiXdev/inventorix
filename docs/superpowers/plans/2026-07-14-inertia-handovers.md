# Inertia Migration — Handovers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate Handovers to `/app` — a 4-step create wizard (with a React signature pad) that reuses `HandoverService::commit`, plus read-only list + detail pages with PDF download.

**Architecture:** The entire domain backend is reused verbatim (`HandoverService::commit`, queued `GenerateHandoverPdf`, `HandoverSigned` mail, `pdf.handover` blade, `HandoverData` DTO, enums, pivot, signed `handover.pdf` route). New: a thin `HandoverController` (index/create/store/show), a canvas `SignaturePad`, a stepped wizard, and list/detail pages. Handovers are immutable — no edit/delete.

**Tech Stack:** Laravel 13/PHP 8.4, Inertia v2 + React 19, shadcn/ui, PHPUnit (`Queue::fake`), Vitest. ddev; pnpm.

## Global Constraints

- ddev for all commands; **PHP tests are PHPUnit**; JS tests are **Vitest**.
- Do NOT touch Filament (`/app-old`), `HandoverService`, `GenerateHandoverPdf`, `HandoverSigned`, `pdf.handover`, `HandoverData`, the enums, the pivot, or the `handover.pdf` route. No DB schema changes.
- New: `app/Http/Controllers/App/HandoverController.php`, `app/Http/Requests/App/HandoverRequest.php`, `resources/js/components/handover/signature-pad.tsx`, `resources/js/pages/handovers/{index,show,create}.tsx` (+ wizard). Modify: `routes/web.php`, `resources/js/config/nav.ts`. `@/` → `resources/js/*`.
- `HandoverData` ctor order: `type` (HandoverType), `recipientKind` (RecipientKind), `recipientUserId ?string`, `recipientName string`, `recipientEmail ?string`, `assetIds array`, `accessories ?string`, `conditionNotes ?string`, `termsText string`, `signaturePngBase64 string`, `signatureIp ?string`, `signatureUserAgent ?string`, `createdById string`.
- `HandoverService::commit` throws `App\Exceptions\HandoverStateConflictException` (bad asset states) and `\InvalidArgumentException` (bad signature base64/PNG/size). Catch both in `store`.
- `HandoverType`: issue/lend/return/return_defect; `allowedStateFrom()` (issue/lend→NEW,STORAGE; return/return_defect→IN_USE,LEND); `assignsRecipientAsOwner()` (issue/lend). `RecipientKind`: internal/external. Labels via `getLabel()`.
- `signature_png` posted is raw base64 (no `data:` prefix — the SignaturePad strips it); the service validates it.
- PDF/email stay queued. `pdf_url` = `URL::signedRoute('handover.pdf', ['handover' => $h])`, only when `pdf_path` set.
- No policy (gate on `auth`). Use `SelectField` for type/recipient_kind (no shadcn radio-group); shadcn `Checkbox` exists for the asset multi-select.
- Valid tiny PNG base64 for tests: `iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==`.
- TDD for backend; commit per task.

---

### Task 1: `SignaturePad` React component

**Files:**
- Create: `resources/js/components/handover/signature-pad.tsx`
- Test: `resources/js/components/handover/__tests__/signature-pad.test.tsx`

**Interfaces:**
- Produces: `SignaturePad` (`{ value: string; onChange: (base64: string) => void; width?: number; height?: number }`) — a `<canvas>` (default 600×200, white fill) with pointer/touch drawing + a Clear button; emits `toDataURL('image/png')` with the `data:image/png;base64,` prefix stripped on stroke-end; Clear emits `''`.

- [ ] **Step 1: Write the failing test**

```tsx
// resources/js/components/handover/__tests__/signature-pad.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi, beforeAll } from 'vitest';
import { SignaturePad } from '../signature-pad';

beforeAll(() => {
    // happy-dom canvas: stub 2d context + toDataURL
    HTMLCanvasElement.prototype.getContext = vi.fn(() => ({
        fillStyle: '', lineWidth: 0, lineCap: '', strokeStyle: '',
        fillRect: vi.fn(), beginPath: vi.fn(), moveTo: vi.fn(), lineTo: vi.fn(), stroke: vi.fn(), closePath: vi.fn(),
    })) as unknown as typeof HTMLCanvasElement.prototype.getContext;
    HTMLCanvasElement.prototype.toDataURL = vi.fn(() => 'data:image/png;base64,SIGDATA');
});

describe('SignaturePad', () => {
    it('emits stripped base64 on a stroke and empties on clear', () => {
        const onChange = vi.fn();
        render(<SignaturePad value="" onChange={onChange} />);
        const canvas = screen.getByTestId('signature-canvas');

        fireEvent.pointerDown(canvas, { clientX: 5, clientY: 5 });
        fireEvent.pointerMove(canvas, { clientX: 20, clientY: 20 });
        fireEvent.pointerUp(canvas);
        expect(onChange).toHaveBeenLastCalledWith('SIGDATA'); // prefix stripped

        fireEvent.click(screen.getByRole('button', { name: /clear/i }));
        expect(onChange).toHaveBeenLastCalledWith('');
    });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev exec pnpm exec vitest run resources/js/components/handover/__tests__/signature-pad.test.tsx`
Expected: FAIL — module missing.

- [ ] **Step 3: Implement `SignaturePad`**

```tsx
// resources/js/components/handover/signature-pad.tsx
import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';

interface Props { value: string; onChange: (base64: string) => void; width?: number; height?: number; }

export function SignaturePad({ value, onChange, width = 600, height = 200 }: Props) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const drawing = useRef(false);
    const last = useRef<{ x: number; y: number } | null>(null);

    const ctx = () => canvasRef.current?.getContext('2d') ?? null;

    const fillWhite = () => {
        const c = ctx();
        if (!c || !canvasRef.current) return;
        c.fillStyle = '#ffffff';
        c.fillRect(0, 0, canvasRef.current.width, canvasRef.current.height);
    };

    useEffect(() => {
        const c = ctx();
        if (c) { c.lineWidth = 2; c.lineCap = 'round'; c.strokeStyle = '#111111'; }
        fillWhite();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const pos = (e: React.PointerEvent) => {
        const r = canvasRef.current!.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    };

    const start = (e: React.PointerEvent) => { drawing.current = true; last.current = pos(e); };
    const move = (e: React.PointerEvent) => {
        if (!drawing.current) return;
        const c = ctx(); if (!c) return;
        const p = pos(e);
        c.beginPath(); c.moveTo(last.current!.x, last.current!.y); c.lineTo(p.x, p.y); c.stroke(); c.closePath();
        last.current = p;
    };
    const end = () => {
        if (!drawing.current) return;
        drawing.current = false;
        const url = canvasRef.current?.toDataURL('image/png') ?? '';
        onChange(url.replace(/^data:image\/png;base64,/, ''));
    };

    const clear = () => { fillWhite(); onChange(''); };

    return (
        <div className="space-y-2">
            <canvas
                ref={canvasRef} data-testid="signature-canvas" width={width} height={height}
                className="touch-none rounded-md border bg-white"
                onPointerDown={start} onPointerMove={move} onPointerUp={end} onPointerLeave={end}
            />
            <div>
                <Button type="button" variant="outline" size="sm" onClick={clear}>Clear</Button>
            </div>
        </div>
    );
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `ddev exec pnpm exec vitest run resources/js/components/handover/__tests__/signature-pad.test.tsx`
Expected: PASS.

- [ ] **Step 5: Build + commit**

Run: `ddev exec pnpm run build` → succeeds.

```bash
git add resources/js/components/handover
git commit -m "feat(handovers): add SignaturePad canvas component"
```

---

### Task 2: Handover backend (controller, request, routes, nav)

**Files:**
- Create: `app/Http/Controllers/App/HandoverController.php`, `app/Http/Requests/App/HandoverRequest.php`
- Modify: `routes/web.php`, `resources/js/config/nav.ts`
- Test: `tests/Feature/App/HandoverControllerTest.php`

**Interfaces:**
- Consumes: `HandoverService::commit`, `HandoverData`, `HandoverType`, `RecipientKind`, `Handover`, `Asset`, `User`.
- Produces: routes `app.handovers.{index,create,store,show}`; index props `handovers: {data,meta}` (rows `{id,type,type_label,recipient_name,recipient_kind_label,assets_count,created_by_name,signed_at,pdf_ready,pdf_url}`); create props `typeOptions/recipientKindOptions/userOptions/assetOptions/defaultTerms`; show props `handover` + `assets` (pivot transitions).

- [ ] **Step 1: Write the failing tests**

```php
<?php // tests/Feature/App/HandoverControllerTest.php
namespace Tests\Feature\App;

use App\Enums\AssetState;
use App\Jobs\GenerateHandoverPdf;
use App\Models\Asset;
use App\Models\Handover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HandoverControllerTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function actor(): User { return User::factory()->create(['login_enabled' => true]); }

    public function test_store_issue_creates_handover_transitions_assets_and_dispatches_pdf(): void
    {
        Queue::fake();
        $actor = $this->actor();
        $recipient = User::factory()->create();
        $asset = Asset::factory()->create(['state' => AssetState::NEW->value, 'owner_id' => null]);

        $this->actingAs($actor)->post('/app/handovers', [
            'type' => 'issue', 'recipient_kind' => 'internal', 'recipient_user_id' => $recipient->id,
            'recipient_name' => $recipient->name, 'recipient_email' => 'r@example.test',
            'asset_ids' => [$asset->id], 'terms_text' => 'Terms apply.', 'signature_png' => self::PNG,
        ])->assertRedirect();

        $handover = Handover::first();
        $this->assertNotNull($handover);
        $this->assertSame($actor->id, $handover->created_by);
        $this->assertNotNull($handover->signed_at);
        $asset->refresh();
        $this->assertSame(AssetState::IN_USE, $asset->state);     // issue -> in-use
        $this->assertSame($recipient->id, $asset->owner_id);      // issue assigns recipient as owner
        $this->assertTrue($handover->assets()->where('assets.id', $asset->id)->exists());
        $pivot = $handover->assets()->first()->pivot;
        $this->assertSame('new', $pivot->state_from);
        $this->assertSame('in-use', $pivot->state_to);
        Queue::assertPushed(GenerateHandoverPdf::class);
    }

    public function test_store_return_moves_asset_to_storage(): void
    {
        Queue::fake();
        $asset = Asset::factory()->create(['state' => AssetState::IN_USE->value]);

        $this->actingAs($this->actor())->post('/app/handovers', [
            'type' => 'return', 'recipient_kind' => 'external',
            'recipient_name' => 'Ext Person', 'recipient_email' => null,
            'asset_ids' => [$asset->id], 'terms_text' => 'T', 'signature_png' => self::PNG,
        ])->assertRedirect();

        $this->assertSame(AssetState::STORAGE, $asset->fresh()->state);
    }

    public function test_store_rejects_asset_not_in_allowed_state(): void
    {
        $asset = Asset::factory()->create(['state' => AssetState::DEFECT->value]); // not allowed for issue

        $this->actingAs($this->actor())->post('/app/handovers', [
            'type' => 'issue', 'recipient_kind' => 'external', 'recipient_name' => 'X',
            'asset_ids' => [$asset->id], 'terms_text' => 'T', 'signature_png' => self::PNG,
        ])->assertSessionHasErrors('asset_ids');

        $this->assertSame(0, Handover::count());
    }

    public function test_store_requires_recipient_user_when_internal(): void
    {
        $asset = Asset::factory()->create(['state' => AssetState::NEW->value]);
        $this->actingAs($this->actor())->post('/app/handovers', [
            'type' => 'issue', 'recipient_kind' => 'internal', 'recipient_name' => 'X',
            'asset_ids' => [$asset->id], 'terms_text' => 'T', 'signature_png' => self::PNG,
        ])->assertSessionHasErrors('recipient_user_id');
    }

    public function test_store_requires_signature(): void
    {
        $asset = Asset::factory()->create(['state' => AssetState::NEW->value]);
        $this->actingAs($this->actor())->post('/app/handovers', [
            'type' => 'issue', 'recipient_kind' => 'external', 'recipient_name' => 'X',
            'asset_ids' => [$asset->id], 'terms_text' => 'T', 'signature_png' => '',
        ])->assertSessionHasErrors('signature_png');
    }

    public function test_index_lists_handovers_with_counts(): void
    {
        $h = Handover::factory()->create();
        $h->assets()->attach(Asset::factory()->create()->id, [
            'id' => (string) \Illuminate\Support\Str::uuid(), 'state_from' => 'new', 'state_to' => 'in-use',
            'owner_from_id' => null, 'owner_to_id' => null,
        ]);

        $this->actingAs($this->actor())->get('/app/handovers')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('handovers/index', false)
                ->has('handovers.data', 1, fn (Assert $r) => $r
                    ->has('id')->has('type_label')->has('recipient_name')->has('assets_count')
                    ->where('pdf_ready', false)->etc()));
    }

    public function test_show_returns_detail(): void
    {
        $h = Handover::factory()->create();

        $this->actingAs($this->actor())->get("/app/handovers/{$h->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('handovers/show', false)
                ->where('handover.id', $h->id)->has('assets'));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/handovers')->assertRedirect();
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/HandoverControllerTest.php`
Expected: FAIL — routes/controller undefined.

- [ ] **Step 3: Create the FormRequest**

```php
<?php // app/Http/Requests/App/HandoverRequest.php
namespace App\Http\Requests\App;

use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Models\Asset;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HandoverRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(HandoverType::class)],
            'recipient_kind' => ['required', Rule::enum(RecipientKind::class)],
            'recipient_user_id' => ['nullable', 'required_if:recipient_kind,internal', 'uuid', 'exists:users,id'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['uuid', 'exists:assets,id'],
            'accessories' => ['nullable', 'string'],
            'condition_notes' => ['nullable', 'string'],
            'terms_text' => ['required', 'string'],
            'signature_png' => ['required', 'string'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = HandoverType::tryFrom((string) $this->input('type'));
            $ids = (array) $this->input('asset_ids', []);
            if ($type === null || $ids === []) {
                return; // base rules already flagged these
            }
            $allowed = array_map(fn ($s) => $s->value, $type->allowedStateFrom());
            $bad = Asset::query()->whereIn('id', $ids)->get()
                ->filter(fn (Asset $a) => ! in_array($a->state->value, $allowed, true));
            if ($bad->isNotEmpty()) {
                $validator->errors()->add('asset_ids', 'One or more selected assets are not in an allowed state for this handover type.');
            }
        }];
    }
}
```

- [ ] **Step 4: Create the controller**

```php
<?php // app/Http/Controllers/App/HandoverController.php
namespace App\Http\Controllers\App;

use App\DataObjects\HandoverData;
use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Exceptions\HandoverStateConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\HandoverRequest;
use App\Models\Asset;
use App\Models\Handover;
use App\Models\User;
use App\Services\HandoverService;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class HandoverController extends Controller
{
    public function index(Request $request): Response
    {
        $handovers = TableQuery::for(
            Handover::query()->withCount('assets')->with('createdBy')->latest('signed_at'),
            $request,
        )->searchable(['recipient_name'])->sortable(['signed_at', 'type', 'recipient_name', 'assets_count'])->paginate();

        $handovers->getCollection()->transform(fn (Handover $h) => [
            'id' => $h->id,
            'type' => $h->type->value,
            'type_label' => $h->type->getLabel(),
            'recipient_name' => $h->recipient_name,
            'recipient_kind_label' => $h->recipient_kind->getLabel(),
            'assets_count' => $h->assets_count,
            'created_by_name' => optional($h->createdBy)->name,
            'signed_at' => optional($h->signed_at)->toDateTimeString(),
            'pdf_ready' => (bool) $h->pdf_path,
            'pdf_url' => $h->pdf_path ? URL::signedRoute('handover.pdf', ['handover' => $h->id]) : null,
        ]);

        return Inertia::render('handovers/index', [
            'handovers' => [
                'data' => $handovers->items(),
                'meta' => [
                    'current_page' => $handovers->currentPage(), 'last_page' => $handovers->lastPage(),
                    'per_page' => $handovers->perPage(), 'total' => $handovers->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('handovers/create', [
            'typeOptions' => array_map(fn (HandoverType $t) => [
                'value' => $t->value, 'label' => $t->getLabel(),
                'allowedStates' => array_map(fn ($s) => $s->value, $t->allowedStateFrom()),
                'assignsOwner' => $t->assignsRecipientAsOwner(),
            ], HandoverType::cases()),
            'recipientKindOptions' => array_map(fn (RecipientKind $k) => ['value' => $k->value, 'label' => $k->getLabel()], RecipientKind::cases()),
            'userOptions' => User::query()->orderBy('name')->get()
                ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name, 'email' => $u->email])->all(),
            'assetOptions' => Asset::query()->with('assetType', 'model.manufacturer')->get()
                ->map(fn (Asset $a) => [
                    'value' => $a->id,
                    'label' => '('.optional(optional($a->model)->manufacturer)->name.') '.optional($a->model)->name.' — '.($a->serial_number ?? '—'),
                    'state' => $a->state->value,
                ])->all(),
            'defaultTerms' => (string) config('handover.terms'),
        ]);
    }

    public function store(HandoverRequest $request, HandoverService $service): RedirectResponse
    {
        $v = $request->validated();
        $data = new HandoverData(
            type: HandoverType::from($v['type']),
            recipientKind: RecipientKind::from($v['recipient_kind']),
            recipientUserId: $v['recipient_kind'] === RecipientKind::INTERNAL->value ? ($v['recipient_user_id'] ?? null) : null,
            recipientName: $v['recipient_name'],
            recipientEmail: $v['recipient_email'] ?? null,
            assetIds: $v['asset_ids'],
            accessories: $v['accessories'] ?? null,
            conditionNotes: $v['condition_notes'] ?? null,
            termsText: $v['terms_text'],
            signaturePngBase64: $v['signature_png'],
            signatureIp: $request->ip(),
            signatureUserAgent: (string) $request->userAgent(),
            createdById: (string) $request->user()->id,
        );

        try {
            $handover = $service->commit($data);
        } catch (HandoverStateConflictException) {
            return back()->withErrors(['asset_ids' => 'One or more selected assets are no longer in an allowed state.']);
        } catch (\InvalidArgumentException) {
            return back()->withErrors(['signature_png' => 'The signature could not be read. Please sign again.']);
        }

        return to_route('app.handovers.show', $handover->id)->with('success', 'Handover created.');
    }

    public function show(Handover $handover): Response
    {
        $handover->load('recipientUser', 'createdBy', 'assets.model.manufacturer', 'assets.assetType');

        return Inertia::render('handovers/show', [
            'handover' => [
                'id' => $handover->id,
                'type_label' => $handover->type->getLabel(),
                'recipient_name' => $handover->recipient_name,
                'recipient_email' => $handover->recipient_email,
                'recipient_kind_label' => $handover->recipient_kind->getLabel(),
                'accessories' => $handover->accessories,
                'condition_notes' => $handover->condition_notes,
                'terms_text' => $handover->terms_text,
                'created_by_name' => optional($handover->createdBy)->name,
                'signed_at' => optional($handover->signed_at)->toDateTimeString(),
                'pdf_ready' => (bool) $handover->pdf_path,
                'pdf_url' => $handover->pdf_path ? URL::signedRoute('handover.pdf', ['handover' => $handover->id]) : null,
            ],
            'assets' => $handover->assets->map(fn (Asset $a) => [
                'id' => $a->id,
                'label' => '('.optional(optional($a->model)->manufacturer)->name.') '.optional($a->model)->name,
                'state_from' => $a->pivot->state_from,
                'state_to' => $a->pivot->state_to,
            ])->all(),
        ]);
    }
}
```

- [ ] **Step 5: Register the routes + nav**

In `routes/web.php`, inside the authenticated `/app` group:

```php
use App\Http\Controllers\App\HandoverController;
Route::get('handovers', [HandoverController::class, 'index'])->name('handovers.index');
Route::get('handovers/create', [HandoverController::class, 'create'])->name('handovers.create');
Route::post('handovers', [HandoverController::class, 'store'])->name('handovers.store');
Route::get('handovers/{handover}', [HandoverController::class, 'show'])->name('handovers.show');
```

(Declare `handovers/create` before `handovers/{handover}` so it isn't captured by the wildcard.)

In `resources/js/config/nav.ts`, import `FileSignature` from `lucide-react` and add a new group after "Administration":

```ts
{
    label: 'Operations',
    items: [
        { label: 'Handovers', href: '/app/handovers', icon: FileSignature, match: (p) => p.startsWith('/app/handovers') },
    ],
},
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/HandoverControllerTest.php`
Expected: PASS. Then full suite `ddev exec ./vendor/bin/phpunit` → green.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/App/HandoverController.php app/Http/Requests/App/HandoverRequest.php routes/web.php resources/js/config/nav.ts tests/Feature/App/HandoverControllerTest.php
git commit -m "feat(handovers): Inertia controller (wizard options, store via HandoverService) + list/detail props"
```

---

### Task 3: Handovers list + detail pages

**Files:**
- Create: `resources/js/pages/handovers/index.tsx`, `resources/js/pages/handovers/show.tsx`
- Modify: `tests/Feature/App/HandoverControllerTest.php` (drop the `false` on the index + show `component` assertions)

**Interfaces:**
- Consumes: `DataTable`, `AppLayout`, `Badge`, `Button`, `Card`; props from Task 2.
- Produces: the two route pages.

- [ ] **Step 1: Create the index page**

```tsx
// resources/js/pages/handovers/index.tsx
import { Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Eye, FileDown } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row {
    id: string; type: string; type_label: string | null; recipient_name: string;
    recipient_kind_label: string | null; assets_count: number; created_by_name: string | null;
    signed_at: string | null; pdf_ready: boolean; pdf_url: string | null;
}

const columns: ColumnDef<Row>[] = [
    { id: 'signed_at', accessorFn: (r) => r.signed_at, header: 'Signed at', cell: ({ row }) => row.original.signed_at ?? '—' },
    { id: 'type', accessorFn: (r) => r.type, header: 'Type', cell: ({ row }) => <Badge variant="secondary">{row.original.type_label ?? row.original.type}</Badge> },
    { id: 'recipient_name', accessorFn: (r) => r.recipient_name, header: 'Recipient',
      cell: ({ row }) => <div><div>{row.original.recipient_name}</div><div className="text-xs text-muted-foreground">{row.original.recipient_kind_label}</div></div> },
    { id: 'assets_count', accessorFn: (r) => r.assets_count, header: 'Assets', cell: ({ row }) => <Badge variant="secondary">{row.original.assets_count}</Badge> },
    { accessorKey: 'created_by_name', header: 'Created by', cell: ({ row }) => row.original.created_by_name ?? '—' },
    {
        id: 'actions', header: '',
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/handovers/${row.original.id}`}><Eye className="h-4 w-4" /></Link></Button>
                {row.original.pdf_ready && row.original.pdf_url && (
                    <Button asChild variant="ghost" size="icon"><a href={row.original.pdf_url}><FileDown className="h-4 w-4" /></a></Button>
                )}
            </div>
        ),
    },
];

export default function HandoversIndex({ handovers }: { handovers: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="Handovers" breadcrumbs={[{ label: 'Handovers' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Handovers</h1>
                <Button asChild><Link href="/app/handovers/create">New handover</Link></Button>
            </div>
            <DataTable columns={columns} rows={handovers.data} pagination={handovers.meta} baseUrl="/app/handovers"
                sortable={['signed_at', 'type', 'recipient_name', 'assets_count']} />
        </AppLayout>
    );
}
```

- [ ] **Step 2: Create the detail page**

```tsx
// resources/js/pages/handovers/show.tsx
import { Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface HandoverDetail {
    id: string; type_label: string | null; recipient_name: string; recipient_email: string | null;
    recipient_kind_label: string | null; accessories: string | null; condition_notes: string | null;
    terms_text: string; created_by_name: string | null; signed_at: string | null;
    pdf_ready: boolean; pdf_url: string | null;
}
interface AssetRow { id: string; label: string; state_from: string | null; state_to: string | null; }

export default function HandoverShow({ handover, assets }: { handover: HandoverDetail; assets: AssetRow[] }) {
    return (
        <AppLayout title="Handover" breadcrumbs={[{ label: 'Handovers', href: '/app/handovers' }, { label: handover.recipient_name }]}>
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold">Handover</h1>
                    <Badge variant="secondary">{handover.type_label}</Badge>
                </div>
                {handover.pdf_ready && handover.pdf_url
                    ? <Button asChild><a href={handover.pdf_url}>Download PDF</a></Button>
                    : <span className="text-sm text-muted-foreground">PDF is generating…</span>}
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Recipient</CardTitle></CardHeader>
                    <CardContent className="space-y-1 text-sm">
                        <div>{handover.recipient_name} <span className="text-muted-foreground">({handover.recipient_kind_label})</span></div>
                        <div className="text-muted-foreground">{handover.recipient_email ?? '—'}</div>
                        <div className="text-xs text-muted-foreground">Signed {handover.signed_at ?? '—'} · by {handover.created_by_name ?? '—'}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Details</CardTitle></CardHeader>
                    <CardContent className="space-y-1 text-sm">
                        <div><span className="text-muted-foreground">Accessories:</span> {handover.accessories ?? '—'}</div>
                        <div><span className="text-muted-foreground">Condition:</span> {handover.condition_notes ?? '—'}</div>
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-4">
                <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Assets</CardTitle></CardHeader>
                <CardContent>
                    <ul className="divide-y text-sm">
                        {assets.map((a) => (
                            <li key={a.id} className="flex items-center justify-between py-2">
                                <span>{a.label}</span>
                                <span className="text-xs text-muted-foreground">{a.state_from} → {a.state_to}</span>
                            </li>
                        ))}
                    </ul>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
```

- [ ] **Step 3: Flip the test existence checks**

In `tests/Feature/App/HandoverControllerTest.php`, change `->component('handovers/index', false)` → `->component('handovers/index')` and `->component('handovers/show', false)` → `->component('handovers/show')`.

- [ ] **Step 4: Build + test**

Run: `ddev exec pnpm run build` → succeeds.
Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/HandoverControllerTest.php` → PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/handovers/index.tsx resources/js/pages/handovers/show.tsx tests/Feature/App/HandoverControllerTest.php
git commit -m "feat(handovers): Inertia list + detail pages"
```

---

### Task 4: Create wizard (stepped) + asset multi-select + signature

**Files:**
- Create: `resources/js/pages/handovers/create.tsx`, `resources/js/pages/handovers/handover-wizard.tsx`, `resources/js/pages/handovers/asset-picker.tsx`
- Test: `resources/js/pages/handovers/__tests__/handover-wizard.test.tsx`

**Interfaces:**
- Consumes: `SelectField`, `TextField`, `Textarea`, `Checkbox`, `Input`, `SignaturePad`, `AppLayout`, Inertia `useForm`; the create props from Task 2.
- Produces: `HandoverWizard` (`{ typeOptions; recipientKindOptions; userOptions; assetOptions; defaultTerms }`) posting to `/app/handovers`; `AssetPicker` (a filtered checkbox multi-select).

- [ ] **Step 1: Write the failing test**

```tsx
// resources/js/pages/handovers/__tests__/handover-wizard.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { HandoverWizard } from '../handover-wizard';

const props = {
    typeOptions: [
        { value: 'issue', label: 'Issue', allowedStates: ['new', 'storage'], assignsOwner: true },
        { value: 'return', label: 'Return', allowedStates: ['in-use', 'lend'], assignsOwner: false },
    ],
    recipientKindOptions: [{ value: 'internal', label: 'Internal' }, { value: 'external', label: 'External' }],
    userOptions: [{ value: 'u1', label: 'Ada', email: 'ada@x.test' }],
    assetOptions: [
        { value: 'a1', label: '(Acme) X1 — SN1', state: 'new' },
        { value: 'a2', label: '(Dell) U27 — SN2', state: 'in-use' },
    ],
    defaultTerms: 'Default terms',
};

describe('HandoverWizard', () => {
    it('starts on step 1 and filters assets by the selected type allowed states', () => {
        render(<HandoverWizard {...props} />);
        // issue is the first type option (default); only the 'new' asset is eligible
        expect(screen.getByText('(Acme) X1 — SN1')).toBeInTheDocument();
        expect(screen.queryByText('(Dell) U27 — SN2')).not.toBeInTheDocument();
    });

    it('advances to the recipient step via Next', () => {
        render(<HandoverWizard {...props} />);
        // pick the eligible asset so step 1 is valid
        fireEvent.click(screen.getByLabelText('(Acme) X1 — SN1'));
        fireEvent.click(screen.getByRole('button', { name: /next/i }));
        // "Recipient kind" is unique to step 2 (the progress bar's "Recipient"
        // label is always present, so match the field label instead).
        expect(screen.getByText('Recipient kind')).toBeInTheDocument();
    });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev exec pnpm exec vitest run resources/js/pages/handovers/__tests__/handover-wizard.test.tsx`
Expected: FAIL — module missing.

- [ ] **Step 3: Create the asset picker**

```tsx
// resources/js/pages/handovers/asset-picker.tsx
import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

export interface AssetOption { value: string; label: string; state: string; }

interface Props { options: AssetOption[]; selected: string[]; onChange: (ids: string[]) => void; }

export function AssetPicker({ options, selected, onChange }: Props) {
    const [q, setQ] = useState('');
    const shown = options.filter((o) => o.label.toLowerCase().includes(q.toLowerCase()));

    const toggle = (id: string) =>
        onChange(selected.includes(id) ? selected.filter((s) => s !== id) : [...selected, id]);

    return (
        <div className="space-y-2">
            <Input placeholder="Filter assets…" value={q} onChange={(e) => setQ(e.target.value)} />
            <div className="max-h-64 space-y-1 overflow-y-auto rounded-md border p-2">
                {shown.length === 0 && <p className="p-2 text-sm text-muted-foreground">No eligible assets.</p>}
                {shown.map((o) => (
                    <label key={o.value} htmlFor={`asset-${o.value}`} className="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-accent">
                        <Checkbox id={`asset-${o.value}`} checked={selected.includes(o.value)} onCheckedChange={() => toggle(o.value)} />
                        <span>{o.label}</span>
                    </label>
                ))}
            </div>
        </div>
    );
}
```

- [ ] **Step 4: Create the wizard**

```tsx
// resources/js/pages/handovers/handover-wizard.tsx
import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { TextField } from '@/components/form/text-field';
import { SelectField } from '@/components/form/select-field';
import { FormError } from '@/components/form/form-error';
import { SignaturePad } from '@/components/handover/signature-pad';
import { AssetPicker, type AssetOption } from './asset-picker';

type Option = { value: string; label: string };
type TypeOption = { value: string; label: string; allowedStates: string[]; assignsOwner: boolean };
type UserOption = { value: string; label: string; email: string | null };

export interface HandoverWizardProps {
    typeOptions: TypeOption[]; recipientKindOptions: Option[]; userOptions: UserOption[];
    assetOptions: AssetOption[]; defaultTerms: string;
}

const STEP_OF_FIELD: Record<string, number> = {
    type: 1, asset_ids: 1, recipient_kind: 2, recipient_user_id: 2, recipient_name: 2, recipient_email: 2,
    accessories: 3, condition_notes: 3, terms_text: 3, signature_png: 4,
};

export function HandoverWizard({ typeOptions, recipientKindOptions, userOptions, assetOptions, defaultTerms }: HandoverWizardProps) {
    const [step, setStep] = useState(1);
    const form = useForm({
        type: typeOptions[0]?.value ?? '', asset_ids: [] as string[],
        recipient_kind: recipientKindOptions[0]?.value ?? '', recipient_user_id: '',
        recipient_name: '', recipient_email: '', accessories: '', condition_notes: '',
        terms_text: defaultTerms, signature_png: '',
    });

    const selectedType = typeOptions.find((t) => t.value === form.data.type);
    const eligibleAssets = assetOptions.filter((a) => selectedType?.allowedStates.includes(a.state));
    const isInternal = form.data.recipient_kind === 'internal';

    const setType = (v: string) => {
        const t = typeOptions.find((o) => o.value === v);
        // drop now-ineligible picks
        const stillOk = form.data.asset_ids.filter((id) => {
            const a = assetOptions.find((o) => o.value === id);
            return a && t?.allowedStates.includes(a.state);
        });
        form.setData((d) => ({ ...d, type: v, asset_ids: stillOk }));
    };

    const pickUser = (v: string) => {
        const u = userOptions.find((o) => o.value === v);
        form.setData((d) => ({ ...d, recipient_user_id: v, recipient_name: u?.label ?? d.recipient_name, recipient_email: u?.email ?? d.recipient_email }));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/app/handovers', {
            onError: (errors) => {
                const first = Object.keys(errors)[0];
                if (first && STEP_OF_FIELD[first]) setStep(STEP_OF_FIELD[first]);
            },
        });
    };

    const canNext =
        (step === 1 && form.data.type !== '' && form.data.asset_ids.length > 0) ||
        (step === 2 && form.data.recipient_name !== '' && (!isInternal || form.data.recipient_user_id !== '')) ||
        step === 3;

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <ol className="flex gap-2 text-sm">
                {['Type & assets', 'Recipient', 'Details', 'Review & sign'].map((label, i) => (
                    <li key={label} className={`rounded px-2 py-1 ${step === i + 1 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'}`}>{i + 1}. {label}</li>
                ))}
            </ol>

            {step === 1 && (
                <div className="space-y-4">
                    <SelectField id="type" label="Type" required options={typeOptions.map((t) => ({ value: t.value, label: t.label }))}
                        value={form.data.type} onChange={setType} error={form.errors.type} />
                    <div className="space-y-1">
                        <Label>Assets</Label>
                        <AssetPicker options={eligibleAssets} selected={form.data.asset_ids} onChange={(ids) => form.setData('asset_ids', ids)} />
                        <FormError message={form.errors.asset_ids} />
                    </div>
                </div>
            )}

            {step === 2 && (
                <div className="space-y-4">
                    <h2 className="text-lg font-medium">Recipient</h2>
                    <SelectField id="recipient_kind" label="Recipient kind" required options={recipientKindOptions}
                        value={form.data.recipient_kind} onChange={(v) => form.setData('recipient_kind', v)} error={form.errors.recipient_kind} />
                    {isInternal && (
                        <SelectField id="recipient_user_id" label="User" required options={userOptions.map((u) => ({ value: u.value, label: u.label }))}
                            value={form.data.recipient_user_id} onChange={pickUser} error={form.errors.recipient_user_id} />
                    )}
                    <TextField id="recipient_name" label="Recipient name" required
                        value={form.data.recipient_name} onChange={(v) => form.setData('recipient_name', v)} error={form.errors.recipient_name} />
                    <TextField id="recipient_email" label="Recipient email"
                        value={form.data.recipient_email} onChange={(v) => form.setData('recipient_email', v)} error={form.errors.recipient_email} />
                </div>
            )}

            {step === 3 && (
                <div className="space-y-4">
                    <h2 className="text-lg font-medium">Details</h2>
                    <div className="space-y-2"><Label htmlFor="accessories">Accessories</Label>
                        <Textarea id="accessories" value={form.data.accessories} onChange={(e) => form.setData('accessories', e.target.value)} /></div>
                    <div className="space-y-2"><Label htmlFor="condition_notes">Condition notes</Label>
                        <Textarea id="condition_notes" value={form.data.condition_notes} onChange={(e) => form.setData('condition_notes', e.target.value)} /></div>
                    <div className="space-y-2"><Label htmlFor="terms_text">Terms</Label>
                        <Textarea id="terms_text" rows={6} value={form.data.terms_text} onChange={(e) => form.setData('terms_text', e.target.value)} />
                        <FormError message={form.errors.terms_text} /></div>
                </div>
            )}

            {step === 4 && (
                <div className="space-y-4">
                    <h2 className="text-lg font-medium">Review &amp; sign</h2>
                    <div className="rounded-md border p-3 text-sm text-muted-foreground">
                        <div><strong className="text-foreground">{selectedType?.label}</strong> to <strong className="text-foreground">{form.data.recipient_name || '—'}</strong></div>
                        <div>{form.data.asset_ids.length} asset(s)</div>
                    </div>
                    <div className="space-y-1">
                        <Label>Signature</Label>
                        <SignaturePad value={form.data.signature_png} onChange={(b64) => form.setData('signature_png', b64)} />
                        <FormError message={form.errors.signature_png} />
                    </div>
                </div>
            )}

            <div className="flex justify-between">
                <Button type="button" variant="ghost" onClick={() => setStep((s) => Math.max(1, s - 1))} disabled={step === 1}>Back</Button>
                {step < 4
                    ? <Button type="button" onClick={() => setStep((s) => s + 1)} disabled={!canNext}>Next</Button>
                    : <Button type="submit" disabled={form.processing || form.data.signature_png === ''}>Create handover</Button>}
            </div>
        </form>
    );
}
```

- [ ] **Step 5: Create the create page**

```tsx
// resources/js/pages/handovers/create.tsx
import AppLayout from '@/layouts/app-layout';
import { HandoverWizard, type HandoverWizardProps } from './handover-wizard';

export default function CreateHandover(props: HandoverWizardProps) {
    return (
        <AppLayout title="New handover" breadcrumbs={[{ label: 'Handovers', href: '/app/handovers' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New handover</h1>
            <HandoverWizard {...props} />
        </AppLayout>
    );
}
```

- [ ] **Step 6: Run test + build**

Run: `ddev exec pnpm exec vitest run resources/js/pages/handovers/__tests__/handover-wizard.test.tsx`
Expected: PASS (2). (If `form.setData((d) => ...)` functional form isn't supported by the installed Inertia version, use the object/spread form `form.setData({ ...form.data, ... })`.)
Run: `ddev exec pnpm run build` → succeeds.
Run: `ddev exec pnpm run test` → full JS suite green.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/handovers/create.tsx resources/js/pages/handovers/handover-wizard.tsx resources/js/pages/handovers/asset-picker.tsx resources/js/pages/handovers/__tests__/handover-wizard.test.tsx
git commit -m "feat(handovers): stepped create wizard (asset picker + signature)"
```

---

### Task 5: Final verification

**Files:** none (verification only).

- [ ] **Step 1: Full PHP suite** — `ddev exec php artisan test` → green.
- [ ] **Step 2: Full JS suite** — `ddev exec pnpm run test` → green.
- [ ] **Step 3: Build** — `ddev exec pnpm run build` → succeeds.
- [ ] **Step 4: Manual verify** — `/app/handovers`: New handover → pick type (asset list filters to eligible states), pick assets, recipient (internal autofills), details, sign, Create → lands on detail; asset states/owners changed; PDF appears after the queue runs (`ddev artisan queue:work --once`); download works. `/app-old` Filament handovers still work.
- [ ] **Step 5:** Commit anything outstanding (releases automated — skip manual CHANGELOG).

---

## Self-Review Notes

- **Spec coverage:** `SignaturePad` → Task 1; controller (index/create/store/show) + `HandoverRequest` (enum, required_if, asset-state after-rule, signature) + routes + Operations nav → Task 2; list + detail → Task 3; stepped wizard + asset picker + signature wiring → Task 4; verification → Task 5. Reuses `HandoverService`/job/mail/PDF/DTO/enums/pivot/signed route.
- **Type consistency:** `HandoverData` ctor args match the DTO order/names; row/detail prop shapes match the page interfaces; `typeOptions.allowedStates` drives the client asset filter (backend is the source of truth); `pdf_url` via `URL::signedRoute('handover.pdf', ['handover' => id])`.
- **Store exceptions:** `HandoverStateConflictException` → `asset_ids` error; `\InvalidArgumentException` (signature) → `signature_png` error. FormRequest pre-validates asset states so the common case is a clean error.
- **Routing:** `handovers/create` before `handovers/{handover}`.
- **Deferred:** edit/delete, sync PDF, inline signature route, reusable Stepper, QR asset selection (Spec 8).
- **Risk note (Task 4):** `useForm().setData` functional-updater vs object form — the plan flags the fallback if the installed Inertia version rejects the callback form.

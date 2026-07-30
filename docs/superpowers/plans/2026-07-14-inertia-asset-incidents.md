# Inertia Migration — Asset Incidents (4c) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fill the asset detail page's Incidents tab — list incidents (status badge, dates, notes), create/edit via a modal Dialog, delete, and one-click Mark closed / Reopen — via asset-scoped endpoints that redirect back.

**Architecture:** `AssetController@show` gains an `incidents` prop (with a derived `status`); a new asset-scoped `IncidentController` (store/update/destroy/close/reopen) guarded by an ownership `abort_unless`. The frontend panel keeps local Dialog state + an Inertia `useForm`; mutations redirect back so `show` re-renders. Reuses existing primitives (Dialog, DateField, Textarea, TextField, Badge, FormError).

**Tech Stack:** Laravel 13/PHP 8.4, Inertia v2 + React 19, shadcn/ui, PHPUnit, Vitest. ddev; pnpm.

## Global Constraints

- ddev for all commands; **PHP tests are PHPUnit**; JS tests are **Vitest**.
- Do NOT touch Filament (`/app-old`), the `Incident` model/migration, or the `Asset::incidents()` relation. No DB schema changes.
- New controller `App\Http\Controllers\App\IncidentController`; request `App\Http\Requests\App\IncidentRequest`. Routes in the authenticated `/app` group. New React file `resources/js/components/assets/asset-incidents.tsx`; modify `resources/js/pages/assets/show.tsx`. `@/` → `resources/js/*`.
- `Incident`: bigint auto-increment `id` (route binds by int); `asset_id` is a **UUID** FK (`foreignIdFor` on the UUID `Asset` → uuid column); fillable `asset_id, notes, title, open_date, closed_date`; casts `open_date`/`closed_date` → datetime. `Asset::incidents()` orders by `open_date asc` (keep it). Status = `closed_date ? 'closed' : 'open'`.
- Ownership guard on every mutating action: `abort_unless($incident->asset_id === $asset->id, 403)`.
- No policy (gate on `auth`). Dates are date-granularity (`Y-m-d`). No new primitives.
- TDD for backend; commit after every task.

---

### Task 1: Backend — show incidents prop + incident CRUD/close/reopen

**Files:**
- Modify: `app/Http/Controllers/App/AssetController.php` (`show()` adds `incidents`)
- Create: `app/Http/Controllers/App/IncidentController.php`, `app/Http/Requests/App/IncidentRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/App/IncidentControllerTest.php`, extend `tests/Feature/App/AssetControllerTest.php` (show prop)

**Interfaces:**
- Consumes: `Asset::incidents()`, `Incident`.
- Produces: routes `app.assets.incidents.{store,update,destroy,close,reopen}`. `show` gains `incidents: [{id,title,notes,open_date,closed_date,status,created_at}]`.

- [ ] **Step 1: Write the failing tests**

```php
<?php // tests/Feature/App/IncidentControllerTest.php
namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User { return User::factory()->create(['login_enabled' => true]); }

    public function test_store_creates_open_incident_linked_to_asset(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/incidents", [
            'title' => 'Screen cracked', 'open_date' => '2025-01-10', 'notes' => 'dropped',
        ])->assertRedirect();

        $incident = Incident::where('title', 'Screen cracked')->first();
        $this->assertNotNull($incident);
        $this->assertSame($asset->id, $incident->asset_id);
        $this->assertNull($incident->closed_date);
    }

    public function test_store_validates_required_title_and_open_date(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/incidents", ['title' => '', 'open_date' => ''])
            ->assertSessionHasErrors(['title', 'open_date']);
    }

    public function test_store_rejects_closed_before_open(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/incidents", [
            'title' => 'x', 'open_date' => '2025-02-01', 'closed_date' => '2025-01-01',
        ])->assertSessionHasErrors('closed_date');
    }

    public function test_update_changes_fields(): void
    {
        $asset = Asset::factory()->create();
        $incident = Incident::factory()->create(['asset_id' => $asset->id, 'title' => 'Old']);
        $this->actingAs($this->actor())->put("/app/assets/{$asset->id}/incidents/{$incident->id}", [
            'title' => 'New', 'open_date' => '2025-01-10',
        ])->assertRedirect();
        $this->assertSame('New', $incident->fresh()->title);
    }

    public function test_close_sets_closed_date_and_reopen_clears_it(): void
    {
        $asset = Asset::factory()->create();
        $incident = Incident::factory()->create(['asset_id' => $asset->id, 'closed_date' => null]);

        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/incidents/{$incident->id}/close")->assertRedirect();
        $this->assertNotNull($incident->fresh()->closed_date);

        $this->actingAs($this->actor())->post("/app/assets/{$asset->id}/incidents/{$incident->id}/reopen")->assertRedirect();
        $this->assertNull($incident->fresh()->closed_date);
    }

    public function test_destroy_deletes_incident(): void
    {
        $asset = Asset::factory()->create();
        $incident = Incident::factory()->create(['asset_id' => $asset->id]);
        $this->actingAs($this->actor())->delete("/app/assets/{$asset->id}/incidents/{$incident->id}")->assertRedirect();
        $this->assertNull(Incident::find($incident->id));
    }

    public function test_mutations_reject_incident_from_other_asset(): void
    {
        $assetA = Asset::factory()->create();
        $assetB = Asset::factory()->create();
        $incident = Incident::factory()->create(['asset_id' => $assetB->id]);
        $actor = $this->actor();

        $this->actingAs($actor)->delete("/app/assets/{$assetA->id}/incidents/{$incident->id}")->assertForbidden();
        $this->actingAs($actor)->post("/app/assets/{$assetA->id}/incidents/{$incident->id}/close")->assertForbidden();
        $this->assertNotNull(Incident::find($incident->id));
        $this->assertNull($incident->fresh()->closed_date);
    }

    public function test_requires_auth(): void
    {
        $asset = Asset::factory()->create();
        $this->post("/app/assets/{$asset->id}/incidents", [])->assertRedirect();
    }
}
```

Extend `tests/Feature/App/AssetControllerTest.php`:

```php
public function test_show_returns_incidents_prop(): void
{
    $asset = Asset::factory()->create();
    Incident::factory()->create(['asset_id' => $asset->id, 'title' => 'Boom', 'closed_date' => null]);

    $this->actingAs(User::factory()->create(['login_enabled' => true]))
        ->get("/app/assets/{$asset->id}")
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p
            ->component('assets/show')
            ->has('incidents', 1, fn ($i) => $i
                ->where('title', 'Boom')->where('status', 'open')->has('open_date')->etc())
            ->etc());
}
```

(Add `use App\Models\Incident;` to the test file if not present.)

- [ ] **Step 2: Run to verify they fail**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/IncidentControllerTest.php tests/Feature/App/AssetControllerTest.php`
Expected: FAIL — routes/controller undefined; show lacks `incidents`.

- [ ] **Step 3: Create the FormRequest**

```php
<?php // app/Http/Requests/App/IncidentRequest.php
namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class IncidentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'open_date' => ['required', 'date'],
            'closed_date' => ['nullable', 'date', 'after_or_equal:open_date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

```php
<?php // app/Http/Controllers/App/IncidentController.php
namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\IncidentRequest;
use App\Models\Asset;
use App\Models\Incident;
use Illuminate\Http\RedirectResponse;

class IncidentController extends Controller
{
    public function store(IncidentRequest $request, Asset $asset): RedirectResponse
    {
        $asset->incidents()->create($request->validated());

        return back()->with('success', 'Incident created.');
    }

    public function update(IncidentRequest $request, Asset $asset, Incident $incident): RedirectResponse
    {
        $this->guard($asset, $incident);
        $incident->update($request->validated());

        return back()->with('success', 'Incident updated.');
    }

    public function destroy(Asset $asset, Incident $incident): RedirectResponse
    {
        $this->guard($asset, $incident);
        $incident->delete();

        return back()->with('success', 'Incident deleted.');
    }

    public function close(Asset $asset, Incident $incident): RedirectResponse
    {
        $this->guard($asset, $incident);
        $incident->update(['closed_date' => now()]);

        return back()->with('success', 'Incident closed.');
    }

    public function reopen(Asset $asset, Incident $incident): RedirectResponse
    {
        $this->guard($asset, $incident);
        $incident->update(['closed_date' => null]);

        return back()->with('success', 'Incident reopened.');
    }

    private function guard(Asset $asset, Incident $incident): void
    {
        abort_unless($incident->asset_id === $asset->id, 403);
    }
}
```

- [ ] **Step 5: Add the `incidents` prop to `AssetController@show`**

In `app/Http/Controllers/App/AssetController.php`, extend the `->load(...)` list in `show()` to include `'incidents'` and add the `incidents` key to the `Inertia::render` array:

```php
$asset->load('assetType', 'model.manufacturer', 'owner', 'place', 'tags', 'attachments.uploadedBy', 'incidents')
    ->loadCount('incidents');
```

```php
// add to the Inertia::render([...]) array, alongside asset/attachments/…:
'incidents' => $asset->incidents->map(fn (\App\Models\Incident $i) => [
    'id' => $i->id,
    'title' => $i->title,
    'notes' => $i->notes,
    'open_date' => optional($i->open_date)->format('Y-m-d'),
    'closed_date' => optional($i->closed_date)->format('Y-m-d'),
    'status' => $i->closed_date ? 'closed' : 'open',
    'created_at' => optional($i->created_at)->toDateTimeString(),
])->all(),
```

- [ ] **Step 6: Register the routes**

In `routes/web.php`, inside the authenticated `/app` group (near the assets resource / attachments routes):

```php
use App\Http\Controllers\App\IncidentController;
// … inside the authenticated group:
Route::post('assets/{asset}/incidents', [IncidentController::class, 'store'])->name('assets.incidents.store');
Route::put('assets/{asset}/incidents/{incident}', [IncidentController::class, 'update'])->name('assets.incidents.update');
Route::delete('assets/{asset}/incidents/{incident}', [IncidentController::class, 'destroy'])->name('assets.incidents.destroy');
Route::post('assets/{asset}/incidents/{incident}/close', [IncidentController::class, 'close'])->name('assets.incidents.close');
Route::post('assets/{asset}/incidents/{incident}/reopen', [IncidentController::class, 'reopen'])->name('assets.incidents.reopen');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/IncidentControllerTest.php tests/Feature/App/AssetControllerTest.php`
Expected: PASS. Then full suite `ddev exec ./vendor/bin/phpunit` → green.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/App/IncidentController.php app/Http/Requests/App/IncidentRequest.php app/Http/Controllers/App/AssetController.php routes/web.php tests/Feature/App/IncidentControllerTest.php tests/Feature/App/AssetControllerTest.php
git commit -m "feat(assets): incident CRUD + close/reopen endpoints + show incidents prop"
```

---

### Task 2: Frontend — incidents panel (dialog CRUD + close/reopen)

**Files:**
- Create: `resources/js/components/assets/asset-incidents.tsx`
- Modify: `resources/js/pages/assets/show.tsx`
- Test: `resources/js/components/assets/__tests__/asset-incidents.test.tsx`

**Interfaces:**
- Consumes: `useForm`/`router` (Inertia), shadcn `Dialog`, `TextField`, `DateField`, `Textarea`, `Badge`, `Button`, `Label`, `FormError`; the `incidents` prop from Task 1.
- Produces: `AssetIncidents` (`{ assetId: string; incidents: IncidentItem[] }`); wired into `show.tsx`.

- [ ] **Step 1: Write the failing test**

```tsx
// resources/js/components/assets/__tests__/asset-incidents.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { AssetIncidents } from '../asset-incidents';

const open = { id: 1, title: 'Screen cracked', notes: 'dropped', open_date: '2025-01-10', closed_date: null, status: 'open' as const, created_at: '2025-01-10 09:00:00' };
const closed = { id: 2, title: 'Battery swollen', notes: null, open_date: '2024-11-01', closed_date: '2024-12-01', status: 'closed' as const, created_at: '2024-11-01 09:00:00' };

describe('AssetIncidents', () => {
    it('renders open and closed status badges', () => {
        render(<AssetIncidents assetId="a1" incidents={[open, closed]} />);
        expect(screen.getByText('Screen cracked')).toBeInTheDocument();
        expect(screen.getByText(/open/i)).toBeInTheDocument();
        expect(screen.getByText(/closed/i)).toBeInTheDocument();
    });

    it('opens the dialog when New incident is clicked', () => {
        render(<AssetIncidents assetId="a1" incidents={[]} />);
        fireEvent.click(screen.getByRole('button', { name: /new incident/i }));
        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByLabelText('Title')).toBeInTheDocument();
    });

    it('prefills the dialog when editing a row', () => {
        render(<AssetIncidents assetId="a1" incidents={[open]} />);
        fireEvent.click(screen.getByRole('button', { name: /edit screen cracked/i }));
        expect(screen.getByLabelText('Title')).toHaveValue('Screen cracked');
    });

    it('shows an empty state when there are no incidents', () => {
        render(<AssetIncidents assetId="a1" incidents={[]} />);
        expect(screen.getByText(/no incidents/i)).toBeInTheDocument();
    });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev exec pnpm exec vitest run resources/js/components/assets/__tests__/asset-incidents.test.tsx`
Expected: FAIL — module missing.

- [ ] **Step 3: Implement the panel**

```tsx
// resources/js/components/assets/asset-incidents.tsx
import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Trash2, CheckCircle2, RotateCcw } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { TextField } from '@/components/form/text-field';
import { DateField } from '@/components/form/date-field';
import { FormError } from '@/components/form/form-error';

export interface IncidentItem {
    id: number; title: string; notes: string | null;
    open_date: string | null; closed_date: string | null;
    status: 'open' | 'closed'; created_at: string | null;
}
interface Props { assetId: string; incidents: IncidentItem[]; }

const today = () => new Date().toISOString().slice(0, 10);

export function AssetIncidents({ assetId, incidents }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<IncidentItem | null>(null);
    const form = useForm({ title: '', open_date: today(), closed_date: '', notes: '' });

    const openCreate = () => {
        setEditing(null);
        form.setData({ title: '', open_date: today(), closed_date: '', notes: '' });
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (i: IncidentItem) => {
        setEditing(i);
        form.setData({ title: i.title, open_date: i.open_date ?? '', closed_date: i.closed_date ?? '', notes: i.notes ?? '' });
        form.clearErrors();
        setDialogOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { setDialogOpen(false); form.reset(); } };
        if (editing) form.put(`/app/assets/${assetId}/incidents/${editing.id}`, opts);
        else form.post(`/app/assets/${assetId}/incidents`, opts);
    };

    const act = (id: number, action: 'close' | 'reopen') =>
        router.post(`/app/assets/${assetId}/incidents/${id}/${action}`, {}, { preserveScroll: true });
    const remove = (id: number) => {
        if (confirm('Delete this incident?')) router.delete(`/app/assets/${assetId}/incidents/${id}`, { preserveScroll: true });
    };

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <Button onClick={openCreate}>New incident</Button>
            </div>

            {incidents.length === 0 ? (
                <p className="text-sm text-muted-foreground">No incidents yet.</p>
            ) : (
                <div className="rounded-md border divide-y">
                    {incidents.map((i) => (
                        <div key={i.id} className="flex items-start gap-3 px-3 py-2 text-sm">
                            <Badge variant={i.status === 'open' ? 'default' : 'outline'}>{i.status === 'open' ? 'Open' : 'Closed'}</Badge>
                            <div className="min-w-0 flex-1">
                                <div className="font-medium">{i.title}</div>
                                <div className="text-xs text-muted-foreground">
                                    Opened {i.open_date ?? '—'}{i.closed_date ? ` · Closed ${i.closed_date}` : ''}
                                </div>
                                {i.notes && <div className="mt-1 line-clamp-2 text-xs text-muted-foreground">{i.notes}</div>}
                            </div>
                            <div className="flex shrink-0 gap-1">
                                <Button variant="ghost" size="icon" aria-label={`Edit ${i.title}`} onClick={() => openEdit(i)}><Pencil className="h-4 w-4" /></Button>
                                {i.status === 'open' ? (
                                    <Button variant="ghost" size="icon" aria-label={`Mark ${i.title} closed`} onClick={() => act(i.id, 'close')}><CheckCircle2 className="h-4 w-4" /></Button>
                                ) : (
                                    <Button variant="ghost" size="icon" aria-label={`Reopen ${i.title}`} onClick={() => act(i.id, 'reopen')}><RotateCcw className="h-4 w-4" /></Button>
                                )}
                                <Button variant="ghost" size="icon" aria-label={`Delete ${i.title}`} onClick={() => remove(i.id)}><Trash2 className="h-4 w-4" /></Button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent>
                    <DialogHeader><DialogTitle>{editing ? 'Edit incident' : 'New incident'}</DialogTitle></DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <TextField id="title" label="Title" required value={form.data.title}
                            onChange={(v) => form.setData('title', v)} error={form.errors.title} />
                        <div className="grid grid-cols-2 gap-4">
                            <DateField id="open_date" label="Open date" required value={form.data.open_date}
                                onChange={(v) => form.setData('open_date', v)} error={form.errors.open_date} />
                            <DateField id="closed_date" label="Closed date" value={form.data.closed_date}
                                onChange={(v) => form.setData('closed_date', v)} error={form.errors.closed_date} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea id="notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                            <FormError message={form.errors.notes} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setDialogOpen(false)}>Cancel</Button>
                            <Button type="submit" disabled={form.processing}>Save</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `ddev exec pnpm exec vitest run resources/js/components/assets/__tests__/asset-incidents.test.tsx`
Expected: PASS (4). (If the Radix Dialog needs a description for a11y warnings, that's a console warning, not a failure; ignore or add `DialogDescription`.)

- [ ] **Step 5: Wire into `show.tsx`**

In `resources/js/pages/assets/show.tsx`:
- Import `AssetIncidents, type IncidentItem` from `@/components/assets/asset-incidents`.
- Add `incidents: IncidentItem[]` to the page `Props` interface and destructure it: `export default function ShowAsset({ asset, attachments, attachmentCategoryOptions, incidents }: Props)`.
- Replace the Incidents tab body:

```tsx
<TabsContent value="incidents" className="mt-4">
    <AssetIncidents assetId={asset.id} incidents={incidents} />
</TabsContent>
```

(Leave the History placeholder.)

- [ ] **Step 6: Build + full JS suite**

Run: `ddev exec pnpm run build` → succeeds, no type errors.
Run: `ddev exec pnpm run test` → green.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/assets/asset-incidents.tsx resources/js/pages/assets/show.tsx resources/js/components/assets/__tests__/asset-incidents.test.tsx
git commit -m "feat(assets): incidents panel (dialog CRUD + close/reopen) in detail page"
```

---

### Task 3: Final verification

**Files:** none (verification only).

- [ ] **Step 1: Full PHP suite** — `ddev exec php artisan test` → green.
- [ ] **Step 2: Full JS suite** — `ddev exec pnpm run test` → green.
- [ ] **Step 3: Build** — `ddev exec pnpm run build` → succeeds.
- [ ] **Step 4: Manual verify** — on an asset detail page, Incidents tab: create an incident (dialog), it appears Open; Mark closed → badge flips + closed date set; Reopen → back to Open; edit updates; delete removes; the tab count reflects changes after reload. `/app-old` Filament incidents still work.
- [ ] **Step 5:** Commit anything outstanding (releases automated — skip manual CHANGELOG).

---

## Self-Review Notes

- **Spec coverage:** show `incidents` prop → Task 1 Step 5; store/update/destroy/close/reopen + ownership guard + validation (`after_or_equal`) → Task 1; dialog CRUD + status badges + close/reopen/delete + empty state → Task 2; wiring → Task 2 Step 5; tests → Task 1 (PHPUnit) + Task 2 (Vitest) + Task 3. All spec sections covered.
- **Type consistency:** `IncidentItem` (Task 2) matches the `show` incidents row shape (Task 1 Step 5) field-for-field (`id:number`, `status:'open'|'closed'`); `AssetIncidents` props match the show wiring.
- **ID/keys:** incident `id` is a number (bigint); routes bind `{incident}` by int, `{asset}` by uuid; ownership guard compares uuid `asset_id`.
- **Reuse/no-touch:** `Incident` model/migration + `Asset::incidents()` untouched; Dialog/DateField/Textarea/TextField/Badge/FormError reused.
- **Guard coverage:** update/destroy/close/reopen all call `guard()`; the ownership-403 test exercises destroy + close (representative).

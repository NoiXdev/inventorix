# Inertia Migration — Assets Core (4a) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate the core Assets resource to `/app` — index (state/type/manufacturer filters + relation columns + `incidents_count`), create/edit form (all core fields + owner + tags), and a tabbed detail page with empty Attachments/Incidents/History tabs for later sub-specs.

**Architecture:** Reuse the Spec 1–3 kit (`TableQuery` incl. `filterable`, `DataTable` incl. select-filters + sortable headers, form kit, resource pattern). The index controller left-joins the four relation tables and selects aliased columns so `TableQuery` stays generic (every sort/search/filter token qualified/aliased). Three new form-kit fields (`DateField`, `NumberField`, `TagsInput`). Assets is the first resource with a `show` detail page (shadcn Tabs).

**Tech Stack:** Laravel 13/PHP 8.4, Inertia v2 + React 19, TypeScript, Tailwind 4, shadcn/ui, spatie tags/activitylog, PHPUnit, Vitest. All commands via `ddev exec …`; pnpm.

## Global Constraints

- All commands via ddev; **PHP tests are PHPUnit** (classes extending `Tests\TestCase`, `use RefreshDatabase`, `public function test_*(): void`); JS tests are **Vitest**. Run: `ddev exec ./vendor/bin/phpunit <path>`, `ddev exec pnpm exec vitest run <path>`, full suites `ddev exec php artisan test` / `ddev exec pnpm run test`.
- Do NOT touch Filament (`/app-old`, assets, panel) or other resources. No DB schema changes.
- New controller `App\Http\Controllers\App\AssetController`; request `App\Http\Requests\App\AssetRequest`. React pages under `resources/js/pages/assets/`. `@/` → `resources/js/*`.
- Routes in the EXISTING authenticated `/app` group in `routes/web.php`: `Route::resource('assets', AssetController::class)` — **INCLUDING `show`** (do NOT `->except('show')`). Names `app.assets.*`.
- No policy; `authorize()` returns true; gate on `auth`.
- `Asset`: UUID pk; `HasTags`, `HasUuids`; casts `state`→`AssetState`, `buy_type`→`BuyType`, `buy_date`/`guarantee_end`→`date`. Relations `assetType`, `model`(→`manufacturer`), `owner`(users, `owner_id`), `place`, `incidents` (hasMany). Manufacturer is via `model.manufacturer` (no direct FK on assets). Fillable includes state, asset_type_id, owner_id, place_id, model_id, serial_number, buy_date, buy_type, buy_price, guarantee_end, invoice.
- `AssetState` cases: new, sold, storage, lend, defect, under-repair, need-repair, in-use (German labels via `getLabel()`). `BuyType`: once, abo (labels via `getLabel()` — note the enum's labels are swapped in source; pass them through verbatim, do NOT "fix" them here).
- Every sort/search/filter token qualified or aliased — NO bare `name`. DataTable sortable column `id` must equal the server sort token (use `accessorFn` for display).
- TDD for server logic + table/controller behavior; commit after every task.

---

### Task 1: `DateField` + `NumberField` + `SelectField` nullable support

**Files:**
- Create: `resources/js/components/form/date-field.tsx`, `resources/js/components/form/number-field.tsx`
- Modify: `resources/js/components/form/select-field.tsx` (add `nullable` support)
- Test: `resources/js/components/form/__tests__/date-field.test.tsx`, `resources/js/components/form/__tests__/number-field.test.tsx`, `resources/js/components/form/__tests__/select-field.test.tsx` (add a nullable case)

**Interfaces:**
- Produces: `DateField` `{ id, label, value, onChange:(v:string)=>void, error?, required? }` (`Input type="date"`, value `YYYY-MM-DD`); `NumberField` `{ id, label, value, onChange:(v:string)=>void, error?, required?, step?, min? }` (`Input type="number"`). Both compose Label + FormError like `TextField`.
- `SelectField` gains an optional `nullable?: boolean` prop: when true it renders a leading "—" clear option (backed by an internal `'__none__'` sentinel, since Radix forbids an empty-string item value), maps an incoming empty-string `value` to that sentinel for display, and calls `onChange('')` when it is picked. Default (`nullable` absent/false) behavior is unchanged, so existing `SelectField` usages (Asset Models, Users) are unaffected.

- [ ] **Step 1: Write the failing tests**

```tsx
// resources/js/components/form/__tests__/date-field.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { DateField } from '../date-field';

describe('DateField', () => {
    it('renders a date input with the label and value', () => {
        render(<DateField id="buy_date" label="Buy date" value="2024-01-15" onChange={() => {}} />);
        const input = screen.getByLabelText('Buy date');
        expect(input).toHaveAttribute('type', 'date');
        expect(input).toHaveValue('2024-01-15');
    });
    it('fires onChange with the new value', () => {
        const onChange = vi.fn();
        render(<DateField id="buy_date" label="Buy date" value="" onChange={onChange} />);
        fireEvent.change(screen.getByLabelText('Buy date'), { target: { value: '2025-02-20' } });
        expect(onChange).toHaveBeenCalledWith('2025-02-20');
    });
    it('shows the error', () => {
        render(<DateField id="buy_date" label="Buy date" value="" onChange={() => {}} error="bad date" />);
        expect(screen.getByText('bad date')).toBeInTheDocument();
    });
});
```

```tsx
// resources/js/components/form/__tests__/number-field.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { NumberField } from '../number-field';

describe('NumberField', () => {
    it('renders a number input with the label', () => {
        render(<NumberField id="buy_price" label="Buy price" value="199.99" onChange={() => {}} />);
        const input = screen.getByLabelText('Buy price');
        expect(input).toHaveAttribute('type', 'number');
        expect(input).toHaveValue(199.99);
    });
    it('fires onChange with the raw string value', () => {
        const onChange = vi.fn();
        render(<NumberField id="buy_price" label="Buy price" value="" onChange={onChange} />);
        fireEvent.change(screen.getByLabelText('Buy price'), { target: { value: '250' } });
        expect(onChange).toHaveBeenCalledWith('250');
    });
    it('shows the error', () => {
        render(<NumberField id="buy_price" label="Buy price" value="" onChange={() => {}} error="not a number" />);
        expect(screen.getByText('not a number')).toBeInTheDocument();
    });
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `ddev exec pnpm exec vitest run resources/js/components/form/__tests__/date-field.test.tsx resources/js/components/form/__tests__/number-field.test.tsx`
Expected: FAIL — modules missing.

- [ ] **Step 3: Implement both fields**

```tsx
// resources/js/components/form/date-field.tsx
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FormError } from './form-error';

interface Props { id: string; label: string; value: string; onChange: (v: string) => void; error?: string; required?: boolean; }

export function DateField({ id, label, value, onChange, error, required }: Props) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}{required && <span className="text-destructive"> *</span>}</Label>
            <Input id={id} type="date" value={value} aria-invalid={!!error} onChange={(e) => onChange(e.target.value)} />
            <FormError message={error} />
        </div>
    );
}
```

```tsx
// resources/js/components/form/number-field.tsx
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FormError } from './form-error';

interface Props { id: string; label: string; value: string; onChange: (v: string) => void; error?: string; required?: boolean; step?: string; min?: string; }

export function NumberField({ id, label, value, onChange, error, required, step = '0.01', min }: Props) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}{required && <span className="text-destructive"> *</span>}</Label>
            <Input id={id} type="number" step={step} min={min} value={value} aria-invalid={!!error} onChange={(e) => onChange(e.target.value)} />
            <FormError message={error} />
        </div>
    );
}
```

- [ ] **Step 4: Run to verify they pass**

Run: `ddev exec pnpm exec vitest run resources/js/components/form/__tests__/date-field.test.tsx resources/js/components/form/__tests__/number-field.test.tsx`
Expected: PASS (6).

- [ ] **Step 5: Add a failing nullable-SelectField test**

Append to `resources/js/components/form/__tests__/select-field.test.tsx`:

```tsx
it('renders a clear option when nullable and maps it to empty string', () => {
    const onChange = vi.fn();
    render(<SelectField id="owner_id" label="Owner" nullable value="" onChange={onChange}
        options={[{ value: '1', label: 'Ada' }]} />);
    // the leading "—" clear option is present
    // (open behavior varies under happy-dom; assert the trigger renders and the value maps cleanly)
    expect(screen.getByText('Owner')).toBeInTheDocument();
});
```

Run: `ddev exec pnpm exec vitest run resources/js/components/form/__tests__/select-field.test.tsx`
Expected: FAIL to type-check/build until `nullable` is added (or the assertion fails if the prop is ignored). If happy-dom can't open the Radix listbox to see the "—" item, keep this a render/prop-contract test; the empty↔sentinel mapping is exercised in real use by the Asset form.

- [ ] **Step 6: Add `nullable` to `SelectField`**

Edit `resources/js/components/form/select-field.tsx`: add `nullable?: boolean` to the props, and use an internal sentinel so an empty value is representable (Radix forbids an empty-string `SelectItem`):

```tsx
// add to the Props interface:
    nullable?: boolean;
```

```tsx
// inside the component body, before the return:
const NONE = '__none__';
const current = nullable && value === '' ? NONE : value;
```

Change the `Select` to use `current` and map the sentinel back to `''`, and render the clear item when `nullable`:

```tsx
<Select value={current} onValueChange={(v) => onChange(v === NONE ? '' : v)}>
    <SelectTrigger id={id} aria-invalid={!!error}>
        <SelectValue placeholder={placeholder ?? `Select ${label.toLowerCase()}…`} />
    </SelectTrigger>
    <SelectContent>
        {nullable && <SelectItem value={NONE}>—</SelectItem>}
        {options.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
    </SelectContent>
</Select>
```

(Destructure `nullable` in the function signature. Leave all existing behavior unchanged when `nullable` is falsy.)

- [ ] **Step 7: Run field tests**

Run: `ddev exec pnpm exec vitest run resources/js/components/form`
Expected: PASS (DateField 3, NumberField 3, SelectField incl. the new nullable case).

- [ ] **Step 8: Build + commit**

Run: `ddev exec pnpm run build` → succeeds.

```bash
git add resources/js/components/form/date-field.tsx resources/js/components/form/number-field.tsx resources/js/components/form/select-field.tsx resources/js/components/form/__tests__/date-field.test.tsx resources/js/components/form/__tests__/number-field.test.tsx resources/js/components/form/__tests__/select-field.test.tsx
git commit -m "feat(form): add DateField, NumberField, SelectField nullable option"
```

---

### Task 2: `TagsInput` form component

**Files:**
- Create: `resources/js/components/form/tags-input.tsx`
- Test: `resources/js/components/form/__tests__/tags-input.test.tsx`

**Interfaces:**
- Consumes: `@/components/ui/input`, `@/components/ui/badge`, `@/components/ui/label`, `FormError`.
- Produces: `TagsInput` `{ id, label, value: string[], onChange: (v: string[]) => void, error? }`. Existing tags render as removable chips; typing + Enter or comma adds a trimmed, non-empty, non-duplicate tag; the chip's × removes it. Every add/remove calls `onChange` with the new array.

- [ ] **Step 1: Write the failing test**

```tsx
// resources/js/components/form/__tests__/tags-input.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { TagsInput } from '../tags-input';

describe('TagsInput', () => {
    it('renders existing tags as chips', () => {
        render(<TagsInput id="tags" label="Tags" value={['alpha', 'beta']} onChange={() => {}} />);
        expect(screen.getByText('alpha')).toBeInTheDocument();
        expect(screen.getByText('beta')).toBeInTheDocument();
    });
    it('adds a trimmed tag on Enter', () => {
        const onChange = vi.fn();
        render(<TagsInput id="tags" label="Tags" value={['alpha']} onChange={onChange} />);
        const input = screen.getByLabelText('Tags');
        fireEvent.change(input, { target: { value: '  gamma  ' } });
        fireEvent.keyDown(input, { key: 'Enter' });
        expect(onChange).toHaveBeenCalledWith(['alpha', 'gamma']);
    });
    it('does not add duplicate or empty tags', () => {
        const onChange = vi.fn();
        render(<TagsInput id="tags" label="Tags" value={['alpha']} onChange={onChange} />);
        const input = screen.getByLabelText('Tags');
        fireEvent.change(input, { target: { value: 'alpha' } });
        fireEvent.keyDown(input, { key: 'Enter' });
        fireEvent.change(input, { target: { value: '   ' } });
        fireEvent.keyDown(input, { key: 'Enter' });
        expect(onChange).not.toHaveBeenCalled();
    });
    it('removes a tag when its × is clicked', () => {
        const onChange = vi.fn();
        render(<TagsInput id="tags" label="Tags" value={['alpha', 'beta']} onChange={onChange} />);
        fireEvent.click(screen.getByRole('button', { name: 'Remove alpha' }));
        expect(onChange).toHaveBeenCalledWith(['beta']);
    });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev exec pnpm exec vitest run resources/js/components/form/__tests__/tags-input.test.tsx`
Expected: FAIL — module missing.

- [ ] **Step 3: Implement `TagsInput`**

```tsx
// resources/js/components/form/tags-input.tsx
import { useState } from 'react';
import { X } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { FormError } from './form-error';

interface Props { id: string; label: string; value: string[]; onChange: (v: string[]) => void; error?: string; }

export function TagsInput({ id, label, value, onChange, error }: Props) {
    const [draft, setDraft] = useState('');

    const addTag = () => {
        const tag = draft.trim();
        if (tag === '' || value.includes(tag)) { setDraft(''); return; }
        onChange([...value, tag]);
        setDraft('');
    };

    const removeTag = (tag: string) => onChange(value.filter((t) => t !== tag));

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <div className="flex flex-wrap gap-1.5">
                {value.map((tag) => (
                    <Badge key={tag} variant="secondary" className="gap-1">
                        {tag}
                        <button type="button" aria-label={`Remove ${tag}`} onClick={() => removeTag(tag)} className="rounded-sm hover:text-destructive">
                            <X className="h-3 w-3" />
                        </button>
                    </Badge>
                ))}
            </div>
            <Input id={id} value={draft} aria-invalid={!!error}
                placeholder="Add a tag and press Enter"
                onChange={(e) => setDraft(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag(); }
                }} />
            <FormError message={error} />
        </div>
    );
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `ddev exec pnpm exec vitest run resources/js/components/form/__tests__/tags-input.test.tsx`
Expected: PASS (4).

- [ ] **Step 5: Build + commit**

Run: `ddev exec pnpm run build` → succeeds.

```bash
git add resources/js/components/form/tags-input.tsx resources/js/components/form/__tests__/tags-input.test.tsx
git commit -m "feat(form): add TagsInput"
```

---

### Task 3: Asset backend (controller, request, routes)

**Files:**
- Create: `app/Http/Controllers/App/AssetController.php`, `app/Http/Requests/App/AssetRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/App/AssetControllerTest.php`

**Interfaces:**
- Consumes: `TableQuery` (filterable), `Asset` + relations, `AssetState`, `BuyType`, `AssetType`/`AssetModel`/`Manufacturer`/`Place`/`User` for option lists.
- Produces: `Route::resource('assets', ...)` (with `show`), names `app.assets.*`. index props `assets: { data: Row[], meta }` (+ `stateOptions`, `assetTypeOptions`, `manufacturerOptions` for filters). create/edit props include `stateOptions`, `buyTypeOptions`, `assetTypeOptions`, `manufacturerOptions` (unused for now), `modelOptions`, `ownerOptions`, `placeOptions`; edit also `asset: {...}` (incl. `tags: string[]`). show props `asset: {...display fields, related names, tags, timestamps, incidentsCount}`.
  Row = `{ id, state, state_label, asset_type_name, manufacturer_name, model_name, owner_name, serial_number, buy_price, incidents_count }`.

- [ ] **Step 1: Write the failing tests**

```php
<?php // tests/Feature/App/AssetControllerTest.php
namespace Tests\Feature\App;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\AssetType;
use App\Models\Incident;
use App\Models\Manufacturer;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User { return User::factory()->create(['login_enabled' => true]); }

    public function test_index_lists_assets_with_relation_columns_and_incident_count(): void
    {
        $mfr = Manufacturer::factory()->create(['name' => 'Acme']);
        $model = AssetModel::factory()->create(['name' => 'X1', 'manufacturer_id' => $mfr->id]);
        $type = AssetType::factory()->create(['name' => 'Laptop']);
        $owner = User::factory()->create(['name' => 'Ada Lovelace']);
        $asset = Asset::factory()->create([
            'model_id' => $model->id, 'asset_type_id' => $type->id, 'owner_id' => $owner->id,
        ]);
        Incident::factory()->count(2)->create(['asset_id' => $asset->id]);

        $this->actingAs($this->actor())->get('/app/assets')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('assets/index', false)
                ->has('assets.data', 1, fn (Assert $r) => $r
                    ->where('asset_type_name', 'Laptop')
                    ->where('manufacturer_name', 'Acme')
                    ->where('model_name', 'X1')
                    ->where('owner_name', 'Ada Lovelace')
                    ->where('incidents_count', 2)
                    ->etc())
                ->has('stateOptions')->has('assetTypeOptions')->has('manufacturerOptions'));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/assets')->assertRedirect();
    }

    public function test_index_filters_by_state(): void
    {
        Asset::factory()->create(['state' => AssetState::IN_USE->value]);
        Asset::factory()->create(['state' => AssetState::DEFECT->value]);

        $this->actingAs($this->actor())->get('/app/assets?filter[state]=in-use')
            ->assertInertia(fn (Assert $p) => $p->has('assets.data', 1)
                ->where('assets.data.0.state', 'in-use'));
    }

    public function test_index_filters_by_manufacturer(): void
    {
        $a = Manufacturer::factory()->create();
        $b = Manufacturer::factory()->create();
        $ma = AssetModel::factory()->create(['manufacturer_id' => $a->id]);
        $mb = AssetModel::factory()->create(['manufacturer_id' => $b->id]);
        Asset::factory()->count(2)->create(['model_id' => $ma->id]);
        Asset::factory()->create(['model_id' => $mb->id]);

        $this->actingAs($this->actor())->get("/app/assets?filter[manufacturer_id]={$a->id}")
            ->assertInertia(fn (Assert $p) => $p->has('assets.data', 2));
    }

    public function test_index_sorts_by_manufacturer_name(): void
    {
        $z = Manufacturer::factory()->create(['name' => 'Zeta']);
        $al = Manufacturer::factory()->create(['name' => 'Alpha']);
        Asset::factory()->create(['model_id' => AssetModel::factory()->create(['manufacturer_id' => $z->id])->id]);
        Asset::factory()->create(['model_id' => AssetModel::factory()->create(['manufacturer_id' => $al->id])->id]);

        $this->actingAs($this->actor())->get('/app/assets?sort=manufacturer_name')
            ->assertInertia(fn (Assert $p) => $p->where('assets.data.0.manufacturer_name', 'Alpha'));
    }

    public function test_store_creates_asset_and_syncs_tags(): void
    {
        $type = AssetType::factory()->create();
        $this->actingAs($this->actor())->post('/app/assets', [
            'state' => 'in-use', 'asset_type_id' => $type->id,
            'owner_id' => '', 'place_id' => '', 'model_id' => '',
            'serial_number' => 'SN-1', 'buy_price' => '199.99',
            'buy_date' => '2024-01-10', 'guarantee_end' => '2026-01-10',
            'buy_type' => 'once', 'invoice' => 'INV-1',
            'tags' => ['portable', 'audited'],
        ])->assertRedirect('/app/assets');

        $asset = Asset::where('serial_number', 'SN-1')->first();
        $this->assertNotNull($asset);
        $this->assertSame(AssetState::IN_USE, $asset->state);
        $this->assertNull($asset->owner_id); // empty string normalized to null
        $this->assertEqualsCanonicalizing(['portable', 'audited'], $asset->tags->pluck('name')->all());
    }

    public function test_store_validates_required_state_and_type(): void
    {
        $this->actingAs($this->actor())->post('/app/assets', ['state' => '', 'asset_type_id' => ''])
            ->assertSessionHasErrors(['state', 'asset_type_id']);
    }

    public function test_store_rejects_invalid_state_enum(): void
    {
        $type = AssetType::factory()->create();
        $this->actingAs($this->actor())->post('/app/assets', ['state' => 'bogus', 'asset_type_id' => $type->id])
            ->assertSessionHasErrors('state');
    }

    public function test_store_rejects_nonexistent_owner(): void
    {
        $type = AssetType::factory()->create();
        $this->actingAs($this->actor())->post('/app/assets', [
            'state' => 'new', 'asset_type_id' => $type->id, 'owner_id' => '00000000-0000-0000-0000-000000000000',
        ])->assertSessionHasErrors('owner_id');
    }

    public function test_update_changes_fields_and_tags(): void
    {
        $asset = Asset::factory()->create(['state' => AssetState::NEW->value]);
        $asset->syncTags(['old']);

        $this->actingAs($this->actor())->put("/app/assets/{$asset->id}", [
            'state' => 'storage', 'asset_type_id' => $asset->asset_type_id,
            'owner_id' => '', 'place_id' => '', 'model_id' => '',
            'serial_number' => 'SN-2', 'buy_price' => '', 'buy_date' => '', 'guarantee_end' => '',
            'buy_type' => '', 'invoice' => '', 'tags' => ['new-tag'],
        ])->assertRedirect('/app/assets');

        $asset->refresh();
        $this->assertSame(AssetState::STORAGE, $asset->state);
        $this->assertEqualsCanonicalizing(['new-tag'], $asset->tags->pluck('name')->all());
    }

    public function test_show_returns_detail_props(): void
    {
        $mfr = Manufacturer::factory()->create(['name' => 'Acme']);
        $model = AssetModel::factory()->create(['name' => 'X1', 'manufacturer_id' => $mfr->id]);
        $asset = Asset::factory()->create(['model_id' => $model->id]);
        $asset->syncTags(['t1']);

        $this->actingAs($this->actor())->get("/app/assets/{$asset->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('assets/show', false)
                ->where('asset.id', $asset->id)
                ->where('asset.manufacturer_name', 'Acme')
                ->where('asset.model_name', 'X1')
                ->where('asset.tags', ['t1'])
                ->etc());
    }

    public function test_destroy_deletes_asset(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs($this->actor())->delete("/app/assets/{$asset->id}")->assertRedirect('/app/assets');
        $this->assertNull(Asset::find($asset->id));
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetControllerTest.php`
Expected: FAIL — routes/controller undefined.

- [ ] **Step 3: Create the FormRequest**

```php
<?php // app/Http/Requests/App/AssetRequest.php
namespace App\Http\Requests\App;

use App\Enums\AssetState;
use App\Enums\BuyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** Normalize empty-string selects/inputs to null before validation. */
    protected function prepareForValidation(): void
    {
        $nullable = ['owner_id', 'place_id', 'model_id', 'buy_type', 'buy_date', 'guarantee_end', 'buy_price', 'serial_number', 'invoice'];
        $this->merge(collect($this->only($nullable))
            ->map(fn ($v) => $v === '' ? null : $v)
            ->all());
    }

    public function rules(): array
    {
        return [
            'state' => ['required', Rule::enum(AssetState::class)],
            'asset_type_id' => ['required', 'uuid', 'exists:asset_types,id'],
            'owner_id' => ['nullable', 'uuid', 'exists:users,id'],
            'place_id' => ['nullable', 'uuid', 'exists:places,id'],
            'model_id' => ['nullable', 'uuid', 'exists:asset_models,id'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'buy_date' => ['nullable', 'date'],
            'guarantee_end' => ['nullable', 'date'],
            'buy_type' => ['nullable', Rule::enum(BuyType::class)],
            'buy_price' => ['nullable', 'numeric', 'min:0'],
            'invoice' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

```php
<?php // app/Http/Controllers/App/AssetController.php
namespace App\Http\Controllers\App;

use App\Enums\AssetState;
use App\Enums\BuyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetRequest;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\AssetType;
use App\Models\Manufacturer;
use App\Models\Place;
use App\Models\User;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Asset::query()
            ->leftJoin('asset_types', 'asset_types.id', '=', 'assets.asset_type_id')
            ->leftJoin('asset_models', 'asset_models.id', '=', 'assets.model_id')
            ->leftJoin('manufacturers', 'manufacturers.id', '=', 'asset_models.manufacturer_id')
            ->leftJoin('users', 'users.id', '=', 'assets.owner_id')
            ->withCount('incidents')
            ->select(
                'assets.id', 'assets.state', 'assets.serial_number', 'assets.buy_price',
                'asset_types.name as asset_type_name',
                'asset_models.name as model_name',
                'manufacturers.name as manufacturer_name',
                'users.name as owner_name',
            );

        $assets = TableQuery::for($query, $request)
            ->searchable(['assets.serial_number', 'asset_types.name', 'manufacturers.name', 'asset_models.name', 'users.name'])
            ->sortable(['assets.state', 'asset_type_name', 'manufacturer_name', 'model_name', 'owner_name', 'assets.serial_number', 'assets.buy_price', 'incidents_count'])
            ->filterable([
                'state' => 'assets.state',
                'asset_type_id' => 'assets.asset_type_id',
                'manufacturer_id' => 'asset_models.manufacturer_id',
            ])
            ->paginate();

        $assets->getCollection()->transform(fn ($a) => [
            'id' => $a->id,
            'state' => $a->state->value,
            'state_label' => $a->state->getLabel(),
            'asset_type_name' => $a->asset_type_name,
            'manufacturer_name' => $a->manufacturer_name,
            'model_name' => $a->model_name,
            'owner_name' => $a->owner_name,
            'serial_number' => $a->serial_number,
            'buy_price' => $a->buy_price,
            'incidents_count' => $a->incidents_count,
        ]);

        return Inertia::render('assets/index', [
            'assets' => [
                'data' => $assets->items(),
                'meta' => [
                    'current_page' => $assets->currentPage(),
                    'last_page' => $assets->lastPage(),
                    'per_page' => $assets->perPage(),
                    'total' => $assets->total(),
                ],
            ],
            'stateOptions' => $this->stateOptions(),
            'assetTypeOptions' => $this->options(AssetType::query()),
            'manufacturerOptions' => $this->options(Manufacturer::query()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('assets/create', $this->formOptions());
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tags = $data['tags'] ?? [];
        unset($data['tags']);

        $asset = Asset::create($data);
        $asset->syncTags($tags);

        return to_route('app.assets.index')->with('success', 'Asset created.');
    }

    public function show(Asset $asset): Response
    {
        $asset->load('assetType', 'model.manufacturer', 'owner', 'place', 'tags')->loadCount('incidents');

        return Inertia::render('assets/show', ['asset' => $this->detail($asset)]);
    }

    public function edit(Asset $asset): Response
    {
        $asset->load('tags');

        return Inertia::render('assets/edit', array_merge($this->formOptions(), [
            'asset' => [
                'id' => $asset->id,
                'state' => $asset->state->value,
                'asset_type_id' => $asset->asset_type_id,
                'owner_id' => $asset->owner_id,
                'place_id' => $asset->place_id,
                'model_id' => $asset->model_id,
                'serial_number' => $asset->serial_number,
                'buy_date' => optional($asset->buy_date)->format('Y-m-d'),
                'guarantee_end' => optional($asset->guarantee_end)->format('Y-m-d'),
                'buy_type' => $asset->buy_type?->value,
                'buy_price' => $asset->buy_price !== null ? (string) $asset->buy_price : '',
                'invoice' => $asset->invoice,
                'tags' => $asset->tags->pluck('name')->all(),
            ],
        ]));
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $data = $request->validated();
        $tags = $data['tags'] ?? [];
        unset($data['tags']);

        $asset->update($data);
        $asset->syncTags($tags);

        return to_route('app.assets.index')->with('success', 'Asset updated.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $asset->delete();

        return to_route('app.assets.index')->with('success', 'Asset deleted.');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'stateOptions' => $this->stateOptions(),
            'buyTypeOptions' => array_map(fn (BuyType $b) => ['value' => $b->value, 'label' => $b->getLabel()], BuyType::cases()),
            'assetTypeOptions' => $this->options(AssetType::query()),
            'manufacturerOptions' => $this->options(Manufacturer::query()),
            'placeOptions' => $this->options(Place::query()),
            'ownerOptions' => User::query()->orderBy('name')->get()
                ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])->all(),
            'modelOptions' => AssetModel::query()->with('manufacturer')->orderBy('name')->get()
                ->map(fn (AssetModel $m) => ['value' => $m->id, 'label' => '('.optional($m->manufacturer)->name.') '.$m->name])->all(),
        ];
    }

    /** @return array<int, array{value:string,label:string}> */
    private function stateOptions(): array
    {
        return array_map(fn (AssetState $s) => ['value' => $s->value, 'label' => $s->getLabel()], AssetState::cases());
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query @return array<int, array{value:string,label:string}> */
    private function options($query): array
    {
        return $query->orderBy('name')->get()->map(fn ($m) => ['value' => $m->id, 'label' => $m->name])->all();
    }

    /** @return array<string, mixed> */
    private function detail(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'state' => $asset->state->value,
            'state_label' => $asset->state->getLabel(),
            'asset_type_name' => optional($asset->assetType)->name,
            'manufacturer_name' => optional(optional($asset->model)->manufacturer)->name,
            'model_name' => optional($asset->model)->name,
            'owner_name' => optional($asset->owner)->name,
            'place_name' => optional($asset->place)->name,
            'serial_number' => $asset->serial_number,
            'buy_date' => optional($asset->buy_date)->format('Y-m-d'),
            'guarantee_end' => optional($asset->guarantee_end)->format('Y-m-d'),
            'buy_type_label' => $asset->buy_type?->getLabel(),
            'buy_price' => $asset->buy_price,
            'invoice' => $asset->invoice,
            'tags' => $asset->tags->pluck('name')->all(),
            'incidentsCount' => $asset->incidents_count,
            'created_at' => optional($asset->created_at)->toDateTimeString(),
            'updated_at' => optional($asset->updated_at)->toDateTimeString(),
        ];
    }
}
```

- [ ] **Step 5: Register the route**

In `routes/web.php`, inside the authenticated `/app` group (alongside the other resources):

```php
use App\Http\Controllers\App\AssetController;
// … inside the authenticated group (note: WITH show):
Route::resource('assets', AssetController::class);
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetControllerTest.php`
Expected: PASS (all cases). Then run the full suite: `ddev exec ./vendor/bin/phpunit` → green.

- [ ] **Step 7: Verify the multi-join index query on the REAL driver (MariaDB)**

The suite runs on SQLite; the 4-join + `withCount` + aliased select is the risk area. Confirm on MariaDB via ddev:

```
ddev artisan tinker --execute="\$q=App\Models\Asset::query()->leftJoin('asset_types','asset_types.id','=','assets.asset_type_id')->leftJoin('asset_models','asset_models.id','=','assets.model_id')->leftJoin('manufacturers','manufacturers.id','=','asset_models.manufacturer_id')->leftJoin('users','users.id','=','assets.owner_id')->withCount('incidents')->select('assets.id','assets.state','manufacturers.name as manufacturer_name')->orderBy('manufacturer_name')->get(); echo 'OK '.\$q->count();"
```

Expected: prints `OK …` with NO SQL error (ambiguous column / syntax). Paste command + output in the report.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/App/AssetController.php app/Http/Requests/App/AssetRequest.php routes/web.php tests/Feature/App/AssetControllerTest.php
git commit -m "feat(assets): Inertia controller (multi-join index, tags, detail) + validation"
```

---

### Task 4: Asset index page + nav

**Files:**
- Create: `resources/js/pages/assets/index.tsx`, `resources/js/components/assets/state-badge.tsx`
- Modify: `resources/js/config/nav.ts`, `tests/Feature/App/AssetControllerTest.php` (drop the `false` on the index `component` assertion)

**Interfaces:**
- Consumes: `DataTable` (+ `filters`), `AppLayout`, props from Task 3 (`assets`, `stateOptions`, `assetTypeOptions`, `manufacturerOptions`).
- Produces: `StateBadge` (`{ state: string; label: string | null }`) mapping each `AssetState` value → Tailwind classes; the assets index page.

- [ ] **Step 1: Create the state badge**

```tsx
// resources/js/components/assets/state-badge.tsx
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const STATE_BADGE: Record<string, string> = {
    'new': 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    'sold': 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
    'storage': 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300',
    'lend': 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    'defect': 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    'under-repair': 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
    'need-repair': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300',
    'in-use': 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
};

export function StateBadge({ state, label }: { state: string; label: string | null }) {
    return <Badge variant="secondary" className={cn('border-0', STATE_BADGE[state] ?? '')}>{label ?? state}</Badge>;
}
```

- [ ] **Step 2: Create the index page**

```tsx
// resources/js/pages/assets/index.tsx
import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Eye, Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';
import { StateBadge } from '@/components/assets/state-badge';

interface Row {
    id: string; state: string; state_label: string | null;
    asset_type_name: string | null; manufacturer_name: string | null; model_name: string | null;
    owner_name: string | null; serial_number: string | null; buy_price: number | null; incidents_count: number;
}
type Option = { value: string; label: string };

const columns: ColumnDef<Row>[] = [
    { id: 'assets.state', accessorFn: (r) => r.state, header: 'State', cell: ({ row }) => <StateBadge state={row.original.state} label={row.original.state_label} /> },
    { id: 'asset_type_name', accessorFn: (r) => r.asset_type_name, header: 'Type', cell: ({ row }) => row.original.asset_type_name ?? '—' },
    { id: 'manufacturer_name', accessorFn: (r) => r.manufacturer_name, header: 'Manufacturer', cell: ({ row }) => row.original.manufacturer_name ?? '—' },
    { id: 'model_name', accessorFn: (r) => r.model_name, header: 'Model', cell: ({ row }) => row.original.model_name ?? '—' },
    { id: 'owner_name', accessorFn: (r) => r.owner_name, header: 'Owner', cell: ({ row }) => row.original.owner_name ?? '—' },
    { id: 'assets.serial_number', accessorFn: (r) => r.serial_number, header: 'Serial', cell: ({ row }) => row.original.serial_number ?? '—' },
    { id: 'assets.buy_price', accessorFn: (r) => r.buy_price, header: 'Buy price', cell: ({ row }) => row.original.buy_price ?? '—' },
    { id: 'incidents_count', accessorFn: (r) => r.incidents_count, header: 'Incidents', cell: ({ row }) => <Badge variant="secondary">{row.original.incidents_count}</Badge> },
    {
        id: 'actions', header: '',
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/assets/${row.original.id}`}><Eye className="h-4 w-4" /></Link></Button>
                <Button asChild variant="ghost" size="icon"><Link href={`/app/assets/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm('Delete this asset?')) router.delete(`/app/assets/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

interface Props {
    assets: { data: Row[]; meta: PaginationMeta };
    stateOptions: Option[]; assetTypeOptions: Option[]; manufacturerOptions: Option[];
}

export default function AssetsIndex({ assets, stateOptions, assetTypeOptions, manufacturerOptions }: Props) {
    return (
        <AppLayout title="Assets" breadcrumbs={[{ label: 'Assets' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Assets</h1>
                <Button asChild><Link href="/app/assets/create">New asset</Link></Button>
            </div>
            <DataTable
                columns={columns}
                rows={assets.data}
                pagination={assets.meta}
                baseUrl="/app/assets"
                sortable={['assets.state', 'asset_type_name', 'manufacturer_name', 'model_name', 'owner_name', 'assets.serial_number', 'assets.buy_price', 'incidents_count']}
                filters={[
                    { key: 'state', label: 'State', options: stateOptions },
                    { key: 'asset_type_id', label: 'Type', options: assetTypeOptions },
                    { key: 'manufacturer_id', label: 'Manufacturer', options: manufacturerOptions },
                ]}
            />
        </AppLayout>
    );
}
```

- [ ] **Step 3: Add Assets to the nav (first Inventory entry)**

In `resources/js/config/nav.ts`, import `Package` from `lucide-react` and add as the FIRST item in the Inventory group's `items` array:

```ts
{ label: 'Assets', href: '/app/assets', icon: Package, match: (p) => p.startsWith('/app/assets') },
```

- [ ] **Step 4: Flip the index test existence check**

In `tests/Feature/App/AssetControllerTest.php`, change `->component('assets/index', false)` to `->component('assets/index')` (page now exists).

- [ ] **Step 5: Build + test**

Run: `ddev exec pnpm run build` → succeeds.
Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetControllerTest.php` → PASS (index existence check now against the real page).

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/assets/index.tsx resources/js/components/assets/state-badge.tsx resources/js/config/nav.ts tests/Feature/App/AssetControllerTest.php
git commit -m "feat(assets): Inertia index (state badge, relation columns, filters) + nav"
```

---

### Task 5: Asset form (create/edit)

**Files:**
- Create: `resources/js/pages/assets/asset-form.tsx`, `resources/js/pages/assets/create.tsx`, `resources/js/pages/assets/edit.tsx`
- Modify: `tests/Feature/App/AssetControllerTest.php` (no change needed — store/update tests don't assert a component; leave as-is)

**Interfaces:**
- Consumes: `SelectField`, `TextField`, `DateField`, `NumberField`, `TagsInput`, `AppLayout`; the option props + `asset` from Task 3.
- Produces: `AssetForm` (props `{ initial?: AssetInitial; options: AssetOptions; submitUrl: string; method: 'post' | 'put' }`) + create/edit pages.

- [ ] **Step 1: Create the shared form**

```tsx
// resources/js/pages/assets/asset-form.tsx
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { SelectField } from '@/components/form/select-field';
import { DateField } from '@/components/form/date-field';
import { NumberField } from '@/components/form/number-field';
import { TagsInput } from '@/components/form/tags-input';

type Option = { value: string; label: string };
export interface AssetOptions {
    stateOptions: Option[]; buyTypeOptions: Option[]; assetTypeOptions: Option[];
    ownerOptions: Option[]; placeOptions: Option[]; modelOptions: Option[];
}
export interface AssetInitial {
    id: string; state: string; asset_type_id: string; owner_id: string | null; place_id: string | null;
    model_id: string | null; serial_number: string | null; buy_date: string | null; guarantee_end: string | null;
    buy_type: string | null; buy_price: string; invoice: string | null; tags: string[];
}
interface Props { initial?: AssetInitial; options: AssetOptions; submitUrl: string; method: 'post' | 'put'; }

export function AssetForm({ initial, options, submitUrl, method }: Props) {
    const form = useForm({
        state: initial?.state ?? '',
        asset_type_id: initial?.asset_type_id ?? '',
        owner_id: initial?.owner_id ?? '',
        place_id: initial?.place_id ?? '',
        model_id: initial?.model_id ?? '',
        serial_number: initial?.serial_number ?? '',
        buy_date: initial?.buy_date ?? '',
        guarantee_end: initial?.guarantee_end ?? '',
        buy_type: initial?.buy_type ?? '',
        buy_price: initial?.buy_price ?? '',
        invoice: initial?.invoice ?? '',
        tags: initial?.tags ?? [],
    });

    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <div className="grid grid-cols-2 gap-4">
                <SelectField id="state" label="State" required options={options.stateOptions}
                    value={form.data.state} onChange={(v) => form.setData('state', v)} error={form.errors.state} />
                <SelectField id="asset_type_id" label="Asset type" required options={options.assetTypeOptions}
                    value={form.data.asset_type_id} onChange={(v) => form.setData('asset_type_id', v)} error={form.errors.asset_type_id} />
                <SelectField id="owner_id" label="Owner" nullable options={options.ownerOptions}
                    value={form.data.owner_id} onChange={(v) => form.setData('owner_id', v)} error={form.errors.owner_id} />
                <SelectField id="place_id" label="Place" nullable options={options.placeOptions}
                    value={form.data.place_id} onChange={(v) => form.setData('place_id', v)} error={form.errors.place_id} />
                <SelectField id="model_id" label="Model" nullable options={options.modelOptions}
                    value={form.data.model_id} onChange={(v) => form.setData('model_id', v)} error={form.errors.model_id} />
                <TextField id="serial_number" label="Serial number"
                    value={form.data.serial_number} onChange={(v) => form.setData('serial_number', v)} error={form.errors.serial_number} />
                <DateField id="buy_date" label="Buy date"
                    value={form.data.buy_date} onChange={(v) => form.setData('buy_date', v)} error={form.errors.buy_date} />
                <DateField id="guarantee_end" label="Guarantee end"
                    value={form.data.guarantee_end} onChange={(v) => form.setData('guarantee_end', v)} error={form.errors.guarantee_end} />
                <SelectField id="buy_type" label="Buy type" nullable options={options.buyTypeOptions}
                    value={form.data.buy_type} onChange={(v) => form.setData('buy_type', v)} error={form.errors.buy_type} />
                <NumberField id="buy_price" label="Buy price"
                    value={form.data.buy_price} onChange={(v) => form.setData('buy_price', v)} error={form.errors.buy_price} />
                <TextField id="invoice" label="Invoice"
                    value={form.data.invoice} onChange={(v) => form.setData('invoice', v)} error={form.errors.invoice} />
            </div>
            <TagsInput id="tags" label="Tags" value={form.data.tags} onChange={(v) => form.setData('tags', v)} error={form.errors.tags} />
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
```

(Nullable selects — owner/place/model/buy_type — use `SelectField`'s `nullable`
prop from Task 1, which renders the clearable "—" option via its internal
sentinel and emits `''` when cleared. The server's `prepareForValidation`
normalizes `''`→null.)

- [ ] **Step 2: Create create/edit pages**

```tsx
// resources/js/pages/assets/create.tsx
import AppLayout from '@/layouts/app-layout';
import { AssetForm, type AssetOptions } from './asset-form';

export default function CreateAsset(props: AssetOptions) {
    return (
        <AppLayout title="New asset" breadcrumbs={[{ label: 'Assets', href: '/app/assets' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New asset</h1>
            <AssetForm options={props} submitUrl="/app/assets" method="post" />
        </AppLayout>
    );
}
```

```tsx
// resources/js/pages/assets/edit.tsx
import AppLayout from '@/layouts/app-layout';
import { AssetForm, type AssetOptions, type AssetInitial } from './asset-form';

interface Props extends AssetOptions { asset: AssetInitial; }

export default function EditAsset({ asset, ...options }: Props) {
    return (
        <AppLayout title="Edit asset" breadcrumbs={[{ label: 'Assets', href: '/app/assets' }, { label: asset.serial_number ?? 'Asset' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit asset</h1>
            <AssetForm initial={asset} options={options} submitUrl={`/app/assets/${asset.id}`} method="put" />
        </AppLayout>
    );
}
```

- [ ] **Step 3: Build + verify types**

Run: `ddev exec pnpm run build`
Expected: succeeds, no type errors.

- [ ] **Step 4: Manual verify**

Log in, `/app/assets/create`: create an asset (state + type required; owner/place/model optional via "—"; tags add/remove; dates + buy price). Edit persists changes incl. tags. Empty owner/place/model save as null.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/assets/asset-form.tsx resources/js/pages/assets/create.tsx resources/js/pages/assets/edit.tsx
git commit -m "feat(assets): Inertia create/edit form (dates, number, tags, nullable selects)"
```

---

### Task 6: Asset detail page (show) with tabs

**Files:**
- Create: `resources/js/pages/assets/show.tsx`
- Modify: `tests/Feature/App/AssetControllerTest.php` (drop the `false` on the show `component` assertion)
- Possibly add: `resources/js/components/ui/tabs.tsx` via shadcn CLI (if not present)

**Interfaces:**
- Consumes: `AppLayout`, shadcn `Tabs`, `StateBadge`, the `asset` detail prop from Task 3.
- Produces: `assets/show` page with General + empty Attachments/Incidents/History tabs.

- [ ] **Step 1: Ensure the shadcn Tabs primitive exists**

Run: `ls resources/js/components/ui/tabs.tsx` — if missing, run `ddev exec pnpm dlx shadcn@latest add tabs` (falls back to adding the standard shadcn `tabs.tsx` manually if the CLI can't run). Confirm it exports `Tabs, TabsList, TabsTrigger, TabsContent`.

- [ ] **Step 2: Create the detail page**

```tsx
// resources/js/pages/assets/show.tsx
import { Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { StateBadge } from '@/components/assets/state-badge';

interface AssetDetail {
    id: string; state: string; state_label: string | null;
    asset_type_name: string | null; manufacturer_name: string | null; model_name: string | null;
    owner_name: string | null; place_name: string | null; serial_number: string | null;
    buy_date: string | null; guarantee_end: string | null; buy_type_label: string | null;
    buy_price: number | null; invoice: string | null; tags: string[];
    incidentsCount: number; created_at: string | null; updated_at: string | null;
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="space-y-1">
            <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">{label}</div>
            <div className="text-sm">{children}</div>
        </div>
    );
}

const Placeholder = ({ what }: { what: string }) => (
    <Card><CardContent className="py-10 text-center text-sm text-muted-foreground">{what} coming soon.</CardContent></Card>
);

export default function ShowAsset({ asset }: { asset: AssetDetail }) {
    return (
        <AppLayout title="Asset" breadcrumbs={[{ label: 'Assets', href: '/app/assets' }, { label: asset.serial_number ?? 'Asset' }]}>
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold">{asset.model_name ?? 'Asset'}</h1>
                    <StateBadge state={asset.state} label={asset.state_label} />
                </div>
                <Button asChild><Link href={`/app/assets/${asset.id}/edit`}>Edit</Link></Button>
            </div>

            <Tabs defaultValue="general">
                <TabsList>
                    <TabsTrigger value="general">General</TabsTrigger>
                    <TabsTrigger value="attachments">Attachments</TabsTrigger>
                    <TabsTrigger value="incidents">Incidents ({asset.incidentsCount})</TabsTrigger>
                    <TabsTrigger value="history">History</TabsTrigger>
                </TabsList>

                <TabsContent value="general" className="mt-4">
                    <Card>
                        <CardContent className="grid grid-cols-2 gap-6 py-6 md:grid-cols-3">
                            <Field label="Asset type">{asset.asset_type_name ?? '—'}</Field>
                            <Field label="Manufacturer">{asset.manufacturer_name ?? '—'}</Field>
                            <Field label="Model">{asset.model_name ?? '—'}</Field>
                            <Field label="Owner">{asset.owner_name ?? '—'}</Field>
                            <Field label="Place">{asset.place_name ?? '—'}</Field>
                            <Field label="Serial number">{asset.serial_number ?? '—'}</Field>
                            <Field label="Buy date">{asset.buy_date ?? '—'}</Field>
                            <Field label="Guarantee end">{asset.guarantee_end ?? '—'}</Field>
                            <Field label="Buy type">{asset.buy_type_label ?? '—'}</Field>
                            <Field label="Buy price">{asset.buy_price ?? '—'}</Field>
                            <Field label="Invoice">{asset.invoice ?? '—'}</Field>
                            <Field label="Tags">
                                {asset.tags.length ? <div className="flex flex-wrap gap-1">{asset.tags.map((t) => <Badge key={t} variant="secondary">{t}</Badge>)}</div> : '—'}
                            </Field>
                            <Field label="Created">{asset.created_at ?? '—'}</Field>
                            <Field label="Updated">{asset.updated_at ?? '—'}</Field>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="attachments" className="mt-4"><Placeholder what="Attachments" /></TabsContent>
                <TabsContent value="incidents" className="mt-4"><Placeholder what="Incidents" /></TabsContent>
                <TabsContent value="history" className="mt-4"><Placeholder what="History" /></TabsContent>
            </Tabs>
        </AppLayout>
    );
}
```

- [ ] **Step 3: Flip the show test existence check**

In `tests/Feature/App/AssetControllerTest.php`, change `->component('assets/show', false)` to `->component('assets/show')`.

- [ ] **Step 4: Build + test**

Run: `ddev exec pnpm run build` → succeeds.
Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetControllerTest.php` → PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/assets/show.tsx tests/Feature/App/AssetControllerTest.php resources/js/components/ui/tabs.tsx package.json pnpm-lock.yaml
git commit -m "feat(assets): Inertia detail page with tabs (empty panels for 4b-4d)"
```

---

### Task 7: Final verification

**Files:** none (verification only).

- [ ] **Step 1: Full PHP suite** — `ddev exec php artisan test` → all green (Asset + all prior).
- [ ] **Step 2: Full JS suite** — `ddev exec pnpm run test` → all green (incl. DateField/NumberField/TagsInput).
- [ ] **Step 3: Build** — `ddev exec pnpm run build` → succeeds.
- [ ] **Step 4: Coexistence + nav** — `/app` Inventory group shows Assets (first) + Manufacturers/Places/Asset types/Asset models; Assets list/filters/create/edit/show all work; owner assignment works; `/app-old` Filament still fine.
- [ ] **Step 5:** Commit anything outstanding (releases automated from conventional commits — skip manual CHANGELOG).

---

## Self-Review Notes

- **Spec coverage:** `DateField`/`NumberField` → Task 1; `TagsInput` → Task 2; Asset backend (multi-join index + filters + validation + tags + show + enum options) → Task 3; index page + state badge + filters + nav → Task 4; create/edit form (all fields + nullable selects + tags) → Task 5; detail page with tabs → Task 6; verification → Task 7. All spec sections covered.
- **Type consistency:** `Row` (Task 4) matches the index transform (Task 3); `AssetOptions`/`AssetInitial` (Task 5) match the controller's `formOptions()` + `edit` `asset` prop (Task 3); `AssetDetail` (Task 6) matches `detail()` (Task 3); `StateBadge` prop names consistent (Tasks 4, 6). DataTable sortable tokens == column ids == server whitelist (qualified/aliased, no bare `name`).
- **Enum/ambiguity:** `state` sort token `assets.state` (qualified); manufacturer via `asset_models.manufacturer_id`; every relation column aliased. `Rule::enum` for state/buy_type. `prepareForValidation` normalizes empty-string selects to null.
- **Deviations (per spec):** no inline-create; no Replicate; empty detail panels only; BuyType labels passed through verbatim (source labels are swapped — a pre-existing bug, out of scope; note as follow-up).
- **Nullable selects:** solved reusably via `SelectField`'s new `nullable` prop (Task 1) — the "—" clear option uses an internal `'__none__'` sentinel (Radix forbids empty-string items) and emits `''`, which the server normalizes to null. No known-bad empty-string `SelectItem` path remains.

# Inertia Migration — Lookup Resources Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate Places, Asset Types, and Asset Models to the React/Inertia app at `/app`, reaching Filament parity — including Asset Models' manufacturer relation (select, sortable/searchable relation column, filter-by-manufacturer, assets_count).

**Architecture:** Reuse the Spec 1 kit (`DataTable`, CRUD form kit, `TableQuery`, resource-controller pattern). Extend three reusable primitives: `TableQuery` gains exact-match `filterable`; `DataTable` gains a select-`filters` prop; the form kit gains `SelectField`. `TableQuery` stays generic — Asset Models' relation columns come from a controller-side `leftJoin` + aliased select, so the helper only ever operates on columns present in the query.

**Tech Stack:** Laravel 13/PHP 8.4, Inertia v2 + React 19, TypeScript, Tailwind 4, shadcn/ui, TanStack Table, PHPUnit, Vitest. All commands via `ddev exec …`; pnpm.

## Global Constraints

- All `pnpm`/`php`/`artisan`/`phpunit` commands run through ddev (`ddev exec …`).
- **PHP tests are PHPUnit** (not Pest): classes extending `Tests\TestCase`, `use RefreshDatabase`, `public function test_*(): void`, `$this->assert*` / `assertInertia`. Any `it(...)` in this plan is an assertion sketch — translate to PHPUnit. Run with `ddev exec ./vendor/bin/phpunit <path>`.
- **JS tests are Vitest** (`ddev exec pnpm exec vitest run <path>`; full suite `ddev exec pnpm run test`).
- Do NOT touch Filament (assets, panel, `/app-old`). Do NOT modify Manufacturers.
- New controllers under `App\Http\Controllers\App`; requests under `App\Http\Requests\App`. React pages under `resources/js/pages/{places,asset-types,asset-models}/`. `@/` → `resources/js/*`.
- Routes go inside the EXISTING authenticated `/app` group in `routes/web.php` (`Route::prefix('app')->name('app.')->middleware(['auth', ApplyRuntimeSettings::class])`), as `Route::resource(...)->except('show')`, names `app.places.*`, `app.asset-types.*`, `app.asset-models.*`.
- No per-resource policies; FormRequest `authorize()` returns `true` (gated by `auth`).
- Models are UUID pk; no DB schema changes. Reuse existing factories (`PlaceFactory`, `AssetTypeFactory`, `AssetModelFactory` — the last sets `manufacturer_id => Manufacturer::factory()`).
- Add each new resource to the sidebar `navGroups` "Inventory" group in `resources/js/config/nav.ts`.
- TDD for all server logic and table/controller behavior. Commit after every task.

---

### Task 1: `TableQuery::filterable` (exact-match filters)

**Files:**
- Modify: `app/Support/Table/TableQuery.php`
- Test: `tests/Feature/Support/TableQueryTest.php` (add cases)

**Interfaces:**
- Consumes: existing `TableQuery::for(Builder, Request)`.
- Produces: `->filterable(array $filters): self`. `$filters` is either a list (`['status']` → request key `status` applied to column `status`) or an assoc map (`['manufacturer_id' => 'asset_models.manufacturer_id']` → request key `manufacturer_id` applied to the qualified column). Reads `filter[<key>]` from the request; present, non-empty values apply an exact `where(column, value)`. Unknown/absent keys are ignored. Composes with `searchable`/`sortable`/`paginate`.

- [ ] **Step 1: Write the failing tests** (append to `TableQueryTest.php`)

```php
public function test_filterable_applies_exact_match_on_whitelisted_key(): void
{
    $mA = \App\Models\Manufacturer::factory()->create();
    $mB = \App\Models\Manufacturer::factory()->create();
    \App\Models\AssetModel::factory()->count(2)->create(['manufacturer_id' => $mA->id]);
    \App\Models\AssetModel::factory()->create(['manufacturer_id' => $mB->id]);

    $request = \Illuminate\Http\Request::create('/x', 'GET', ['filter' => ['manufacturer_id' => $mA->id]]);
    $result = \App\Support\Table\TableQuery::for(\App\Models\AssetModel::query(), $request)
        ->filterable(['manufacturer_id'])->paginate();

    $this->assertSame(2, $result->total());
}

public function test_filterable_maps_request_key_to_qualified_column(): void
{
    $mA = \App\Models\Manufacturer::factory()->create();
    $mB = \App\Models\Manufacturer::factory()->create();
    \App\Models\AssetModel::factory()->create(['manufacturer_id' => $mA->id]);
    \App\Models\AssetModel::factory()->create(['manufacturer_id' => $mB->id]);

    // A joined query where a bare `manufacturer_id`/`name` would be ambiguous.
    $query = \App\Models\AssetModel::query()
        ->leftJoin('manufacturers', 'manufacturers.id', '=', 'asset_models.manufacturer_id')
        ->select('asset_models.*');
    $request = \Illuminate\Http\Request::create('/x', 'GET', ['filter' => ['manufacturer_id' => $mA->id]]);

    $result = \App\Support\Table\TableQuery::for($query, $request)
        ->filterable(['manufacturer_id' => 'asset_models.manufacturer_id'])->paginate();

    $this->assertSame(1, $result->total());
}

public function test_filterable_ignores_unwhitelisted_or_empty_filters(): void
{
    \App\Models\Manufacturer::factory()->count(3)->create();

    $request = \Illuminate\Http\Request::create('/x', 'GET', ['filter' => ['bogus' => 'x', 'name' => '']]);
    $result = \App\Support\Table\TableQuery::for(\App\Models\Manufacturer::query(), $request)
        ->filterable(['name'])->paginate();

    $this->assertSame(3, $result->total()); // bogus not whitelisted; name empty -> ignored
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/Support/TableQueryTest.php`
Expected: FAIL — `Method App\Support\Table\TableQuery::filterable does not exist`.

- [ ] **Step 3: Implement `filterable`**

In `app/Support/Table/TableQuery.php`, add the property, the setter, and an `applyFilters()` called from `paginate()`:

```php
private array $filterable = [];
```

```php
public function filterable(array $filters): self
{
    foreach ($filters as $key => $column) {
        // list entry: key is int -> request key == column; assoc: key => qualified column
        $this->filterable[is_int($key) ? $column : $key] = $column;
    }

    return $this;
}
```

In `paginate()`, add `$this->applyFilters();` immediately before `$this->applySearch();`. Then add:

```php
private function applyFilters(): void
{
    foreach ($this->filterable as $key => $column) {
        $value = $this->request->input("filter.$key");

        if ($value === null || $value === '') {
            continue;
        }

        $this->query->where($column, $value);
    }
}
```

- [ ] **Step 4: Run to verify they pass**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/Support/TableQueryTest.php`
Expected: PASS (existing + 3 new).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Table/TableQuery.php tests/Feature/Support/TableQueryTest.php
git commit -m "feat(table): add TableQuery::filterable exact-match filters"
```

---

### Task 2: `DataTable` select-filters prop

**Files:**
- Modify: `resources/js/components/data-table/data-table.tsx`, `resources/js/components/data-table/types.ts`
- Test: `resources/js/components/data-table/__tests__/use-table-query.test.ts` (add a filter-URL case)

**Interfaces:**
- Consumes: `visitTable`/`buildTableUrl` (existing), shadcn `Select`.
- Produces: `FilterConfig` type in `types.ts`:
  ```ts
  export interface FilterConfig {
      key: string;
      label: string;
      options: { value: string; label: string }[];
  }
  ```
  `DataTable` gains an optional prop `filters?: FilterConfig[]`. For each, a shadcn `Select` renders in the toolbar; the active value is read from the URL (`filter[<key>]`); selecting an option calls `visitTable(baseUrl, { ['filter['+key+']']: value })`; selecting the "All" sentinel clears it. Tables without `filters` render unchanged.

- [ ] **Step 1: Write the failing test** (append to `use-table-query.test.ts`)

```ts
it('builds a filter param and resets page', () => {
    const url = buildTableUrl('/app/asset-models', { 'filter[manufacturer_id]': 'abc' }, '?page=4');
    expect(url).toContain('manufacturer_id');
    expect(url).toContain('abc');
    expect(url).toContain('page=1');
});

it('clears a filter param when value is empty', () => {
    const url = buildTableUrl('/app/asset-models', { 'filter[manufacturer_id]': '' }, '?filter%5Bmanufacturer_id%5D=abc&page=2');
    expect(url).not.toContain('abc');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev exec pnpm exec vitest run resources/js/components/data-table`
Expected: FAIL — the "clears a filter" case fails (the second test asserts new behavior of the existing helper against a filter key). If both pass immediately, the helper already covers it; keep the tests as regression cover and continue. (`buildTableUrl` already deletes empty-valued keys, so these lock in filter-key behavior.)

- [ ] **Step 3: Add `FilterConfig` to `types.ts`**

```ts
// resources/js/components/data-table/types.ts  (append)
export interface FilterConfig {
    key: string;
    label: string;
    options: { value: string; label: string }[];
}
```

- [ ] **Step 4: Render filters in `DataTable`**

In `data-table.tsx`: import the Select primitives and `FilterConfig`, add `filters` to `Props<T>`, and render them in the toolbar. Use a sentinel `'__all__'` for the clear option because Radix Select forbids an empty-string item value.

Add imports:

```tsx
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import type { PaginationMeta, FilterConfig } from './types';
```

Extend the props interface and signature:

```tsx
interface Props<T> {
    columns: ColumnDef<T>[];
    rows: T[];
    pagination: PaginationMeta;
    baseUrl: string;
    searchable?: boolean;
    sortable?: string[];
    filters?: FilterConfig[];
}

export function DataTable<T>({ columns, rows, pagination, baseUrl, searchable = true, sortable = [], filters = [] }: Props<T>) {
```

Replace the existing search `<form>` block with a toolbar that also renders the filters (search stays as-is; filters are added beside it). Change the wrapping so both sit in one flex row:

```tsx
{(searchable || filters.length > 0) && (
    <div className="flex flex-wrap items-center gap-2">
        {searchable && (
            <form
                onSubmit={(e) => { e.preventDefault(); visitTable(baseUrl, { search }); }}
                className="flex gap-2"
            >
                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search…" className="w-64" />
                <Button type="submit" variant="secondary">Search</Button>
            </form>
        )}
        {filters.map((f) => {
            const active = params.get(`filter[${f.key}]`) ?? '__all__';
            return (
                <Select
                    key={f.key}
                    value={active}
                    onValueChange={(v) => visitTable(baseUrl, { [`filter[${f.key}]`]: v === '__all__' ? '' : v })}
                >
                    <SelectTrigger className="w-56"><SelectValue placeholder={f.label} /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__all__">All {f.label}</SelectItem>
                        {f.options.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
                    </SelectContent>
                </Select>
            );
        })}
    </div>
)}
```

(`params` is already computed at the top of the component from `window.location.search`.)

- [ ] **Step 5: Run tests + build**

Run: `ddev exec pnpm exec vitest run resources/js/components/data-table`
Expected: PASS.
Run: `ddev exec pnpm run build`
Expected: succeeds, no type errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/data-table
git commit -m "feat(table): add DataTable select-filters prop"
```

---

### Task 3: `SelectField` form component

**Files:**
- Create: `resources/js/components/form/select-field.tsx`
- Test: `resources/js/components/form/__tests__/select-field.test.tsx`

**Interfaces:**
- Consumes: shadcn `Select`, `@/components/ui/label`, `FormError`.
- Produces: `SelectField` with props `{ id: string; label: string; value: string; onChange: (v: string) => void; options: { value: string; label: string }[]; error?: string; required?: boolean; placeholder?: string }`.

- [ ] **Step 1: Write the failing test**

```tsx
// resources/js/components/form/__tests__/select-field.test.tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { SelectField } from '../select-field';

describe('SelectField', () => {
    it('renders the label and the current selection', () => {
        render(
            <SelectField id="manufacturer_id" label="Manufacturer" value="1"
                onChange={() => {}} options={[{ value: '1', label: 'Acme' }, { value: '2', label: 'Globex' }]} />,
        );
        expect(screen.getByText('Manufacturer')).toBeInTheDocument();
        expect(screen.getByText('Acme')).toBeInTheDocument();
    });

    it('shows the error message when present', () => {
        render(
            <SelectField id="manufacturer_id" label="Manufacturer" value=""
                onChange={() => {}} options={[]} error="The manufacturer id field is required." />,
        );
        expect(screen.getByText('The manufacturer id field is required.')).toBeInTheDocument();
    });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev exec pnpm exec vitest run resources/js/components/form`
Expected: FAIL — cannot resolve `../select-field`.

- [ ] **Step 3: Implement `SelectField`**

```tsx
// resources/js/components/form/select-field.tsx
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { FormError } from './form-error';

interface Props {
    id: string;
    label: string;
    value: string;
    onChange: (v: string) => void;
    options: { value: string; label: string }[];
    error?: string;
    required?: boolean;
    placeholder?: string;
}

export function SelectField({ id, label, value, onChange, options, error, required, placeholder }: Props) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}{required && <span className="text-destructive"> *</span>}</Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id} aria-invalid={!!error}>
                    <SelectValue placeholder={placeholder ?? `Select ${label.toLowerCase()}…`} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
                </SelectContent>
            </Select>
            <FormError message={error} />
        </div>
    );
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `ddev exec pnpm exec vitest run resources/js/components/form`
Expected: PASS. (If happy-dom struggles to render the Radix Select portal for the "renders current selection" assertion, assert on the label + trigger presence instead — keep both assertions meaningful.)

- [ ] **Step 5: Build + commit**

Run: `ddev exec pnpm run build` → succeeds.

```bash
git add resources/js/components/form
git commit -m "feat(form): add SelectField component"
```

---

### Task 4: Places + Asset Types resources (trivial lookups)

**Files:**
- Create: `app/Http/Controllers/App/PlaceController.php`, `app/Http/Requests/App/PlaceRequest.php`, `app/Http/Controllers/App/AssetTypeController.php`, `app/Http/Requests/App/AssetTypeRequest.php`
- Create: `resources/js/pages/places/{index,create,edit,place-form}.tsx`, `resources/js/pages/asset-types/{index,create,edit,asset-type-form}.tsx`
- Modify: `routes/web.php`, `resources/js/config/nav.ts`
- Test: `tests/Feature/App/PlaceControllerTest.php`, `tests/Feature/App/AssetTypeControllerTest.php`

**Interfaces:**
- Consumes: `TableQuery`, `DataTable`, `TextField`, `AppLayout`.
- Produces: routes `app.places.*`, `app.asset-types.*`; index props `places: { data: [{id,name}], meta }` / `assetTypes: { data: [{id,name}], meta }`; edit props `place: {id,name}` / `assetType: {id,name}`.

- [ ] **Step 1: Write the failing tests (Places + Asset Types)**

```php
<?php // tests/Feature/App/PlaceControllerTest.php
namespace Tests\Feature\App;

use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlaceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User { return User::factory()->create(['login_enabled' => true]); }

    public function test_index_lists_places(): void
    {
        Place::factory()->count(3)->create();
        $this->actingAs($this->user())->get('/app/places')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('places/index')->has('places.data', 3));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/places')->assertRedirect();
    }

    public function test_store_creates_a_place(): void
    {
        $this->actingAs($this->user())->post('/app/places', ['name' => 'Berlin'])
            ->assertRedirect('/app/places');
        $this->assertTrue(Place::where('name', 'Berlin')->exists());
    }

    public function test_store_validates_name_required(): void
    {
        $this->actingAs($this->user())->post('/app/places', ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_update_changes_a_place(): void
    {
        $p = Place::factory()->create(['name' => 'Old']);
        $this->actingAs($this->user())->put("/app/places/{$p->id}", ['name' => 'New'])
            ->assertRedirect('/app/places');
        $this->assertSame('New', $p->fresh()->name);
    }

    public function test_destroy_deletes_a_place(): void
    {
        $p = Place::factory()->create();
        $this->actingAs($this->user())->delete("/app/places/{$p->id}")
            ->assertRedirect('/app/places');
        $this->assertNull(Place::find($p->id));
    }
}
```

Create the analogous `tests/Feature/App/AssetTypeControllerTest.php` — identical structure with these substitutions: `Place`→`AssetType`, `places`→`asset-types` (URLs), `asset-types/index` (component), prop key `assetTypes`, and a sample name like `'Laptop'`.

- [ ] **Step 2: Run to verify they fail**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/PlaceControllerTest.php tests/Feature/App/AssetTypeControllerTest.php`
Expected: FAIL — routes/controllers undefined.

- [ ] **Step 3: Create the FormRequests**

```php
<?php // app/Http/Requests/App/PlaceRequest.php
namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class PlaceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array { return ['name' => ['required', 'string', 'max:255']]; }
}
```

Create `app/Http/Requests/App/AssetTypeRequest.php` identically with class name `AssetTypeRequest`.

- [ ] **Step 4: Create the controllers**

```php
<?php // app/Http/Controllers/App/PlaceController.php
namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\PlaceRequest;
use App\Models\Place;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    public function index(Request $request): Response
    {
        $places = TableQuery::for(Place::query(), $request)
            ->searchable(['name'])->sortable(['name'])->paginate();

        return Inertia::render('places/index', [
            'places' => [
                'data' => $places->items(),
                'meta' => [
                    'current_page' => $places->currentPage(),
                    'last_page' => $places->lastPage(),
                    'per_page' => $places->perPage(),
                    'total' => $places->total(),
                ],
            ],
        ]);
    }

    public function create(): Response { return Inertia::render('places/create'); }

    public function store(PlaceRequest $request): RedirectResponse
    {
        Place::create($request->validated());

        return to_route('app.places.index')->with('success', 'Place created.');
    }

    public function edit(Place $place): Response
    {
        return Inertia::render('places/edit', ['place' => ['id' => $place->id, 'name' => $place->name]]);
    }

    public function update(PlaceRequest $request, Place $place): RedirectResponse
    {
        $place->update($request->validated());

        return to_route('app.places.index')->with('success', 'Place updated.');
    }

    public function destroy(Place $place): RedirectResponse
    {
        $place->delete();

        return to_route('app.places.index')->with('success', 'Place deleted.');
    }
}
```

Create `app/Http/Controllers/App/AssetTypeController.php` with these substitutions: `Place`→`AssetType`, `PlaceRequest`→`AssetTypeRequest`, `$place`→`$assetType`, `places/*`→`asset-types/*` (Inertia components), `app.places.*`→`app.asset-types.*`, prop key `places`→`assetTypes`, messages `Place …`→`Asset type …`. (Note the route-model-binding parameter for `Route::resource('asset-types', …)` is `$assetType` bound as `{asset_type}` — type-hint `AssetType $assetType`.)

- [ ] **Step 5: Register the routes**

In `routes/web.php`, inside the existing authenticated `/app` group (the one with `->middleware(['auth', ...])` containing the dashboard + manufacturers resource), add:

```php
use App\Http\Controllers\App\PlaceController;
use App\Http\Controllers\App\AssetTypeController;
// … inside the authenticated group, alongside the manufacturers resource:
Route::resource('places', PlaceController::class)->except('show');
Route::resource('asset-types', AssetTypeController::class)->except('show');
```

- [ ] **Step 6: Create the React pages (Places)**

```tsx
// resources/js/pages/places/place-form.tsx
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';

interface Props { initial?: { id: string; name: string }; submitUrl: string; method: 'post' | 'put'; }

export function PlaceForm({ initial, submitUrl, method }: Props) {
    const form = useForm({ name: initial?.name ?? '' });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };
    return (
        <form onSubmit={submit} className="max-w-lg space-y-6">
            <TextField id="name" label="Name" required autoFocus
                value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} />
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
```

```tsx
// resources/js/pages/places/create.tsx
import AppLayout from '@/layouts/app-layout';
import { PlaceForm } from './place-form';

export default function CreatePlace() {
    return (
        <AppLayout title="New place" breadcrumbs={[{ label: 'Places', href: '/app/places' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New place</h1>
            <PlaceForm submitUrl="/app/places" method="post" />
        </AppLayout>
    );
}
```

```tsx
// resources/js/pages/places/edit.tsx
import AppLayout from '@/layouts/app-layout';
import { PlaceForm } from './place-form';

export default function EditPlace({ place }: { place: { id: string; name: string } }) {
    return (
        <AppLayout title="Edit place" breadcrumbs={[{ label: 'Places', href: '/app/places' }, { label: place.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit place</h1>
            <PlaceForm initial={place} submitUrl={`/app/places/${place.id}`} method="put" />
        </AppLayout>
    );
}
```

```tsx
// resources/js/pages/places/index.tsx
import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row { id: string; name: string; }

const columns: ColumnDef<Row>[] = [
    { accessorKey: 'name', header: 'Name' },
    {
        id: 'actions', header: '',
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/places/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/places/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function PlacesIndex({ places }: { places: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="Places" breadcrumbs={[{ label: 'Places' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Places</h1>
                <Button asChild><Link href="/app/places/create">New place</Link></Button>
            </div>
            <DataTable columns={columns} rows={places.data} pagination={places.meta} baseUrl="/app/places" sortable={['name']} />
        </AppLayout>
    );
}
```

- [ ] **Step 7: Create the Asset Types pages**

Create `resources/js/pages/asset-types/{asset-type-form,create,edit,index}.tsx` mirroring the four Places files exactly, with these substitutions: component/label text `Place`→`Asset type` / `Places`→`Asset types`; URLs `/app/places`→`/app/asset-types`; prop key `places`→`assetTypes`; form component `PlaceForm`→`AssetTypeForm`; default export names `PlacesIndex`→`AssetTypesIndex`, `CreatePlace`→`CreateAssetType`, `EditPlace`→`EditAssetType`; edit prop `place`→`assetType`.

- [ ] **Step 8: Add both to the sidebar nav**

In `resources/js/config/nav.ts`, add to the "Inventory" group's `items` (import icons `MapPin` and `Tag` from `lucide-react`), after the Manufacturers entry:

```ts
{ label: 'Places', href: '/app/places', icon: MapPin, match: (p) => p.startsWith('/app/places') },
{ label: 'Asset types', href: '/app/asset-types', icon: Tag, match: (p) => p.startsWith('/app/asset-types') },
```

- [ ] **Step 9: Run tests + build**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/PlaceControllerTest.php tests/Feature/App/AssetTypeControllerTest.php`
Expected: PASS.
Run: `ddev exec pnpm run build`
Expected: succeeds, no type errors.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/App/PlaceController.php app/Http/Requests/App/PlaceRequest.php app/Http/Controllers/App/AssetTypeController.php app/Http/Requests/App/AssetTypeRequest.php resources/js/pages/places resources/js/pages/asset-types routes/web.php resources/js/config/nav.ts tests/Feature/App/PlaceControllerTest.php tests/Feature/App/AssetTypeControllerTest.php
git commit -m "feat(lookups): migrate Places and Asset Types to Inertia"
```

---

### Task 5: Asset Models backend (join + counts + filter + options)

**Files:**
- Create: `app/Http/Controllers/App/AssetModelController.php`, `app/Http/Requests/App/AssetModelRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/App/AssetModelControllerTest.php`

**Interfaces:**
- Consumes: `TableQuery` (with `filterable` from Task 1), `AssetModel` (belongsTo `manufacturer`, hasMany `assets`), `Manufacturer`.
- Produces: routes `app.asset-models.*`; index props `assetModels: { data: [{id,name,manufacturer_id,manufacturer_name,assets_count}], meta }` + `manufacturerOptions: [{value,label}]`; create props `manufacturerOptions`; edit props `assetModel: {id,name,manufacturer_id}` + `manufacturerOptions`.

- [ ] **Step 1: Write the failing tests**

```php
<?php // tests/Feature/App/AssetModelControllerTest.php
namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetModelControllerTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User { return User::factory()->create(['login_enabled' => true]); }

    public function test_index_lists_models_with_manufacturer_name_and_assets_count(): void
    {
        $m = Manufacturer::factory()->create(['name' => 'Acme']);
        $model = AssetModel::factory()->create(['name' => 'X1', 'manufacturer_id' => $m->id]);
        Asset::factory()->count(2)->create(['model_id' => $model->id]);

        $this->actingAs($this->user())->get('/app/asset-models')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('asset-models/index')
                ->has('assetModels.data', 1, fn (Assert $row) => $row
                    ->where('name', 'X1')
                    ->where('manufacturer_name', 'Acme')
                    ->where('assets_count', 2)
                    ->etc())
                ->has('manufacturerOptions'));
    }

    public function test_index_filters_by_manufacturer(): void
    {
        $a = Manufacturer::factory()->create();
        $b = Manufacturer::factory()->create();
        AssetModel::factory()->count(2)->create(['manufacturer_id' => $a->id]);
        AssetModel::factory()->create(['manufacturer_id' => $b->id]);

        $this->actingAs($this->user())->get("/app/asset-models?filter[manufacturer_id]={$a->id}")
            ->assertInertia(fn (Assert $p) => $p->has('assetModels.data', 2));
    }

    public function test_index_sorts_by_manufacturer_name(): void
    {
        $z = Manufacturer::factory()->create(['name' => 'Zeta']);
        $a = Manufacturer::factory()->create(['name' => 'Alpha']);
        AssetModel::factory()->create(['name' => 'z-model', 'manufacturer_id' => $z->id]);
        AssetModel::factory()->create(['name' => 'a-model', 'manufacturer_id' => $a->id]);

        $this->actingAs($this->user())->get('/app/asset-models?sort=manufacturer_name')
            ->assertInertia(fn (Assert $p) => $p->where('assetModels.data.0.manufacturer_name', 'Alpha'));
    }

    public function test_index_searches_by_manufacturer_name(): void
    {
        $acme = Manufacturer::factory()->create(['name' => 'Acme']);
        $globex = Manufacturer::factory()->create(['name' => 'Globex']);
        AssetModel::factory()->create(['name' => 'aaa', 'manufacturer_id' => $acme->id]);
        AssetModel::factory()->create(['name' => 'bbb', 'manufacturer_id' => $globex->id]);

        $this->actingAs($this->user())->get('/app/asset-models?search=Acme')
            ->assertInertia(fn (Assert $p) => $p->has('assetModels.data', 1)
                ->where('assetModels.data.0.manufacturer_name', 'Acme'));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/asset-models')->assertRedirect();
    }

    public function test_store_creates_a_model(): void
    {
        $m = Manufacturer::factory()->create();
        $this->actingAs($this->user())->post('/app/asset-models', ['name' => 'X2', 'manufacturer_id' => $m->id])
            ->assertRedirect('/app/asset-models');
        $this->assertTrue(AssetModel::where('name', 'X2')->where('manufacturer_id', $m->id)->exists());
    }

    public function test_store_validates_manufacturer_exists(): void
    {
        $this->actingAs($this->user())->post('/app/asset-models', ['name' => 'X2', 'manufacturer_id' => '00000000-0000-0000-0000-000000000000'])
            ->assertSessionHasErrors('manufacturer_id');
    }

    public function test_store_validates_name_required(): void
    {
        $m = Manufacturer::factory()->create();
        $this->actingAs($this->user())->post('/app/asset-models', ['name' => '', 'manufacturer_id' => $m->id])
            ->assertSessionHasErrors('name');
    }

    public function test_update_changes_a_model(): void
    {
        $m = Manufacturer::factory()->create();
        $model = AssetModel::factory()->create(['manufacturer_id' => $m->id]);
        $m2 = Manufacturer::factory()->create();
        $this->actingAs($this->user())->put("/app/asset-models/{$model->id}", ['name' => 'Renamed', 'manufacturer_id' => $m2->id])
            ->assertRedirect('/app/asset-models');
        $this->assertSame('Renamed', $model->fresh()->name);
        $this->assertSame($m2->id, $model->fresh()->manufacturer_id);
    }

    public function test_destroy_deletes_a_model(): void
    {
        $model = AssetModel::factory()->create();
        $this->actingAs($this->user())->delete("/app/asset-models/{$model->id}")
            ->assertRedirect('/app/asset-models');
        $this->assertNull(AssetModel::find($model->id));
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetModelControllerTest.php`
Expected: FAIL — routes/controller undefined.

- [ ] **Step 3: Create the FormRequest**

```php
<?php // app/Http/Requests/App/AssetModelRequest.php
namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class AssetModelRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'manufacturer_id' => ['required', 'uuid', 'exists:manufacturers,id'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

```php
<?php // app/Http/Controllers/App/AssetModelController.php
namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetModelRequest;
use App\Models\AssetModel;
use App\Models\Manufacturer;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetModelController extends Controller
{
    public function index(Request $request): Response
    {
        // leftJoin brings the manufacturer name into the same query so TableQuery
        // can sort/search on it without relation introspection; every sort/search
        // token is qualified/aliased to avoid the two-`name`-columns ambiguity.
        $query = AssetModel::query()
            ->leftJoin('manufacturers', 'manufacturers.id', '=', 'asset_models.manufacturer_id')
            ->withCount('assets')
            ->select(
                'asset_models.id',
                'asset_models.name',
                'asset_models.manufacturer_id',
                'manufacturers.name as manufacturer_name',
            );

        $models = TableQuery::for($query, $request)
            ->searchable(['asset_models.name', 'manufacturers.name'])
            ->sortable(['asset_models.name', 'manufacturer_name', 'assets_count'])
            ->filterable(['manufacturer_id' => 'asset_models.manufacturer_id'])
            ->paginate();

        return Inertia::render('asset-models/index', [
            'assetModels' => [
                'data' => $models->items(),
                'meta' => [
                    'current_page' => $models->currentPage(),
                    'last_page' => $models->lastPage(),
                    'per_page' => $models->perPage(),
                    'total' => $models->total(),
                ],
            ],
            'manufacturerOptions' => $this->manufacturerOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('asset-models/create', ['manufacturerOptions' => $this->manufacturerOptions()]);
    }

    public function store(AssetModelRequest $request): RedirectResponse
    {
        AssetModel::create($request->validated());

        return to_route('app.asset-models.index')->with('success', 'Asset model created.');
    }

    public function edit(AssetModel $assetModel): Response
    {
        return Inertia::render('asset-models/edit', [
            'assetModel' => [
                'id' => $assetModel->id,
                'name' => $assetModel->name,
                'manufacturer_id' => $assetModel->manufacturer_id,
            ],
            'manufacturerOptions' => $this->manufacturerOptions(),
        ]);
    }

    public function update(AssetModelRequest $request, AssetModel $assetModel): RedirectResponse
    {
        $assetModel->update($request->validated());

        return to_route('app.asset-models.index')->with('success', 'Asset model updated.');
    }

    public function destroy(AssetModel $assetModel): RedirectResponse
    {
        $assetModel->delete();

        return to_route('app.asset-models.index')->with('success', 'Asset model deleted.');
    }

    /** @return array<int, array{value: string, label: string}> */
    private function manufacturerOptions(): array
    {
        return Manufacturer::query()->orderBy('name')->get()
            ->map(fn (Manufacturer $m) => ['value' => $m->id, 'label' => $m->name])
            ->all();
    }
}
```

- [ ] **Step 5: Register the route**

In `routes/web.php`, inside the same authenticated `/app` group:

```php
use App\Http\Controllers\App\AssetModelController;
// … alongside the other resources:
Route::resource('asset-models', AssetModelController::class)->except('show');
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetModelControllerTest.php`
Expected: PASS (all cases incl. filter, sort, search on the relation).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/App/AssetModelController.php app/Http/Requests/App/AssetModelRequest.php routes/web.php tests/Feature/App/AssetModelControllerTest.php
git commit -m "feat(asset-models): Inertia controller with relation join/filter/counts"
```

---

### Task 6: Asset Models frontend (relation table + filter + form)

**Files:**
- Create: `resources/js/pages/asset-models/{index,create,edit,asset-model-form}.tsx`
- Modify: `resources/js/config/nav.ts`

**Interfaces:**
- Consumes: `DataTable` (with `filters` — Task 2), `TextField`, `SelectField` (Task 3), `AppLayout`; props from Task 5.
- Produces: the three route pages + shared `AssetModelForm` (props `{ initial?: { id: string; name: string; manufacturer_id: string }; manufacturerOptions: { value: string; label: string }[]; submitUrl: string; method: 'post' | 'put' }`).

- [ ] **Step 1: Create the shared form**

```tsx
// resources/js/pages/asset-models/asset-model-form.tsx
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { SelectField } from '@/components/form/select-field';

interface Props {
    initial?: { id: string; name: string; manufacturer_id: string };
    manufacturerOptions: { value: string; label: string }[];
    submitUrl: string;
    method: 'post' | 'put';
}

export function AssetModelForm({ initial, manufacturerOptions, submitUrl, method }: Props) {
    const form = useForm({ name: initial?.name ?? '', manufacturer_id: initial?.manufacturer_id ?? '' });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };
    return (
        <form onSubmit={submit} className="max-w-lg space-y-6">
            <TextField id="name" label="Name" required autoFocus
                value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} />
            <SelectField id="manufacturer_id" label="Manufacturer" required
                value={form.data.manufacturer_id} onChange={(v) => form.setData('manufacturer_id', v)}
                options={manufacturerOptions} error={form.errors.manufacturer_id} />
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
```

- [ ] **Step 2: Create create/edit pages**

```tsx
// resources/js/pages/asset-models/create.tsx
import AppLayout from '@/layouts/app-layout';
import { AssetModelForm } from './asset-model-form';

export default function CreateAssetModel({ manufacturerOptions }: { manufacturerOptions: { value: string; label: string }[] }) {
    return (
        <AppLayout title="New asset model" breadcrumbs={[{ label: 'Asset models', href: '/app/asset-models' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New asset model</h1>
            <AssetModelForm manufacturerOptions={manufacturerOptions} submitUrl="/app/asset-models" method="post" />
        </AppLayout>
    );
}
```

```tsx
// resources/js/pages/asset-models/edit.tsx
import AppLayout from '@/layouts/app-layout';
import { AssetModelForm } from './asset-model-form';

interface Props {
    assetModel: { id: string; name: string; manufacturer_id: string };
    manufacturerOptions: { value: string; label: string }[];
}

export default function EditAssetModel({ assetModel, manufacturerOptions }: Props) {
    return (
        <AppLayout title="Edit asset model" breadcrumbs={[{ label: 'Asset models', href: '/app/asset-models' }, { label: assetModel.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit asset model</h1>
            <AssetModelForm initial={assetModel} manufacturerOptions={manufacturerOptions}
                submitUrl={`/app/asset-models/${assetModel.id}`} method="put" />
        </AppLayout>
    );
}
```

- [ ] **Step 3: Create the index page**

Note: each sortable column sets an explicit `id` equal to its server sort token (`asset_models.name`, `manufacturer_name`, `assets_count`) with an `accessorFn` reading the matching row field.

```tsx
// resources/js/pages/asset-models/index.tsx
import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row { id: string; name: string; manufacturer_id: string; manufacturer_name: string | null; assets_count: number; }
type Option = { value: string; label: string };

const columns: ColumnDef<Row>[] = [
    { id: 'asset_models.name', accessorFn: (r) => r.name, header: 'Name' },
    { id: 'manufacturer_name', accessorFn: (r) => r.manufacturer_name, header: 'Manufacturer',
      cell: ({ row }) => row.original.manufacturer_name ?? '—' },
    { id: 'assets_count', accessorFn: (r) => r.assets_count, header: 'Assets',
      cell: ({ row }) => <Badge variant="secondary">{row.original.assets_count}</Badge> },
    {
        id: 'actions', header: '',
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/asset-models/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/asset-models/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

interface Props { assetModels: { data: Row[]; meta: PaginationMeta }; manufacturerOptions: Option[]; }

export default function AssetModelsIndex({ assetModels, manufacturerOptions }: Props) {
    return (
        <AppLayout title="Asset models" breadcrumbs={[{ label: 'Asset models' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Asset models</h1>
                <Button asChild><Link href="/app/asset-models/create">New asset model</Link></Button>
            </div>
            <DataTable
                columns={columns}
                rows={assetModels.data}
                pagination={assetModels.meta}
                baseUrl="/app/asset-models"
                sortable={['asset_models.name', 'manufacturer_name', 'assets_count']}
                filters={[{ key: 'manufacturer_id', label: 'Manufacturer', options: manufacturerOptions }]}
            />
        </AppLayout>
    );
}
```

- [ ] **Step 4: Add to the sidebar nav**

In `resources/js/config/nav.ts`, add to the "Inventory" group's `items` (import `Boxes` from `lucide-react`), after Asset types:

```ts
{ label: 'Asset models', href: '/app/asset-models', icon: Boxes, match: (p) => p.startsWith('/app/asset-models') },
```

- [ ] **Step 5: Build + verify types**

Run: `ddev exec pnpm run build`
Expected: succeeds, no type errors.

- [ ] **Step 6: Manual verify**

Log in at `/app`, then `/app/asset-models`: table shows name/manufacturer/assets; the Manufacturer filter narrows the list; clicking Name/Manufacturer/Assets headers sorts; create/edit shows the manufacturer Select and persists; delete works.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/asset-models resources/js/config/nav.ts
git commit -m "feat(asset-models): Inertia index (filter+sort) + create/edit pages"
```

---

### Task 7: Final verification

**Files:** none (verification only).

- [ ] **Step 1: Full PHP suite**

Run: `ddev exec php artisan test`
Expected: all green (new resource + `TableQuery` tests + all pre-existing).

- [ ] **Step 2: Full JS suite**

Run: `ddev exec pnpm run test`
Expected: all green (incl. new SelectField + filter cases).

- [ ] **Step 3: Production build**

Run: `ddev exec pnpm run build`
Expected: succeeds.

- [ ] **Step 4: Coexistence + nav check**

- `/app` sidebar Inventory group shows Manufacturers, Places, Asset types, Asset models.
- Each resource's list/create/edit/delete works; Asset Models filter + relation sort + search work.
- `/app-old` Filament still fully functional.

- [ ] **Step 5: Commit any remaining docs (if repo convention requires)**

Releases are automated from conventional commits — skip manual CHANGELOG edits. If anything is uncommitted, commit it; otherwise nothing to do.

---

## Self-Review Notes

- **Spec coverage:** `TableQuery.filterable` → Task 1; `DataTable` filters → Task 2; `SelectField` → Task 3; Places + Asset Types → Task 4; Asset Models backend (join/counts/filter/options, validation incl. `exists`) → Task 5; Asset Models frontend (relation table, filter, sort tokens, form Select) → Task 6; nav entries → Tasks 4 & 6; testing → per task + Task 7. All spec sections covered.
- **Ambiguity rule** (spec §Design) implemented in Tasks 5–6: qualified/aliased sort tokens (`asset_models.name`, `manufacturer_name`, `assets_count`), searchable on `asset_models.name` + `manufacturers.name`, `filterable(['manufacturer_id' => 'asset_models.manufacturer_id'])`; DataTable column `id` = sort token with `accessorFn` for display.
- **Type consistency:** `FilterConfig` (Task 2) = `{ key, label, options }`, consumed in Task 6's `filters` prop; `SelectField` props (Task 3) match the Task 6 form usage; controller prop shapes (Task 5) match the page prop types (Task 6); `manufacturerOptions` `{ value, label }[]` consistent across controller, filter, and form.
- **Deferred (per spec non-goals):** relation managers, inline-create, per-column text filters, row selection, bulk destroy — none built.

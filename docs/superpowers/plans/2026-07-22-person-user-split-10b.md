# Person / User Separation — 10b (People Directory UI) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A `/app/people` directory — list/create/edit/delete people and view a person's owned assets — backed by the `Person` model from 10a.

**Architecture:** A conventional resource controller (modeled on `ManufacturerController`) + a detail page listing owned assets; delete is blocked while a person owns assets. React pages mirror the existing lookup CRUD + the asset show page.

**Tech Stack:** Laravel 13/PHP 8.4, Inertia v2 + React 19 + TS, shadcn/ui, PHPUnit, Vitest. ddev; pnpm.

## Global Constraints

- ddev for all commands; **PHPUnit** (`./vendor/bin/phpunit`); **Vitest** (`pnpm exec vitest run`; if `pnpm` missing `ddev exec corepack enable`).
- `Person` (from 10a): `HasUuids`, `#[Fillable(['firstname','lastname','name','email'])]`, `assets(): hasMany(Asset,'owner_id')`. Do NOT change the Person model, migrations, or the already-Person-backed pickers.
- `name` is derived (`trim(firstname.' '.lastname)`), not a form field. Auth-only gate. Pint is a CI gate. `@/` → `resources/js/*`. Commit trailer:
  `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`

---

### Task 1: `PersonController` + `PersonRequest` + routes + tests

**Files:** create `app/Http/Controllers/App/PersonController.php`, `app/Http/Requests/App/PersonRequest.php`, placeholder `resources/js/pages/people/{index,create,edit,show}.tsx`; modify `routes/web.php`; test `tests/Feature/App/PersonControllerTest.php`.

- [ ] **Step 1: `PersonRequest`**

```php
<?php // app/Http/Requests/App/PersonRequest.php
namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class PersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
```

- [ ] **Step 2: `PersonController`**

```php
<?php // app/Http/Controllers/App/PersonController.php
namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\PersonRequest;
use App\Models\Asset;
use App\Models\Person;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function index(Request $request): Response
    {
        $people = TableQuery::for(Person::query()->withCount('assets'), $request)
            ->searchable(['name', 'firstname', 'lastname', 'email'])
            ->sortable(['name', 'email', 'assets_count'])
            ->paginate();

        $people->getCollection()->transform(fn (Person $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'firstname' => $p->firstname,
            'lastname' => $p->lastname,
            'email' => $p->email,
            'assets_count' => $p->assets_count,
        ]);

        return Inertia::render('people/index', [
            'people' => [
                'data' => $people->items(),
                'meta' => [
                    'current_page' => $people->currentPage(),
                    'last_page' => $people->lastPage(),
                    'per_page' => $people->perPage(),
                    'total' => $people->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('people/create');
    }

    public function store(PersonRequest $request): RedirectResponse
    {
        Person::create($this->withName($request->validated()));

        return to_route('app.people.index')->with('success', 'Person created.');
    }

    public function show(Person $person): Response
    {
        $person->loadCount('assets')->load('assets.model.manufacturer', 'assets.place');

        return Inertia::render('people/show', [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'firstname' => $person->firstname,
                'lastname' => $person->lastname,
                'email' => $person->email,
                'assets_count' => $person->assets_count,
            ],
            'assets' => $person->assets->map(fn (Asset $a) => [
                'id' => $a->id,
                'model_name' => optional($a->model)->name,
                'serial_number' => $a->serial_number,
                'state' => $a->state->value,
                'state_label' => $a->state->getLabel(),
                'place_name' => optional($a->place)->name,
            ])->all(),
        ]);
    }

    public function edit(Person $person): Response
    {
        return Inertia::render('people/edit', [
            'person' => [
                'id' => $person->id,
                'firstname' => $person->firstname,
                'lastname' => $person->lastname,
                'email' => $person->email,
            ],
        ]);
    }

    public function update(PersonRequest $request, Person $person): RedirectResponse
    {
        $person->update($this->withName($request->validated()));

        return to_route('app.people.index')->with('success', 'Person updated.');
    }

    public function destroy(Person $person): RedirectResponse
    {
        if ($person->assets()->exists()) {
            throw ValidationException::withMessages([
                'person' => 'Reassign this person\'s assets before deleting them.',
            ]);
        }

        $person->delete();

        return to_route('app.people.index')->with('success', 'Person deleted.');
    }

    /**
     * Derive the display name from first/last (name is not a form field).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withName(array $data): array
    {
        $data['name'] = trim(($data['firstname'] ?? '').' '.($data['lastname'] ?? ''));

        return $data;
    }
}
```

- [ ] **Step 3: Route** — in `routes/web.php`, inside the authenticated `/app` group, import `PersonController` and add (near the other resources):
```php
Route::resource('people', PersonController::class);
```

- [ ] **Step 4: Placeholder React pages** — create minimal `resources/js/pages/people/{index,create,edit,show}.tsx` so `assertInertia()->component('people/...')` resolves (Task 2 replaces them). Example for each (adjust the title/component name):
```tsx
import AppLayout from '@/layouts/app-layout';
export default function People() {
    return <AppLayout title="People" breadcrumbs={[{ label: 'People' }]}><h1 className="text-2xl font-semibold">People</h1></AppLayout>;
}
```

- [ ] **Step 5: Tests**

```php
<?php // tests/Feature/App/PersonControllerTest.php
namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_index_lists_people_with_asset_counts(): void
    {
        $p = Person::factory()->create();
        Asset::factory()->count(2)->create(['owner_id' => $p->id]);

        $this->actingAs($this->actor())->get('/app/people')
            ->assertOk()
            ->assertInertia(fn (Assert $a) => $a->component('people/index')
                ->has('people.data', 1)
                ->where('people.data.0.assets_count', 2)->etc());
    }

    public function test_store_derives_name(): void
    {
        $this->actingAs($this->actor())->post('/app/people', [
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@x.de',
        ])->assertRedirect();

        $this->assertDatabaseHas('people', ['name' => 'Ada Lovelace', 'email' => 'ada@x.de']);
    }

    public function test_store_validation(): void
    {
        $this->actingAs($this->actor())->post('/app/people', ['firstname' => '', 'lastname' => '', 'email' => 'nope'])
            ->assertSessionHasErrors(['firstname', 'lastname', 'email']);
    }

    public function test_update_derives_name(): void
    {
        $p = Person::factory()->create();
        $this->actingAs($this->actor())->put('/app/people/'.$p->id, [
            'firstname' => 'Grace', 'lastname' => 'Hopper',
        ])->assertRedirect();
        $this->assertSame('Grace Hopper', $p->refresh()->name);
    }

    public function test_destroy_blocked_when_owns_assets(): void
    {
        $p = Person::factory()->create();
        Asset::factory()->create(['owner_id' => $p->id]);

        $this->actingAs($this->actor())->delete('/app/people/'.$p->id)
            ->assertSessionHasErrors('person');
        $this->assertDatabaseHas('people', ['id' => $p->id]);
    }

    public function test_destroy_allowed_when_no_assets(): void
    {
        $p = Person::factory()->create();
        $this->actingAs($this->actor())->delete('/app/people/'.$p->id)->assertRedirect();
        $this->assertDatabaseMissing('people', ['id' => $p->id]);
    }

    public function test_show_lists_owned_assets(): void
    {
        $p = Person::factory()->create();
        Asset::factory()->count(3)->create(['owner_id' => $p->id]);

        $this->actingAs($this->actor())->get('/app/people/'.$p->id)
            ->assertInertia(fn (Assert $a) => $a->component('people/show')
                ->where('person.id', $p->id)->has('assets', 3)->etc());
    }

    public function test_requires_auth(): void
    {
        $this->get('/app/people')->assertRedirect();
    }
}
```

- [ ] **Step 6: Run + commit** — `ddev exec ./vendor/bin/phpunit tests/Feature/App/PersonControllerTest.php` → PASS; Pint the new PHP + routes; full suite green.
```bash
git add app/Http/Controllers/App/PersonController.php app/Http/Requests/App/PersonRequest.php routes/web.php resources/js/pages/people tests/Feature/App/PersonControllerTest.php
git commit -m "feat(people): PersonController + request + routes (people directory backend)"
```

---

### Task 2: React People pages + nav

**Files:** replace `resources/js/pages/people/{index,create,edit,show}.tsx`; create `resources/js/pages/people/person-form.tsx`; modify `resources/js/config/nav.ts`; test `resources/js/pages/people/__tests__/{index,show}.test.tsx` (+ a person-form test if convenient).

- [ ] **Step 1: `person-form.tsx`** (mirror `manufacturer-form`)

```tsx
// resources/js/pages/people/person-form.tsx
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';

interface Props {
    initial?: { id: string; firstname: string; lastname: string; email: string | null };
    submitUrl: string;
    method: 'post' | 'put';
}

export function PersonForm({ initial, submitUrl, method }: Props) {
    const form = useForm({
        firstname: initial?.firstname ?? '',
        lastname: initial?.lastname ?? '',
        email: initial?.email ?? '',
    });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };

    return (
        <form onSubmit={submit} className="max-w-lg space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField id="firstname" label="First name" required autoFocus value={form.data.firstname} onChange={(v) => form.setData('firstname', v)} error={form.errors.firstname} />
                <TextField id="lastname" label="Last name" required value={form.data.lastname} onChange={(v) => form.setData('lastname', v)} error={form.errors.lastname} />
            </div>
            <TextField id="email" label="Email" value={form.data.email} onChange={(v) => form.setData('email', v)} error={form.errors.email} />
            <div class="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
```
(Note: use `className`, not `class`, on the div — fix if copied.)

- [ ] **Step 2: `create.tsx` + `edit.tsx`**

```tsx
// resources/js/pages/people/create.tsx
import AppLayout from '@/layouts/app-layout';
import { PersonForm } from './person-form';

export default function CreatePerson() {
    return (
        <AppLayout title="New person" breadcrumbs={[{ label: 'People', href: '/app/people' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New person</h1>
            <PersonForm submitUrl="/app/people" method="post" />
        </AppLayout>
    );
}
```
```tsx
// resources/js/pages/people/edit.tsx
import AppLayout from '@/layouts/app-layout';
import { PersonForm } from './person-form';

interface Props { person: { id: string; firstname: string; lastname: string; email: string | null } }

export default function EditPerson({ person }: Props) {
    return (
        <AppLayout title="Edit person" breadcrumbs={[{ label: 'People', href: '/app/people' }, { label: 'Edit' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit person</h1>
            <PersonForm initial={person} submitUrl={`/app/people/${person.id}`} method="put" />
        </AppLayout>
    );
}
```

- [ ] **Step 3: `index.tsx`** (DataTable, clickable rows → detail)

```tsx
// resources/js/pages/people/index.tsx
import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Eye, Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row { id: string; name: string; firstname: string; lastname: string; email: string | null; assets_count: number }

const columns: ColumnDef<Row>[] = [
    { id: 'name', accessorFn: (r) => r.name, header: 'Name', cell: ({ row }) => row.original.name },
    { id: 'email', accessorFn: (r) => r.email, header: 'Email', cell: ({ row }) => row.original.email ?? '—' },
    { id: 'assets_count', accessorFn: (r) => r.assets_count, header: 'Assets', cell: ({ row }) => <Badge variant="secondary">{row.original.assets_count}</Badge> },
    {
        id: 'actions', header: '',
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/people/${row.original.id}`}><Eye className="h-4 w-4" /></Link></Button>
                <Button asChild variant="ghost" size="icon"><Link href={`/app/people/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm('Delete this person?')) router.delete(`/app/people/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function PeopleIndex({ people }: { people: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="People" breadcrumbs={[{ label: 'People' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">People</h1>
                <Button asChild><Link href="/app/people/create">New person</Link></Button>
            </div>
            <DataTable
                columns={columns}
                rows={people.data}
                pagination={people.meta}
                baseUrl="/app/people"
                sortable={['name', 'email', 'assets_count']}
                rowHref={(r) => `/app/people/${r.id}`}
            />
        </AppLayout>
    );
}
```

- [ ] **Step 4: `show.tsx`** (owned-assets table)

```tsx
// resources/js/pages/people/show.tsx
import { Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { StateBadge } from '@/components/assets/state-badge';

interface AssetRow { id: string; model_name: string | null; serial_number: string | null; state: string; state_label: string | null; place_name: string | null }
interface Props {
    person: { id: string; name: string; firstname: string; lastname: string; email: string | null; assets_count: number };
    assets: AssetRow[];
}

export default function PersonShow({ person, assets }: Props) {
    return (
        <AppLayout title={person.name} breadcrumbs={[{ label: 'People', href: '/app/people' }, { label: person.name }]}>
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">{person.name}</h1>
                    {person.email && <p className="text-sm text-muted-foreground">{person.email}</p>}
                </div>
                <div className="flex gap-2">
                    <Button asChild variant="outline"><Link href={`/app/people/${person.id}/edit`}>Edit</Link></Button>
                    <Button variant="outline" onClick={() => { if (confirm('Delete this person?')) router.delete(`/app/people/${person.id}`); }}>Delete</Button>
                </div>
            </div>

            <Card>
                <CardHeader className="pb-2"><CardTitle className="text-base">Owned assets ({person.assets_count})</CardTitle></CardHeader>
                <CardContent>
                    {assets.length === 0 ? (
                        <p className="text-sm text-muted-foreground">This person owns no assets.</p>
                    ) : (
                        <Table>
                            <TableHeader><TableRow><TableHead>Model</TableHead><TableHead>Serial</TableHead><TableHead>State</TableHead><TableHead>Place</TableHead></TableRow></TableHeader>
                            <TableBody>
                                {assets.map((a) => (
                                    <TableRow key={a.id} className="cursor-pointer" onClick={() => router.visit(`/app/assets/${a.id}`)}>
                                        <TableCell>{a.model_name ?? '—'}</TableCell>
                                        <TableCell>{a.serial_number ?? '—'}</TableCell>
                                        <TableCell><StateBadge state={a.state} label={a.state_label} /></TableCell>
                                        <TableCell>{a.place_name ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </AppLayout>
    );
}
```

- [ ] **Step 5: Nav** — in `resources/js/config/nav.ts`, import `Contact` from lucide-react and add to the **Inventory** group's `items` (after Assets):
```ts
{ label: 'People', href: '/app/people', icon: Contact, match: (p) => p.startsWith('/app/people') },
```

- [ ] **Step 6: Tests**

```tsx
// resources/js/pages/people/__tests__/index.test.tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({ Link: ({ children, href }: any) => <a href={href}>{children}</a>, router: { delete: vi.fn(), get: vi.fn(), visit: vi.fn() } }));

import PeopleIndex from '../index';

describe('PeopleIndex', () => {
    it('renders a person row with asset count', () => {
        render(<PeopleIndex people={{ data: [{ id: 'p1', name: 'Ada Lovelace', firstname: 'Ada', lastname: 'Lovelace', email: 'ada@x.de', assets_count: 3 }], meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 } as never }} />);
        expect(screen.getByText('Ada Lovelace')).toBeInTheDocument();
        expect(screen.getByText('3')).toBeInTheDocument();
    });
});
```
```tsx
// resources/js/pages/people/__tests__/show.test.tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({ Link: ({ children, href }: any) => <a href={href}>{children}</a>, router: { visit: vi.fn(), delete: vi.fn() } }));

import PersonShow from '../show';

const person = { id: 'p1', name: 'Ada Lovelace', firstname: 'Ada', lastname: 'Lovelace', email: 'ada@x.de', assets_count: 1 };

describe('PersonShow', () => {
    it('lists owned assets', () => {
        render(<PersonShow person={person} assets={[{ id: 'a1', model_name: 'X1', serial_number: 'SN1', state: 'in-use', state_label: 'In Benutzung', place_name: 'HQ' }]} />);
        expect(screen.getByText('X1')).toBeInTheDocument();
    });
    it('shows an empty state', () => {
        render(<PersonShow person={{ ...person, assets_count: 0 }} assets={[]} />);
        expect(screen.getByText(/owns no assets/i)).toBeInTheDocument();
    });
});
```

- [ ] **Step 7: Run + commit** — vitest files green; full JS suite + `pnpm run build` clean.
```bash
git add resources/js/pages/people resources/js/config/nav.ts
git commit -m "feat(people): people directory pages (index/create/edit/show) + nav"
```

---

### Task 3: Final verification

- [ ] `ddev exec ./vendor/bin/phpunit` → green.
- [ ] `ddev exec pnpm run test` → green.
- [ ] `ddev exec pnpm run build` → succeeds.
- [ ] `ddev exec ./vendor/bin/pint --test` → clean.
- [ ] Manual: `/app/people` lists people with asset counts; create/edit derive the name; a person detail shows owned assets (rows open the asset); deleting a person who owns assets is blocked with an error; the Inventory nav shows People.
- [ ] Commit anything outstanding.

---

## Self-Review Notes

- **Spec coverage:** controller/request/routes/tests → Task 1; pages/nav/vitest → Task 2; verification → Task 3.
- **Delete-block:** `destroy` throws a validation error when `assets()->exists()`; tested both blocked + allowed.
- **Name derivation:** `withName()` sets `name = trim(first.' '.last)` on store+update; tested.
- **Consistency:** `Row`/props shapes match the controller payloads; DataTable `rowHref`/clickable-rows reused; StateBadge for asset state; nav uses the existing `NavItem` shape with a `match` fn.
- **Deferred:** Users login-only UI + dropping `users` name cols + auth-prop → 10c.

# Inertia Migration — Dashboard (6a) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the placeholder `/app` dashboard with real stats (Assets count + warranty buckets) and three recent-activity mini-tables (latest documents, open incidents, warranty-expiring).

**Architecture:** A `DashboardController@index` replaces the route's inline closure and returns the stats + three limit-10 lists; the `dashboard.tsx` page renders stat cards + mini-table `Card`s. No charts, no new deps. Reproduces the five Filament dashboard widgets.

**Tech Stack:** Laravel 13/PHP 8.4, Inertia v2 + React 19, shadcn/ui, PHPUnit, Vitest. ddev; pnpm.

## Global Constraints

- ddev for all commands; **PHP tests are PHPUnit**; JS tests are **Vitest**.
- Do NOT touch Filament (`/app-old`) or its widgets. No DB/schema changes.
- New: `app/Http/Controllers/App/DashboardController.php`. Modify: `routes/web.php` (swap the `app.dashboard` closure), `resources/js/pages/dashboard.tsx`. `@/` → `resources/js/*`.
- Show a single **Assets** count stat card + three warranty cards (expired / ≤30 / ≤90); Licences and Total cards are dropped (no Licence model). Mini-tables limited to **10** rows.
- Warranty stat computation with `$today = now()->startOfDay()`: expired = `guarantee_end < today`; soon_30 = `today..+30`; soon_90 = `today..+90` (cumulative windows, matching the widget).
- Row links: documents → `route('attachments.open', $a)` (new tab); incidents/warranty → `/app/assets/{asset_id}`.
- `attachable_type` stores the FQCN (no morph map) → `class_basename()` gives e.g. "Asset".
- No policy (gate on `auth`). TDD for the controller; commit per task.

---

### Task 1: `DashboardController` + route swap

**Files:**
- Create: `app/Http/Controllers/App/DashboardController.php`
- Modify: `routes/web.php` (the authenticated `/app` group's `app.dashboard` route)
- Test: `tests/Feature/App/DashboardControllerTest.php`

**Interfaces:**
- Consumes: `Asset`, `Incident`, `Attachment`.
- Produces: route `app.dashboard` → `DashboardController@index`; Inertia `dashboard` props `{ stats:{assets:int}, warranty:{expired:int,soon_30:int,soon_90:int}, latestDocuments:[], openIncidents:[], warrantyExpiring:[] }` (row shapes per the spec).

- [ ] **Step 1: Write the failing tests**

```php
<?php // tests/Feature/App/DashboardControllerTest.php
namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User { return User::factory()->create(['login_enabled' => true]); }

    public function test_stats_and_warranty_buckets(): void
    {
        Asset::factory()->create(['guarantee_end' => now()->subDay()->format('Y-m-d')]);        // expired
        Asset::factory()->create(['guarantee_end' => now()->addDays(10)->format('Y-m-d')]);     // soon_30 + soon_90
        Asset::factory()->create(['guarantee_end' => now()->addDays(60)->format('Y-m-d')]);     // soon_90 only
        Asset::factory()->create(['guarantee_end' => now()->addDays(200)->format('Y-m-d')]);    // neither
        Asset::factory()->create(['guarantee_end' => null]);                                     // ignored

        $this->actingAs($this->actor())->get('/app')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('dashboard')
                ->where('stats.assets', 5)
                ->where('warranty.expired', 1)
                ->where('warranty.soon_30', 1)
                ->where('warranty.soon_90', 2)
                ->etc());
    }

    public function test_latest_documents_only_documents_newest_first_capped(): void
    {
        Attachment::factory()->count(12)->create(['type' => 'document']);
        Attachment::factory()->create(['type' => 'image', 'original_name' => 'pic.jpg']);

        $this->actingAs($this->actor())->get('/app')
            ->assertInertia(fn (Assert $p) => $p
                ->has('latestDocuments', 10) // capped
                ->has('latestDocuments.0', fn (Assert $r) => $r->has('id')->has('title')->has('attached_to')->has('url')->etc())
                ->etc());
    }

    public function test_open_incidents_excludes_closed_and_caps(): void
    {
        $asset = Asset::factory()->create();
        Incident::factory()->count(3)->create(['asset_id' => $asset->id, 'closed_date' => null]);
        Incident::factory()->create(['asset_id' => $asset->id, 'closed_date' => now()]);

        $this->actingAs($this->actor())->get('/app')
            ->assertInertia(fn (Assert $p) => $p->has('openIncidents', 3)
                ->has('openIncidents.0', fn (Assert $r) => $r->has('id')->has('title')->has('asset_url')->has('days_open')->etc())
                ->etc());
    }

    public function test_warranty_expiring_ordered_soonest_first(): void
    {
        Asset::factory()->create(['serial_number' => 'LATER', 'guarantee_end' => now()->addDays(50)->format('Y-m-d')]);
        Asset::factory()->create(['serial_number' => 'SOONER', 'guarantee_end' => now()->addDays(5)->format('Y-m-d')]);
        Asset::factory()->create(['guarantee_end' => null]); // excluded

        $this->actingAs($this->actor())->get('/app')
            ->assertInertia(fn (Assert $p) => $p->has('warrantyExpiring', 2)
                ->where('warrantyExpiring.0.serial', 'SOONER')->etc());
    }

    public function test_requires_auth(): void
    {
        $this->get('/app')->assertRedirect();
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/DashboardControllerTest.php`
Expected: FAIL — route still a closure (no props / controller undefined).

- [ ] **Step 3: Create the controller**

```php
<?php // app/Http/Controllers/App/DashboardController.php
namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Incident;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = Carbon::now()->startOfDay();

        return Inertia::render('dashboard', [
            'stats' => ['assets' => Asset::query()->count()],
            'warranty' => [
                'expired' => Asset::query()->whereNotNull('guarantee_end')->whereDate('guarantee_end', '<', $today)->count(),
                'soon_30' => Asset::query()->whereNotNull('guarantee_end')
                    ->whereDate('guarantee_end', '>=', $today)->whereDate('guarantee_end', '<=', $today->copy()->addDays(30))->count(),
                'soon_90' => Asset::query()->whereNotNull('guarantee_end')
                    ->whereDate('guarantee_end', '>=', $today)->whereDate('guarantee_end', '<=', $today->copy()->addDays(90))->count(),
            ],
            'latestDocuments' => Attachment::query()->where('type', 'document')->with('uploadedBy')->latest()->limit(10)->get()
                ->map(fn (Attachment $a) => [
                    'id' => $a->id,
                    'title' => $a->title ?: $a->original_name,
                    'category_label' => $a->category?->getLabel(),
                    'attached_to' => class_basename($a->attachable_type),
                    'uploaded_by' => optional($a->uploadedBy)->name,
                    'created_at' => optional($a->created_at)->format('d.m.Y H:i'),
                    'url' => route('attachments.open', $a->id),
                ])->all(),
            'openIncidents' => Incident::query()->whereNull('closed_date')->with('asset.model')->orderByDesc('open_date')->limit(10)->get()
                ->map(fn (Incident $i) => [
                    'id' => $i->id,
                    'title' => $i->title,
                    'model' => optional(optional($i->asset)->model)->name,
                    'serial' => optional($i->asset)->serial_number,
                    'open_date' => optional($i->open_date)->format('d.m.Y'),
                    'days_open' => $i->open_date ? (int) $i->open_date->diffInDays(Carbon::now()) : null,
                    'asset_url' => $i->asset_id ? "/app/assets/{$i->asset_id}" : null,
                ])->all(),
            'warrantyExpiring' => Asset::query()->whereNotNull('guarantee_end')->with('owner', 'model')->orderBy('guarantee_end')->limit(10)->get()
                ->map(fn (Asset $a) => [
                    'id' => $a->id,
                    'owner' => optional($a->owner)->name,
                    'model' => optional($a->model)->name,
                    'serial' => $a->serial_number,
                    'guarantee_end' => optional($a->guarantee_end)->format('d.m.Y'),
                    'days_left' => $a->guarantee_end ? (int) Carbon::now()->startOfDay()->diffInDays($a->guarantee_end, false) : null,
                    'asset_url' => "/app/assets/{$a->id}",
                ])->all(),
        ]);
    }
}
```

- [ ] **Step 4: Swap the route**

In `routes/web.php`, inside the authenticated `/app` group, replace
`Route::get('/', fn () => Inertia::render('dashboard'))->name('dashboard');`
with:

```php
Route::get('/', [\App\Http\Controllers\App\DashboardController::class, 'index'])->name('dashboard');
```

(Leave the unauthenticated top-level `Route::get('/', fn () => to_route('filament.app.pages.dashboard'));` — that's the Filament root redirect, out of the `/app` group.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/DashboardControllerTest.php`
Expected: PASS. Then full suite `ddev exec ./vendor/bin/phpunit` → green (the existing `InertiaSmokeTest` still asserts the `dashboard` component and stays green).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/App/DashboardController.php routes/web.php tests/Feature/App/DashboardControllerTest.php
git commit -m "feat(dashboard): DashboardController with stats + activity lists"
```

---

### Task 2: Dashboard page (stat cards + mini-tables)

**Files:**
- Modify: `resources/js/pages/dashboard.tsx`
- Test: `resources/js/pages/__tests__/dashboard.test.tsx`

**Interfaces:**
- Consumes: `AppLayout`, `Card`, `Table`, `Badge`; the props from Task 1.
- Produces: the real dashboard page.

- [ ] **Step 1: Write the failing test**

```tsx
// resources/js/pages/__tests__/dashboard.test.tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import Dashboard from '../dashboard';

const base = {
    stats: { assets: 42 },
    warranty: { expired: 3, soon_30: 5, soon_90: 9 },
    latestDocuments: [{ id: 'd1', title: 'Invoice', category_label: 'Rechnung', attached_to: 'Asset', uploaded_by: 'Ada', created_at: '01.01.2025 10:00', url: 'http://x/open/d1' }],
    openIncidents: [{ id: 1, title: 'Broken screen', model: 'X1', serial: 'SN1', open_date: '02.01.2025', days_open: 4, asset_url: '/app/assets/a1' }],
    warrantyExpiring: [{ id: 'a1', owner: 'Bob', model: 'X1', serial: 'SN1', guarantee_end: '10.02.2025', days_left: 20, asset_url: '/app/assets/a1' }],
};

describe('Dashboard', () => {
    it('renders the assets stat and warranty buckets', () => {
        render(<Dashboard {...base} />);
        expect(screen.getByText('42')).toBeInTheDocument();
        expect(screen.getByText('3')).toBeInTheDocument();
        expect(screen.getByText('5')).toBeInTheDocument();
        expect(screen.getByText('9')).toBeInTheDocument();
    });

    it('renders a row in each mini-table', () => {
        render(<Dashboard {...base} />);
        expect(screen.getByText('Invoice')).toBeInTheDocument();
        expect(screen.getByText('Broken screen')).toBeInTheDocument();
        expect(screen.getByText('Bob')).toBeInTheDocument();
    });

    it('shows empty states when lists are empty', () => {
        render(<Dashboard {...base} latestDocuments={[]} openIncidents={[]} warrantyExpiring={[]} />);
        expect(screen.getAllByText(/nothing|none|no /i).length).toBeGreaterThan(0);
    });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev exec pnpm exec vitest run resources/js/pages/__tests__/dashboard.test.tsx`
Expected: FAIL — the placeholder dashboard doesn't accept/render these props.

- [ ] **Step 3: Rebuild the dashboard page**

```tsx
// resources/js/pages/dashboard.tsx
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';

interface Doc { id: string; title: string; category_label: string | null; attached_to: string | null; uploaded_by: string | null; created_at: string | null; url: string; }
interface OpenIncident { id: number; title: string; model: string | null; serial: string | null; open_date: string | null; days_open: number | null; asset_url: string | null; }
interface Warranty { id: string; owner: string | null; model: string | null; serial: string | null; guarantee_end: string | null; days_left: number | null; asset_url: string | null; }

interface Props {
    stats: { assets: number };
    warranty: { expired: number; soon_30: number; soon_90: number };
    latestDocuments: Doc[];
    openIncidents: OpenIncident[];
    warrantyExpiring: Warranty[];
}

function StatCard({ label, value, tone }: { label: string; value: number; tone?: string }) {
    return (
        <Card>
            <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">{label}</CardTitle></CardHeader>
            <CardContent className={cn('text-3xl font-semibold', tone)}>{value}</CardContent>
        </Card>
    );
}

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <Card>
            <CardHeader className="pb-2"><CardTitle className="text-base">{title}</CardTitle></CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

export default function Dashboard({ stats, warranty, latestDocuments, openIncidents, warrantyExpiring }: Props) {
    return (
        <AppLayout title="Dashboard" breadcrumbs={[{ label: 'Dashboard' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Dashboard</h1>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Assets" value={stats.assets} />
                <StatCard label="Warranty expired" value={warranty.expired} tone="text-red-600 dark:text-red-400" />
                <StatCard label="Expiring ≤ 30 days" value={warranty.soon_30} tone="text-amber-600 dark:text-amber-400" />
                <StatCard label="Expiring ≤ 90 days" value={warranty.soon_90} tone="text-blue-600 dark:text-blue-400" />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Panel title="Latest documents">
                    {latestDocuments.length === 0 ? <p className="text-sm text-muted-foreground">No documents.</p> : (
                        <Table>
                            <TableHeader><TableRow><TableHead>Title</TableHead><TableHead>Category</TableHead><TableHead>Attached to</TableHead><TableHead>Uploaded by</TableHead></TableRow></TableHeader>
                            <TableBody>
                                {latestDocuments.map((d) => (
                                    <TableRow key={d.id}>
                                        <TableCell><a href={d.url} target="_blank" rel="noreferrer" className="hover:underline">{d.title}</a></TableCell>
                                        <TableCell>{d.category_label ?? '—'}</TableCell>
                                        <TableCell>{d.attached_to ?? '—'}</TableCell>
                                        <TableCell>{d.uploaded_by ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Panel>

                <Panel title="Open incidents">
                    {openIncidents.length === 0 ? <p className="text-sm text-muted-foreground">No open incidents.</p> : (
                        <Table>
                            <TableHeader><TableRow><TableHead>Title</TableHead><TableHead>Model</TableHead><TableHead>Serial</TableHead><TableHead>Days open</TableHead></TableRow></TableHeader>
                            <TableBody>
                                {openIncidents.map((i) => (
                                    <TableRow key={i.id}>
                                        <TableCell>{i.asset_url ? <a href={i.asset_url} className="hover:underline">{i.title}</a> : i.title}</TableCell>
                                        <TableCell>{i.model ?? '—'}</TableCell>
                                        <TableCell>{i.serial ?? '—'}</TableCell>
                                        <TableCell>{i.days_open ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Panel>

                <Panel title="Warranty expiring">
                    {warrantyExpiring.length === 0 ? <p className="text-sm text-muted-foreground">No upcoming expirations.</p> : (
                        <Table>
                            <TableHeader><TableRow><TableHead>Owner</TableHead><TableHead>Model</TableHead><TableHead>Guarantee end</TableHead><TableHead>Days left</TableHead></TableRow></TableHeader>
                            <TableBody>
                                {warrantyExpiring.map((w) => (
                                    <TableRow key={w.id}>
                                        <TableCell>{w.asset_url ? <a href={w.asset_url} className="hover:underline">{w.owner ?? '—'}</a> : (w.owner ?? '—')}</TableCell>
                                        <TableCell>{w.model ?? '—'}</TableCell>
                                        <TableCell>{w.guarantee_end ?? '—'}</TableCell>
                                        <TableCell className={cn(w.days_left !== null && w.days_left < 0 && 'text-red-600 dark:text-red-400')}>{w.days_left ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Panel>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 4: Run test + build**

Run: `ddev exec pnpm exec vitest run resources/js/pages/__tests__/dashboard.test.tsx`
Expected: PASS (3).
Run: `ddev exec pnpm run build` → succeeds.
Run: `ddev exec pnpm run test` → full JS suite green.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/dashboard.tsx resources/js/pages/__tests__/dashboard.test.tsx
git commit -m "feat(dashboard): real dashboard page (stat cards + activity mini-tables)"
```

---

### Task 3: Final verification

**Files:** none (verification only).

- [ ] **Step 1: Full PHP suite** — `ddev exec php artisan test` → green.
- [ ] **Step 2: Full JS suite** — `ddev exec pnpm run test` → green.
- [ ] **Step 3: Build** — `ddev exec pnpm run build` → succeeds.
- [ ] **Step 4: Manual verify** — `/app` shows the Assets count + warranty cards with real numbers and three populated mini-tables (or empty states); document rows open the file, incident/warranty rows go to the asset detail. `/app-old` Filament dashboard still works.
- [ ] **Step 5:** Commit anything outstanding (releases automated — skip manual CHANGELOG).

---

## Self-Review Notes

- **Spec coverage:** stats.assets + warranty buckets + three limit-10 lists → Task 1; Assets + 3 warranty cards + three mini-tables with row links + empty states → Task 2; tests → Task 1 (PHPUnit buckets/ordering/limit/shape) + Task 2 (Vitest) + Task 3.
- **Type consistency:** page prop interfaces (`Doc`/`OpenIncident`/`Warranty` + `stats`/`warranty`) match the controller's mapped row shapes field-for-field; `days_left` signed, `days_open` positive.
- **Route swap:** only the `/app` group's `app.dashboard` closure → controller; the top-level Filament root redirect is left untouched; `InertiaSmokeTest` (asserts the `dashboard` component) stays green.
- **Deferred (per spec):** charts, Licences/Total cards, warranty→report links (6b), the 8 reports (6b).

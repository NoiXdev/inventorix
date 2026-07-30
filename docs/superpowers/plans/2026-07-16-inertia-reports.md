# Inertia Migration — Reports (6b) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the 8 Filament "Evaluation" reports to the new `/app` (Inertia/React) as a parallel, framework-agnostic report backend + a filter/table/download UI, with identical query results and PDFs.

**Architecture:** A new `app/Reports/AbstractReport` base + `ReportRegistry` + `ReportController` reproduce the non-Filament half of `BaseReportPage`. Each of the 8 reports is a new class that **copies its Filament twin's `reportQuery()`/`reportColumns()`/`filterSummary()`/`pdfData()`/private helpers verbatim** and adds a serializable `filterConfig()`. React renders filters (form kit + a new `MultiSelectField`), a paginated/aggregated table, and PDF/CSV/XLSX downloads. Filament reports stay at `/app-old` until cutover.

**Tech Stack:** Laravel 13/PHP 8.4, Inertia v2 + React 19 + TS, shadcn/ui, dompdf, League\Csv, OpenSpout, PHPUnit, Vitest. ddev; pnpm.

## Global Constraints

- ddev for all commands; **PHP tests are PHPUnit** (`./vendor/bin/phpunit`); JS tests are **Vitest** (`pnpm exec vitest run`); if `pnpm` is missing run `ddev exec corepack enable` first.
- **Reuse verbatim, do NOT modify:** `app/Reports/ReportColumn.php`, `app/Services/ReportExportService.php`, `resources/views/pdf/reports/layout.blade.php`, `resources/views/pdf/reports/assets-per-employee.blade.php`, `lang/de/evaluation.php`, and everything under `app/Filament/` (`/app-old`). No DB/schema changes.
- Report content stays **German**, resolved server-side via `__('evaluation…')` and passed to React as strings.
- Filters are **URL-driven** (`?filters[key]=…`, arrays as `filters[key][]=…`); on-screen list reports **paginate** (per_page ∈ {25,50,100}, default 25); aggregation reports render all rows; a **totals row** shows only where `tableSummaries()` is non-empty. **PDF/CSV/XLSX always export the full unpaginated result.**
- Route names `app.reports.*`, in the authenticated `/app` group. `{report}` is a plain key string (no model binding). No policy (gate on `auth`).
- `@/` → `resources/js/*`. Commit per task with trailer:
  `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`

## Filter → `filterConfig()` translation (reference)

Each Filament filter widget maps to one `filterConfig()` entry (a plain array):
- `Select::make(k)->multiple()->options(assoc)` → `['key'=>k,'type'=>'multiselect','label'=>…,'options'=>[['value'=>…,'label'=>…], …]]`
- `Select::make(k)->options(assoc)` (single) → `['key'=>k,'type'=>'select','nullable'=>true,'label'=>…,'options'=>[…]]`; if it had `->default(x)->selectablePlaceholder(false)` use `'nullable'=>false,'default'=>x`.
- `DatePicker::make(k)` → `['key'=>k,'type'=>'date','label'=>…]`
- `TextInput::make(k)->numeric()->default(d)->minValue(0)` → `['key'=>k,'type'=>'number','default'=>d,'min'=>0,'label'=>…]`
- `Toggle::make(k)->default(false)` → `['key'=>k,'type'=>'toggle','default'=>false,'label'=>…]`

Option lists: `employees`→`User::query()->orderBy('name')->get()->map(fn($u)=>['value'=>(string)$u->id,'label'=>$u->name])->all()`; `places`→same with `Place`; `handover type`→`array_map(fn(HandoverType $t)=>['value'=>$t->value,'label'=>$t->getLabel()], HandoverType::cases())`; status/group_by options from the lang file keys.

---

### Task 1: Backend spine + first report (Inventory by location)

**Files:**
- Create: `app/Reports/AbstractReport.php`, `app/Reports/ReportRegistry.php`, `app/Reports/InventoryByLocationReport.php`, `app/Http/Controllers/App/ReportController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/App/ReportControllerTest.php`

**Interfaces:**
- Produces: `AbstractReport` (base contract below); `ReportRegistry::all()/find($key)`; routes `app.reports.index|show|pdf|export`; Inertia `reports/show` prop contract (below) consumed by Task 6.

- [ ] **Step 1: Write `AbstractReport`**

```php
<?php

namespace App\Reports;

use App\Services\ReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class AbstractReport
{
    /** @var array<string, mixed> */
    protected array $filters = [];

    abstract public static function reportKey(): string;

    abstract public static function reportLabel(): string;

    abstract public static function reportDescription(): string;

    /** A lucide-react icon name. */
    abstract public static function icon(): string;

    /** @return array<int, array<string, mixed>> */
    abstract public function filterConfig(): array;

    abstract public function reportQuery(): Builder;

    /** @return array<int, ReportColumn> */
    abstract public function reportColumns(): array;

    /** @return array<string, mixed> */
    public function defaultFilters(): array
    {
        return [];
    }

    /**
     * Merge incoming filters over defaults, dropping only null/empty-string scalars
     * (keeps '0' and empty arrays, so a numeric 0 or a cleared multiselect survive).
     *
     * @param  array<string, mixed>  $filters
     */
    public function setFilters(array $filters): static
    {
        $clean = array_filter($filters, static fn ($v): bool => $v !== null && $v !== '');
        $this->filters = array_merge($this->defaultFilters(), $clean);

        return $this;
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->filters;
    }

    public function filterSummary(): string
    {
        return '';
    }

    /** @return array<int, string> */
    public function tableSummaries(): array
    {
        return [];
    }

    public function isPaginated(): bool
    {
        return true;
    }

    /** @return array<int, array{key: string, label: string}> */
    public function columnMeta(): array
    {
        return array_map(
            static fn (ReportColumn $c): array => ['key' => $c->key, 'label' => $c->label],
            $this->reportColumns(),
        );
    }

    /** @return array<int, string> */
    public function reportHeadings(): array
    {
        return array_map(static fn (ReportColumn $c): string => $c->label, $this->reportColumns());
    }

    /** @return array<int, array<int, mixed>> */
    public function reportRows(): array
    {
        $columns = $this->reportColumns();

        return $this->reportQuery()->get()
            ->map(fn (Model $record): array => array_map(
                static fn (ReportColumn $c) => $c->resolve($record),
                $columns,
            ))
            ->all();
    }

    /** Paginated positional rows for the on-screen list table. */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        $columns = $this->reportColumns();
        $paginator = $this->reportQuery()->paginate($perPage)->withQueryString();
        $paginator->getCollection()->transform(fn (Model $record): array => array_map(
            static fn (ReportColumn $c) => $c->resolve($record),
            $columns,
        ));

        return $paginator;
    }

    /**
     * Column-key => summed value across the FULL result set, for the totals row.
     *
     * @return array<string, float>
     */
    public function totals(): array
    {
        $summaries = $this->tableSummaries();
        if ($summaries === []) {
            return [];
        }

        $records = $this->reportQuery()->get();
        $totals = [];
        foreach ($this->reportColumns() as $column) {
            if (in_array($column->key, $summaries, true)) {
                $totals[$column->key] = (float) $records->sum(fn (Model $r): float => (float) $column->resolve($r));
            }
        }

        return $totals;
    }

    public function pdfView(): string
    {
        return 'pdf.reports.layout';
    }

    /** @return array<string, mixed> */
    public function pdfData(): array
    {
        return [
            'title' => static::reportLabel(),
            'headings' => $this->reportHeadings(),
            'rows' => $this->reportRows(),
            'filterSummary' => $this->filterSummary(),
            'companyName' => config('handover.company.name'),
            'generatedAt' => now()->format('d.m.Y H:i'),
        ];
    }

    public function toPdf(): StreamedResponse
    {
        $pdf = Pdf::loadView($this->pdfView(), $this->pdfData())->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            static::reportKey().'-'.now()->format('Y-m-d').'.pdf',
        );
    }

    public function toExport(string $format): StreamedResponse
    {
        return app(ReportExportService::class)->download(
            $format,
            static::reportKey().'-'.now()->format('Y-m-d'),
            $this->reportHeadings(),
            $this->reportRows(),
        );
    }
}
```

- [ ] **Step 2: Write `ReportRegistry`** (Task 1 registers only the first report; later tasks append)

```php
<?php

namespace App\Reports;

class ReportRegistry
{
    /** @return array<int, class-string<AbstractReport>> */
    public static function all(): array
    {
        return [
            InventoryByLocationReport::class,
        ];
    }

    /** @return class-string<AbstractReport>|null */
    public static function find(string $key): ?string
    {
        foreach (static::all() as $class) {
            if ($class::reportKey() === $key) {
                return $class;
            }
        }

        return null;
    }
}
```

- [ ] **Step 3: Write `InventoryByLocationReport`** — copy `reportQuery()` and `reportColumns()` **verbatim** from `app/Filament/App/Pages/Evaluation/InventoryByLocationReport.php` (only change the namespace to `App\Reports` and drop the Filament imports). Add the metadata + `filterConfig()`:

```php
<?php

namespace App\Reports;

use App\Models\Asset;
use App\Models\Place;
use Illuminate\Database\Eloquent\Builder;

class InventoryByLocationReport extends AbstractReport
{
    public static function reportKey(): string
    {
        return 'inventory_by_location';
    }

    public static function reportLabel(): string
    {
        return __('evaluation.reports.inventory_by_location.label');
    }

    public static function reportDescription(): string
    {
        return __('evaluation.reports.inventory_by_location.description');
    }

    public static function icon(): string
    {
        return 'MapPin';
    }

    public function filterConfig(): array
    {
        return [
            [
                'key' => 'places',
                'type' => 'multiselect',
                'label' => __('evaluation.reports.inventory_by_location.filter.places'),
                'options' => Place::query()->orderBy('name')->get()
                    ->map(fn (Place $p): array => ['value' => (string) $p->id, 'label' => $p->name])->all(),
            ],
        ];
    }

    public function reportQuery(): Builder
    {
        return Asset::query()
            ->with(['place', 'owner', 'model', 'assetType'])
            ->when(
                ! empty($this->filters['places']),
                fn (Builder $query): Builder => $query->whereIn('place_id', $this->filters['places']),
            );
    }

    public function reportColumns(): array
    {
        $t = 'evaluation.reports.inventory_by_location.columns';

        return [
            \App\Reports\ReportColumn::make('place', __("{$t}.place"), fn (Asset $a): ?string => $a->place?->name),
            \App\Reports\ReportColumn::make('asset_type', __("{$t}.asset_type"), fn (Asset $a): ?string => $a->assetType?->name),
            \App\Reports\ReportColumn::make('model', __("{$t}.model"), fn (Asset $a): ?string => $a->model?->name),
            \App\Reports\ReportColumn::make('serial_number', __("{$t}.serial_number"), fn (Asset $a): ?string => $a->serial_number),
            \App\Reports\ReportColumn::make('state', __("{$t}.state"), fn (Asset $a): ?string => $a->state?->getLabel()),
            \App\Reports\ReportColumn::make('owner', __("{$t}.owner"), fn (Asset $a): ?string => $a->owner?->name),
        ];
    }
}
```

(Use a `use App\Reports\ReportColumn;` import and drop the FQCN if Pint prefers — run Pint at the end.)

- [ ] **Step 4: Write `ReportController`**

```php
<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Reports\ReportRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const PER_PAGE = [25, 50, 100];

    public function index(): Response
    {
        $reports = array_map(
            static fn (string $class): array => [
                'key' => $class::reportKey(),
                'label' => $class::reportLabel(),
                'description' => $class::reportDescription(),
                'icon' => $class::icon(),
            ],
            ReportRegistry::all(),
        );

        return Inertia::render('reports/index', ['reports' => $reports]);
    }

    public function show(string $report, Request $request): Response
    {
        $instance = $this->resolve($report, $request);

        $props = [
            'meta' => [
                'key' => $instance::reportKey(),
                'label' => $instance::reportLabel(),
                'description' => $instance::reportDescription(),
            ],
            'filterConfig' => $instance->filterConfig(),
            'filters' => (object) $instance->filters(),
            'columns' => $instance->columnMeta(),
            'filterSummary' => $instance->filterSummary(),
        ];

        if ($instance->isPaginated()) {
            $perPage = in_array((int) $request->input('per_page'), self::PER_PAGE, true)
                ? (int) $request->input('per_page')
                : 25;
            $paginator = $instance->paginate($perPage);
            $props['rows'] = $paginator->items();
            $props['pagination'] = [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ];
        } else {
            $props['rows'] = $instance->reportRows();
        }

        if ($instance->tableSummaries() !== []) {
            $props['totals'] = (object) $instance->totals();
        }

        return Inertia::render('reports/show', $props);
    }

    public function pdf(string $report, Request $request): StreamedResponse
    {
        return $this->resolve($report, $request)->toPdf();
    }

    public function export(string $report, Request $request): StreamedResponse
    {
        $format = $request->input('format');
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 422);

        return $this->resolve($report, $request)->toExport($format);
    }

    private function resolve(string $report, Request $request): \App\Reports\AbstractReport
    {
        $class = ReportRegistry::find($report);
        abort_unless($class !== null, 404);

        /** @var array<string, mixed> $filters */
        $filters = $request->input('filters', []);

        return app($class)->setFilters(is_array($filters) ? $filters : []);
    }
}
```

- [ ] **Step 5: Register routes** — in `routes/web.php`, inside the authenticated `/app` group, add (grouped near the other resource routes):

```php
Route::get('reports', [\App\Http\Controllers\App\ReportController::class, 'index'])->name('reports.index');
Route::get('reports/{report}/pdf', [\App\Http\Controllers\App\ReportController::class, 'pdf'])->name('reports.pdf');
Route::get('reports/{report}/export', [\App\Http\Controllers\App\ReportController::class, 'export'])->name('reports.export');
Route::get('reports/{report}', [\App\Http\Controllers\App\ReportController::class, 'show'])->name('reports.show');
```

(Import `ReportController` at the top and use the short name to satisfy Pint, matching the file's convention. The `pdf`/`export` routes are declared BEFORE `{report}` so they aren't swallowed by the catch-all `{report}` segment.)

- [ ] **Step 6: Write the tests**

```php
<?php // tests/Feature/App/ReportControllerTest.php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_index_lists_reports(): void
    {
        $this->actingAs($this->actor())->get('/app/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('reports/index')
                ->has('reports', fn (Assert $r) => $r->etc())
                ->where('reports.0.key', 'inventory_by_location')
                ->has('reports.0', fn (Assert $r) => $r->has('key')->has('label')->has('description')->has('icon'))
                ->etc());
    }

    public function test_show_returns_columns_and_rows(): void
    {
        Asset::factory()->count(3)->create();

        $this->actingAs($this->actor())->get('/app/reports/inventory_by_location')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('reports/show')
                ->where('meta.key', 'inventory_by_location')
                ->has('filterConfig')
                ->has('columns', 6)
                ->has('rows', 3)
                ->has('pagination', fn (Assert $pg) => $pg->where('per_page', 25)->where('total', 3)->etc())
                ->etc());
    }

    public function test_show_places_filter(): void
    {
        $keep = Place::factory()->create();
        $other = Place::factory()->create();
        Asset::factory()->create(['place_id' => $keep->id]);
        Asset::factory()->create(['place_id' => $other->id]);

        $this->actingAs($this->actor())
            ->get('/app/reports/inventory_by_location?filters[places][]='.$keep->id)
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());
    }

    public function test_per_page_clamped(): void
    {
        Asset::factory()->count(2)->create();

        $this->actingAs($this->actor())->get('/app/reports/inventory_by_location?per_page=999')
            ->assertInertia(fn (Assert $p) => $p->where('pagination.per_page', 25)->etc());

        $this->actingAs($this->actor())->get('/app/reports/inventory_by_location?per_page=50')
            ->assertInertia(fn (Assert $p) => $p->where('pagination.per_page', 50)->etc());
    }

    public function test_pdf_streams(): void
    {
        Asset::factory()->create();

        $res = $this->actingAs($this->actor())->get('/app/reports/inventory_by_location/pdf');
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', strtolower($res->headers->get('content-type') ?? ''));
    }

    public function test_export_csv_and_xlsx(): void
    {
        Asset::factory()->create();

        $csv = $this->actingAs($this->actor())->get('/app/reports/inventory_by_location/export?format=csv');
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', strtolower($csv->headers->get('content-type') ?? ''));

        $xlsx = $this->actingAs($this->actor())->get('/app/reports/inventory_by_location/export?format=xlsx');
        $xlsx->assertOk();
        $this->assertStringContainsString('spreadsheetml', strtolower($xlsx->headers->get('content-type') ?? ''));
    }

    public function test_export_bad_format_rejected(): void
    {
        $this->actingAs($this->actor())->get('/app/reports/inventory_by_location/export?format=pdf')
            ->assertStatus(422);
    }

    public function test_unknown_report_404(): void
    {
        $this->actingAs($this->actor())->get('/app/reports/nope')->assertNotFound();
    }

    public function test_requires_auth(): void
    {
        $this->get('/app/reports')->assertRedirect();
    }
}
```

- [ ] **Step 7: Run + commit**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/ReportControllerTest.php` → PASS.
Then `ddev exec ./vendor/bin/pint app/Reports app/Http/Controllers/App/ReportController.php routes/web.php tests/Feature/App/ReportControllerTest.php` and `ddev exec ./vendor/bin/phpunit` → full green.

```bash
git add app/Reports app/Http/Controllers/App/ReportController.php routes/web.php tests/Feature/App/ReportControllerTest.php
git commit -m "feat(reports): report backend spine + inventory-by-location"
```

---

### Task 2: Aggregation reports (State overview, Asset value)

**Files:**
- Create: `app/Reports/StateOverviewReport.php`, `app/Reports/AssetValueReport.php`
- Modify: `app/Reports/ReportRegistry.php` (append both)
- Test: `tests/Feature/App/Reports/AggregationReportsTest.php`

**Interfaces:** Consumes `AbstractReport`. Produces two reports; `AssetValueReport` overrides `isPaginated()`, `tableSummaries()`, `pdfData()`.

- [ ] **Step 1: `StateOverviewReport`** — copy `reportQuery()` and `reportColumns()` **verbatim** from the Filament twin; add metadata; `filterConfig(): array { return []; }`; override `public function isPaginated(): bool { return false; }`. Icon `'PieChart'`.

- [ ] **Step 2: `AssetValueReport`** — copy `reportQuery()`, `reportColumns()`, and the private helpers `isDetailed()`, `groupBy()`, `groupLabel()`, and `pdfData()` **verbatim** from the Filament twin (change `parent::pdfData()` — it now calls `AbstractReport::pdfData()`, which is compatible). Add:

```php
public static function icon(): string { return 'Banknote'; }

public function defaultFilters(): array
{
    return ['group_by' => 'employee', 'detailed' => false];
}

public function filterConfig(): array
{
    $g = 'evaluation.reports.asset_value.group_by';

    return [
        [
            'key' => 'group_by', 'type' => 'select', 'nullable' => false, 'default' => 'employee',
            'label' => __('evaluation.reports.asset_value.filter.group_by'),
            'options' => [
                ['value' => 'employee', 'label' => __("{$g}.employee")],
                ['value' => 'asset_type', 'label' => __("{$g}.asset_type")],
                ['value' => 'state', 'label' => __("{$g}.state")],
            ],
        ],
        [
            'key' => 'detailed', 'type' => 'toggle', 'default' => false,
            'label' => __('evaluation.reports.asset_value.filter.detailed'),
        ],
    ];
}

public function isPaginated(): bool
{
    return (bool) ($this->filters['detailed'] ?? false);
}

public function tableSummaries(): array
{
    return $this->isDetailed() ? ['buy_price'] : [];
}
```

Note: `isDetailed()`/`groupBy()` read `$this->filters` exactly as the Filament twin did — copy them verbatim (they reference `$this->filters['detailed']`/`['group_by']`). Because `setFilters()` drops `''`/`null` and merges `defaultFilters()`, an absent/empty `detailed` correctly yields the aggregated (non-paginated) view, and `'1'` yields the detailed (paginated) view.

- [ ] **Step 3: Append to `ReportRegistry::all()`** → `StateOverviewReport::class, AssetValueReport::class` (after `InventoryByLocationReport::class`).

- [ ] **Step 4: Tests**

```php
<?php // tests/Feature/App/Reports/AggregationReportsTest.php

namespace Tests\Feature\App\Reports;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\User;
use App\Reports\AssetValueReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AggregationReportsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_state_overview_is_grouped_and_not_paginated(): void
    {
        Asset::factory()->count(2)->create(['state' => AssetState::NEW->value]);
        Asset::factory()->create(['state' => AssetState::IN_USE->value]);

        $this->actingAs($this->actor())->get('/app/reports/state_overview')
            ->assertInertia(fn (Assert $p) => $p->component('reports/show')
                ->has('rows', 2)          // one row per distinct state
                ->missing('pagination')   // non-paginated
                ->etc());
    }

    public function test_asset_value_aggregated_vs_detailed(): void
    {
        $owner = User::factory()->create();
        Asset::factory()->count(2)->create(['owner_id' => $owner->id, 'buy_price' => 100]);

        // aggregated (default): grouped rows, no pagination, no totals
        $this->actingAs($this->actor())->get('/app/reports/asset_value')
            ->assertInertia(fn (Assert $p) => $p->missing('pagination')->missing('totals')->etc());

        // detailed: per-asset rows, paginated, buy_price total
        $this->actingAs($this->actor())->get('/app/reports/asset_value?filters[detailed]=1')
            ->assertInertia(fn (Assert $p) => $p->has('pagination')
                ->where('totals.buy_price', 200.0)->etc());
    }

    public function test_asset_value_pdf_has_grand_total_row(): void
    {
        Asset::factory()->create(['buy_price' => 150]);

        $report = app(AssetValueReport::class)->setFilters([]);
        $data = $report->pdfData();
        $lastRow = end($data['rows']);
        // aggregated grand-total row: label in col 0, sum in the total_price column (index 2)
        $this->assertSame(__('evaluation.reports.asset_value.total'), $lastRow[0]);
        $this->assertEqualsWithDelta(150.0, (float) $lastRow[2], 0.001);
    }
}
```

- [ ] **Step 5:** Run `ddev exec ./vendor/bin/phpunit tests/Feature/App/Reports/AggregationReportsTest.php` → PASS; Pint the new files; full suite green; commit `feat(reports): state-overview + asset-value (aggregation)`.

---

### Task 3: Asset list reports (Assets per employee, Guarantee status, Asset aging)

**Files:**
- Create: `app/Reports/AssetsPerEmployeeReport.php`, `app/Reports/GuaranteeStatusReport.php`, `app/Reports/AssetAgingReport.php`
- Modify: `app/Reports/ReportRegistry.php`
- Test: `tests/Feature/App/Reports/AssetListReportsTest.php`

- [ ] **Step 1: `AssetsPerEmployeeReport`** — copy `reportQuery()`, `reportColumns()`, `filterSummary()`, `pdfView()`, and `pdfData()` **verbatim** from the Filament twin (the grouped-PDF logic). Add metadata, icon `'Users'`, and:

```php
public function filterConfig(): array
{
    return [[
        'key' => 'employees', 'type' => 'multiselect',
        'label' => __('evaluation.reports.assets_per_employee.filter.employees'),
        'options' => \App\Models\User::query()->orderBy('name')->get()
            ->map(fn (\App\Models\User $u): array => ['value' => (string) $u->id, 'label' => $u->name])->all(),
    ]];
}
```

- [ ] **Step 2: `GuaranteeStatusReport`** — copy `reportQuery()`, `reportColumns()`, and the private helper `statusLabel()` and the `EXPIRING_SOON_DAYS` const **verbatim**. Add metadata, icon `'ShieldCheck'`, and `filterConfig()`:

```php
public function filterConfig(): array
{
    $s = 'evaluation.reports.guarantee_status.status';

    return [
        [
            'key' => 'status', 'type' => 'multiselect',
            'label' => __('evaluation.reports.guarantee_status.filter.status'),
            'options' => [
                ['value' => 'expired', 'label' => __("{$s}.expired")],
                ['value' => 'expiring_soon', 'label' => __("{$s}.expiring_soon")],
                ['value' => 'valid', 'label' => __("{$s}.valid")],
                ['value' => 'none', 'label' => __("{$s}.none")],
            ],
        ],
        [
            'key' => 'employees', 'type' => 'multiselect',
            'label' => __('evaluation.reports.guarantee_status.filter.employees'),
            'options' => \App\Models\User::query()->orderBy('name')->get()
                ->map(fn (\App\Models\User $u): array => ['value' => (string) $u->id, 'label' => $u->name])->all(),
        ],
    ];
}
```

- [ ] **Step 3: `AssetAgingReport`** — copy `reportQuery()`, `reportColumns()`, the private `minAgeYears()`, and the `DEFAULT_MIN_AGE_YEARS` const **verbatim**. Add metadata, icon `'Clock'`, `defaultFilters(): array { return ['min_age_years' => self::DEFAULT_MIN_AGE_YEARS]; }`, and:

```php
public function filterConfig(): array
{
    return [[
        'key' => 'min_age_years', 'type' => 'number', 'default' => self::DEFAULT_MIN_AGE_YEARS, 'min' => 0,
        'label' => __('evaluation.reports.asset_aging.filter.min_age_years'),
    ]];
}
```

- [ ] **Step 4: Append all three to `ReportRegistry::all()`.**

- [ ] **Step 5: Tests**

```php
<?php // tests/Feature/App/Reports/AssetListReportsTest.php

namespace Tests\Feature\App\Reports;

use App\Models\Asset;
use App\Models\User;
use App\Reports\AssetsPerEmployeeReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetListReportsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_guarantee_status_filters_by_status(): void
    {
        Asset::factory()->create(['guarantee_end' => now()->subDay()->format('Y-m-d')]);   // expired
        Asset::factory()->create(['guarantee_end' => now()->addDays(400)->format('Y-m-d')]); // valid

        $this->actingAs($this->actor())
            ->get('/app/reports/guarantee_status?filters[status][]=expired')
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());
    }

    public function test_asset_aging_min_age_cutoff_and_default(): void
    {
        Asset::factory()->create(['buy_date' => now()->subYears(5)->format('Y-m-d')]); // old
        Asset::factory()->create(['buy_date' => now()->subMonths(6)->format('Y-m-d')]); // new

        // default min_age_years = 3 → only the 5y asset
        $this->actingAs($this->actor())->get('/app/reports/asset_aging')
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());

        // min_age_years = 0 → both
        $this->actingAs($this->actor())->get('/app/reports/asset_aging?filters[min_age_years]=0')
            ->assertInertia(fn (Assert $p) => $p->has('rows', 2)->etc());
    }

    public function test_assets_per_employee_pdf_groups_by_owner(): void
    {
        $a = User::factory()->create(['name' => 'Alice']);
        Asset::factory()->count(2)->create(['owner_id' => $a->id]);
        Asset::factory()->create(['owner_id' => null]); // no-owner group

        $data = app(AssetsPerEmployeeReport::class)->setFilters([])->pdfData();
        $this->assertArrayHasKey('groups', $data);
        $labels = array_map(fn (array $g): string => $g['employee'], $data['groups']);
        // no-owner group is pushed last
        $this->assertSame(__('evaluation.reports.assets_per_employee.pdf.no_owner'), end($labels));
    }

    public function test_assets_per_employee_filters_employees(): void
    {
        $a = User::factory()->create();
        Asset::factory()->create(['owner_id' => $a->id]);
        Asset::factory()->create(); // different owner

        $this->actingAs($this->actor())
            ->get('/app/reports/assets_per_employee?filters[employees][]='.$a->id)
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());
    }
}
```

- [ ] **Step 6:** Run the new test file → PASS; Pint; full suite green; commit `feat(reports): assets-per-employee + guarantee-status + asset-aging`.

---

### Task 4: History reports (Incident history, Handover history)

**Files:**
- Create: `app/Reports/IncidentHistoryReport.php`, `app/Reports/HandoverHistoryReport.php`
- Modify: `app/Reports/ReportRegistry.php` (now all 8, in the design order)
- Test: `tests/Feature/App/Reports/HistoryReportsTest.php`

- [ ] **Step 1: `IncidentHistoryReport`** — copy `reportQuery()` and `reportColumns()` **verbatim**. Add metadata, icon `'Wrench'`, and:

```php
public function filterConfig(): array
{
    $s = 'evaluation.reports.incident_history.status';

    return [
        ['key' => 'from', 'type' => 'date', 'label' => __('evaluation.reports.incident_history.filter.from')],
        ['key' => 'to', 'type' => 'date', 'label' => __('evaluation.reports.incident_history.filter.to')],
        [
            'key' => 'status', 'type' => 'select', 'nullable' => true,
            'label' => __('evaluation.reports.incident_history.filter.status'),
            'options' => [
                ['value' => 'open', 'label' => __("{$s}.open")],
                ['value' => 'closed', 'label' => __("{$s}.closed")],
            ],
        ],
    ];
}
```

- [ ] **Step 2: `HandoverHistoryReport`** — copy `reportQuery()` and `reportColumns()` **verbatim**. Add metadata, icon `'ClipboardCheck'`, and:

```php
public function filterConfig(): array
{
    return [
        [
            'key' => 'type', 'type' => 'multiselect',
            'label' => __('evaluation.reports.handover_history.filter.type'),
            'options' => array_map(
                fn (\App\Enums\HandoverType $t): array => ['value' => $t->value, 'label' => $t->getLabel()],
                \App\Enums\HandoverType::cases(),
            ),
        ],
        ['key' => 'from', 'type' => 'date', 'label' => __('evaluation.reports.handover_history.filter.from')],
        ['key' => 'to', 'type' => 'date', 'label' => __('evaluation.reports.handover_history.filter.to')],
    ];
}
```

- [ ] **Step 3: Finalize `ReportRegistry::all()`** to the full 8 in design order: `AssetsPerEmployeeReport, GuaranteeStatusReport, InventoryByLocationReport, AssetValueReport, StateOverviewReport, IncidentHistoryReport, AssetAgingReport, HandoverHistoryReport`.

- [ ] **Step 4: Tests**

```php
<?php // tests/Feature/App/Reports/HistoryReportsTest.php

namespace Tests\Feature\App\Reports;

use App\Enums\HandoverType;
use App\Models\Asset;
use App\Models\Handover;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HistoryReportsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_registry_exposes_all_eight(): void
    {
        $this->actingAs($this->actor())->get('/app/reports')
            ->assertInertia(fn (Assert $p) => $p->has('reports', 8)->etc());
    }

    public function test_incident_history_status_filter(): void
    {
        $asset = Asset::factory()->create();
        Incident::factory()->create(['asset_id' => $asset->id, 'closed_date' => null]);
        Incident::factory()->create(['asset_id' => $asset->id, 'closed_date' => now()]);

        $this->actingAs($this->actor())->get('/app/reports/incident_history?filters[status]=open')
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());
    }

    public function test_incident_history_date_range(): void
    {
        $asset = Asset::factory()->create();
        Incident::factory()->create(['asset_id' => $asset->id, 'open_date' => now()->subDays(2)]);
        Incident::factory()->create(['asset_id' => $asset->id, 'open_date' => now()->subDays(30)]);

        $this->actingAs($this->actor())
            ->get('/app/reports/incident_history?filters[from]='.now()->subDays(5)->format('Y-m-d'))
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());
    }

    public function test_handover_history_type_filter(): void
    {
        Handover::factory()->create(['type' => HandoverType::ISSUE->value]);
        Handover::factory()->create(['type' => HandoverType::RETURN_->value]);

        $this->actingAs($this->actor())
            ->get('/app/reports/handover_history?filters[type][]='.HandoverType::ISSUE->value)
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->etc());
    }
}
```

(`HandoverType` cases are `ISSUE`, `LEND`, `RETURN_` (value `'return'`), `RETURN_DEFECT` — note the trailing underscore on `RETURN_`.)

- [ ] **Step 5:** Run the new test file → PASS; Pint; full suite green; commit `feat(reports): incident-history + handover-history (all 8 registered)`.

---

### Task 5: React reports index + nav + MultiSelectField

**Files:**
- Create: `resources/js/pages/reports/index.tsx`, `resources/js/components/form/multi-select-field.tsx`
- Modify: `resources/js/config/nav.ts`
- Test: `resources/js/pages/reports/__tests__/index.test.tsx`, `resources/js/components/form/__tests__/multi-select-field.test.tsx`

**Interfaces:** Produces `MultiSelectField` (consumed by Task 6). Consumes the `reports/index` prop `{ reports: {key,label,description,icon}[] }`.

- [ ] **Step 1: `MultiSelectField`**

```tsx
// resources/js/components/form/multi-select-field.tsx
import { useMemo, useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Option { value: string; label: string }
interface Props { id: string; label: string; options: Option[]; value: string[]; onChange: (v: string[]) => void }

export function MultiSelectField({ id, label, options, value, onChange }: Props) {
    const [q, setQ] = useState('');
    const filtered = useMemo(
        () => options.filter((o) => o.label.toLowerCase().includes(q.toLowerCase())),
        [options, q],
    );

    const toggle = (v: string) =>
        onChange(value.includes(v) ? value.filter((x) => x !== v) : [...value, v]);

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            <Input id={id} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Suchen…" className="mb-1" />
            <div className="max-h-40 overflow-y-auto rounded-md border p-2">
                {filtered.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Keine Optionen.</p>
                ) : (
                    filtered.map((o) => (
                        <label key={o.value} className="flex cursor-pointer items-center gap-2 py-1 text-sm">
                            <Checkbox checked={value.includes(o.value)} onCheckedChange={() => toggle(o.value)} />
                            {o.label}
                        </label>
                    ))
                )}
            </div>
        </div>
    );
}
```

(Confirm `@/components/ui/input` and `@/components/ui/label` exist — they are used by the form kit; if `Label` isn't a separate primitive, copy the label markup used by `TextField`.)

- [ ] **Step 2: Test `MultiSelectField`**

```tsx
// resources/js/components/form/__tests__/multi-select-field.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { MultiSelectField } from '../multi-select-field';

const opts = [{ value: 'a', label: 'Alpha' }, { value: 'b', label: 'Bravo' }];

describe('MultiSelectField', () => {
    it('adds and removes values', () => {
        const onChange = vi.fn();
        const { rerender } = render(<MultiSelectField id="x" label="X" options={opts} value={[]} onChange={onChange} />);
        fireEvent.click(screen.getByText('Alpha'));
        expect(onChange).toHaveBeenCalledWith(['a']);

        rerender(<MultiSelectField id="x" label="X" options={opts} value={['a']} onChange={onChange} />);
        fireEvent.click(screen.getByText('Alpha'));
        expect(onChange).toHaveBeenCalledWith([]);
    });

    it('filters by search', () => {
        render(<MultiSelectField id="x" label="X" options={opts} value={[]} onChange={vi.fn()} />);
        fireEvent.change(screen.getByPlaceholderText('Suchen…'), { target: { value: 'brav' } });
        expect(screen.queryByText('Alpha')).not.toBeInTheDocument();
        expect(screen.getByText('Bravo')).toBeInTheDocument();
    });
});
```

- [ ] **Step 3: `reports/index.tsx`**

```tsx
// resources/js/pages/reports/index.tsx
import { Link } from '@inertiajs/react';
import { Banknote, ClipboardCheck, Clock, MapPin, PieChart, ShieldCheck, Users, Wrench, type LucideIcon } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const ICONS: Record<string, LucideIcon> = {
    Users, ShieldCheck, MapPin, Banknote, PieChart, Wrench, Clock, ClipboardCheck,
};

interface Report { key: string; label: string; description: string; icon: string }

export default function ReportsIndex({ reports }: { reports: Report[] }) {
    return (
        <AppLayout title="Reports" breadcrumbs={[{ label: 'Reports' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Reports</h1>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {reports.map((r) => {
                    const Icon = ICONS[r.icon] ?? PieChart;
                    return (
                        <Link key={r.key} href={`/app/reports/${r.key}`} className="block">
                            <Card className="h-full transition-colors hover:border-primary">
                                <CardHeader className="flex flex-row items-center gap-3 pb-2">
                                    <Icon className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle className="text-base">{r.label}</CardTitle>
                                </CardHeader>
                                <CardContent className="text-sm text-muted-foreground">{r.description}</CardContent>
                            </Card>
                        </Link>
                    );
                })}
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 4: Nav** — in `resources/js/config/nav.ts`, import a lucide icon (e.g. `BarChart3`) in the existing top import, and append a new group after "Operations", matching the file's `NavGroup`/`NavItem` shape exactly (each item needs a `match` fn):

```ts
{
    label: 'Reports',
    items: [
        { label: 'Reports', href: '/app/reports', icon: BarChart3, match: (p) => p.startsWith('/app/reports') },
    ],
},
```

- [ ] **Step 5: Test `reports/index`**

```tsx
// resources/js/pages/reports/__tests__/index.test.tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({ Link: ({ children, href }: any) => <a href={href}>{children}</a> }));

import ReportsIndex from '../index';

describe('ReportsIndex', () => {
    it('renders a card per report linking to it', () => {
        render(<ReportsIndex reports={[
            { key: 'state_overview', label: 'Status-Übersicht', description: 'desc', icon: 'PieChart' },
        ]} />);
        const link = screen.getByRole('link', { name: /Status-Übersicht/ });
        expect(link).toHaveAttribute('href', '/app/reports/state_overview');
    });
});
```

- [ ] **Step 6:** Run both Vitest files → PASS; `ddev exec pnpm run build` → clean; commit `feat(reports): reports index page + nav + multi-select field`.

---

### Task 6: React report detail page (filters + table + downloads)

**Files:**
- Create: `resources/js/pages/reports/show.tsx`
- Test: `resources/js/pages/reports/__tests__/show.test.tsx`

**Interfaces:** Consumes the `reports/show` props from Task 1 and `MultiSelectField` from Task 5.

- [ ] **Step 1: `show.tsx`**

```tsx
// resources/js/pages/reports/show.tsx
import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { SelectField } from '@/components/form/select-field';
import { DateField } from '@/components/form/date-field';
import { NumberField } from '@/components/form/number-field';
import { SwitchField } from '@/components/form/switch-field';
import { MultiSelectField } from '@/components/form/multi-select-field';

interface Opt { value: string; label: string }
interface FilterCfg {
    key: string;
    type: 'multiselect' | 'select' | 'date' | 'number' | 'toggle';
    label: string;
    options?: Opt[];
    nullable?: boolean;
    default?: string | number | boolean;
    min?: number;
}
interface ColumnMeta { key: string; label: string }
interface Pagination { current_page: number; last_page: number; per_page: number; total: number }
type Cell = string | number | null;

interface Props {
    meta: { key: string; label: string; description: string };
    filterConfig: FilterCfg[];
    filters: Record<string, unknown>;
    columns: ColumnMeta[];
    rows: Cell[][];
    filterSummary: string;
    pagination?: Pagination;
    totals?: Record<string, number>;
}

type FilterValue = string | string[] | boolean;

function initialValue(cfg: FilterCfg, current: unknown): FilterValue {
    if (cfg.type === 'multiselect') return Array.isArray(current) ? (current as string[]) : [];
    if (cfg.type === 'toggle') return current === true || current === '1' || current === 1;
    if (current === undefined || current === null) return cfg.default !== undefined ? String(cfg.default) : '';
    return String(current);
}

// Build a query object Inertia serializes as ?filters[key]=… (arrays as filters[key][]=…).
function toParams(values: Record<string, FilterValue>): Record<string, unknown> {
    const filters: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(values)) {
        if (Array.isArray(v)) { if (v.length) filters[k] = v; }
        else if (typeof v === 'boolean') { if (v) filters[k] = '1'; }
        else if (v !== '') filters[k] = v;
    }
    return { filters };
}

// Build a query string for plain-anchor download links.
function toQuery(values: Record<string, FilterValue>, extra: Record<string, string> = {}): string {
    const sp = new URLSearchParams();
    for (const [k, v] of Object.entries(values)) {
        if (Array.isArray(v)) v.forEach((x) => sp.append(`filters[${k}][]`, x));
        else if (typeof v === 'boolean') { if (v) sp.set(`filters[${k}]`, '1'); }
        else if (v !== '') sp.set(`filters[${k}]`, v);
    }
    for (const [k, v] of Object.entries(extra)) sp.set(k, v);
    const s = sp.toString();
    return s ? `?${s}` : '';
}

export default function ReportShow({ meta, filterConfig, filters, columns, rows, filterSummary, pagination, totals }: Props) {
    const [values, setValues] = useState<Record<string, FilterValue>>(() =>
        Object.fromEntries(filterConfig.map((c) => [c.key, initialValue(c, filters[c.key])])),
    );

    const set = (k: string, v: FilterValue) => setValues((prev) => ({ ...prev, [k]: v }));
    const base = `/app/reports/${meta.key}`;

    const apply = () => router.get(base, toParams(values), { preserveScroll: true, preserveState: true });
    const reset = () => router.get(base, {}, { preserveScroll: true });
    const goPage = (page: number) =>
        router.get(base, { ...toParams(values), per_page: pagination?.per_page, page }, { preserveScroll: true, preserveState: true });
    const setPerPage = (per: string) =>
        router.get(base, { ...toParams(values), per_page: per }, { preserveScroll: true, preserveState: true });

    return (
        <AppLayout title={meta.label} breadcrumbs={[{ label: 'Reports', href: '/app/reports' }, { label: meta.label }]}>
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">{meta.label}</h1>
                    <p className="text-sm text-muted-foreground">{meta.description}</p>
                </div>
                <div className="flex gap-2">
                    <Button asChild variant="outline"><a href={`${base}/pdf${toQuery(values)}`}>PDF</a></Button>
                    <Button asChild variant="outline"><a href={`${base}/export${toQuery(values, { format: 'xlsx' })}`}>Excel</a></Button>
                    <Button asChild variant="outline"><a href={`${base}/export${toQuery(values, { format: 'csv' })}`}>CSV</a></Button>
                </div>
            </div>

            {filterConfig.length > 0 && (
                <Card className="mb-4">
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2 lg:grid-cols-3">
                        {filterConfig.map((c) => {
                            const v = values[c.key];
                            if (c.type === 'multiselect') return <MultiSelectField key={c.key} id={c.key} label={c.label} options={c.options ?? []} value={v as string[]} onChange={(nv) => set(c.key, nv)} />;
                            if (c.type === 'select') return <SelectField key={c.key} id={c.key} label={c.label} options={c.options ?? []} nullable={c.nullable !== false} value={v as string} onChange={(nv) => set(c.key, nv)} />;
                            if (c.type === 'date') return <DateField key={c.key} id={c.key} label={c.label} value={v as string} onChange={(nv) => set(c.key, nv)} />;
                            if (c.type === 'number') return <NumberField key={c.key} id={c.key} label={c.label} step="1" min={c.min !== undefined ? String(c.min) : undefined} value={v as string} onChange={(nv) => set(c.key, nv)} />;
                            return <SwitchField key={c.key} id={c.key} label={c.label} checked={v as boolean} onChange={(nv) => set(c.key, nv)} />;
                        })}
                        <div className="flex items-end gap-2">
                            <Button onClick={apply}>Apply</Button>
                            <Button variant="ghost" onClick={reset}>Reset</Button>
                        </div>
                    </CardContent>
                </Card>
            )}

            {filterSummary && <p className="mb-2 text-sm text-muted-foreground">{filterSummary}</p>}

            <Card>
                <CardContent className="pt-6">
                    {rows.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Keine Daten für die gewählten Filter.</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>{columns.map((c) => <TableHead key={c.key}>{c.label}</TableHead>)}</TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row, i) => (
                                    <TableRow key={i}>
                                        {row.map((cell, j) => <TableCell key={j}>{cell ?? '—'}</TableCell>)}
                                    </TableRow>
                                ))}
                                {totals && Object.keys(totals).length > 0 && (
                                    <TableRow className="font-semibold">
                                        {columns.map((c, j) => (
                                            <TableCell key={c.key}>{j === 0 ? 'Σ' : (totals[c.key] ?? '')}</TableCell>
                                        ))}
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}

                    {pagination && pagination.last_page > 1 && (
                        <div className="mt-4 flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                {pagination.total} · Seite {pagination.current_page}/{pagination.last_page}
                            </span>
                            <div className="flex items-center gap-2">
                                <select
                                    className="rounded-md border bg-background px-2 py-1"
                                    value={String(pagination.per_page)}
                                    onChange={(e) => setPerPage(e.target.value)}
                                >
                                    {['25', '50', '100'].map((n) => <option key={n} value={n}>{n}</option>)}
                                </select>
                                <Button variant="outline" size="sm" disabled={pagination.current_page <= 1} onClick={() => goPage(pagination.current_page - 1)}>Prev</Button>
                                <Button variant="outline" size="sm" disabled={pagination.current_page >= pagination.last_page} onClick={() => goPage(pagination.current_page + 1)}>Next</Button>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Test `reports/show`**

```tsx
// resources/js/pages/reports/__tests__/show.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const get = vi.fn();
vi.mock('@inertiajs/react', () => ({ router: { get: (...a: unknown[]) => get(...a) } }));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import ReportShow from '../show';

const base = {
    meta: { key: 'guarantee_status', label: 'Garantie', description: 'd' },
    filterConfig: [
        { key: 'status', type: 'multiselect' as const, label: 'Status', options: [{ value: 'expired', label: 'Abgelaufen' }] },
        { key: 'min', type: 'number' as const, label: 'Min', default: 3 },
    ],
    filters: {},
    columns: [{ key: 'owner', label: 'Owner' }, { key: 'buy_price', label: 'Preis' }],
    rows: [['Alice', 100]],
    filterSummary: '',
    pagination: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
};

describe('ReportShow', () => {
    it('renders columns and a data row', () => {
        render(<ReportShow {...base} />);
        expect(screen.getByText('Owner')).toBeInTheDocument();
        expect(screen.getByText('Alice')).toBeInTheDocument();
    });

    it('Apply pushes the selected filters to the URL', () => {
        get.mockClear();
        render(<ReportShow {...base} />);
        fireEvent.click(screen.getByText('Abgelaufen'));       // select the multiselect option
        fireEvent.click(screen.getByText('Apply'));
        expect(get).toHaveBeenCalledWith('/app/reports/guarantee_status',
            { filters: { status: ['expired'], min: '3' } },
            expect.objectContaining({ preserveScroll: true }));
    });

    it('renders a totals row when totals present', () => {
        render(<ReportShow {...base} totals={{ buy_price: 100 }} />);
        expect(screen.getByText('Σ')).toBeInTheDocument();
    });

    it('shows an empty state with no rows', () => {
        render(<ReportShow {...base} rows={[]} />);
        expect(screen.getByText(/Keine Daten/)).toBeInTheDocument();
    });
});
```

- [ ] **Step 3:** Run `ddev exec pnpm exec vitest run resources/js/pages/reports/__tests__/show.test.tsx` → PASS; full JS suite + `ddev exec pnpm run build` → clean; commit `feat(reports): report detail page (filters, table, downloads)`.

---

### Task 7: Final verification

- [ ] **Step 1:** `ddev exec ./vendor/bin/phpunit` → green.
- [ ] **Step 2:** `ddev exec pnpm run test` → green.
- [ ] **Step 3:** `ddev exec pnpm run build` → succeeds.
- [ ] **Step 4:** `ddev exec ./vendor/bin/pint --test` → clean (CI lint gate).
- [ ] **Step 5: Manual verify** — `/app/reports` lists 8 cards; each opens; filters + Apply/Reset re-query; pagination on list reports; totals on asset_value detailed; PDF/CSV/XLSX download and match Filament's output; `/app-old` Evaluation pages still work.
- [ ] **Step 6:** Commit anything outstanding.

---

## Self-Review Notes

- **Spec coverage:** AbstractReport/registry/controller/routes → Task 1; the 8 reports → Tasks 1–4 (1: inventory; 2: state_overview+asset_value; 3: assets_per_employee+guarantee_status+asset_aging; 4: incident_history+handover_history); index+nav+MultiSelectField → Task 5; detail page (filters/table/pagination/totals/downloads) → Task 6; tests throughout + Task 7.
- **Verbatim reuse:** each report copies its Filament twin's query/columns/summary/pdfData; `ReportColumn`, `ReportExportService`, PDF blades, and lang file are untouched.
- **Type/contract consistency:** controller props (`meta`, `filterConfig`, `filters`, `columns`, `rows`, `pagination?`, `totals?`) match `show.tsx`'s `Props`; `filterConfig` entry shapes match the renderer's `switch`; `toParams` serialization (`filters[key]`, arrays as `filters[key][]`) matches the controller reading `$request->input('filters')` and the PHPUnit query-string tests.
- **Edge cases:** `setFilters` keeps `'0'`/`0` and empty arrays (so `min_age_years=0` and cleared multiselects behave); `asset_value` pagination/totals depend on `detailed`; `pdf`/`export` routes declared before `{report}`.
- **Deferred (per spec):** English translations, per-column sorting, scheduling, charts; Filament pages removed at cutover (Spec 9).

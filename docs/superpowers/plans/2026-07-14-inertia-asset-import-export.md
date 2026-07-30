# Inertia Migration — Asset Import/Export (4e) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Synchronous CSV export (respecting index filters) and import (find-or-create resolution, per-row errors, row cap) of assets in the Inertia app — closing out Spec 4.

**Architecture:** A shared `AssetTableQuery` helper carries the index's 4-join + searchable/sortable/filterable config (used by both the index and the export so filters stay in sync); a new `TableQuery::get()` returns the filtered set unpaginated. Export streams a CSV via `fputcsv`. Import parses via `fgetcsv` and delegates row processing to a framework-plain, unit-tested `AssetImport` service (ported Filament resolution rules); the controller enforces the header + a 2000-row cap and flashes an `importResult` the index renders.

**Tech Stack:** Laravel 13/PHP 8.4, native CSV (`fputcsv`/`fgetcsv`), Inertia v2 + React 19, shadcn/ui, PHPUnit, Vitest. ddev; pnpm.

## Global Constraints

- ddev for all commands; **PHP tests are PHPUnit**; JS tests are **Vitest**.
- Do NOT touch Filament (`/app-old`), its `AssetImporter`/`AssetExporter` (left for `/app-old`), or the DB schema.
- New: `app/Support/Assets/AssetCsv.php`, `app/Support/Assets/AssetTableQuery.php`, `app/Support/Assets/AssetImport.php`, `app/Support/Assets/RowImportException.php`, `app/Http/Controllers/App/AssetExportController.php`, `app/Http/Controllers/App/AssetImportController.php`, `app/Http/Requests/App/AssetImportRequest.php`. Modify: `app/Support/Table/TableQuery.php` (add `get()`), `app/Http/Controllers/App/AssetController.php` (use the helper + pass `importResult`), `routes/web.php`, `resources/js/pages/assets/index.tsx` (+ new dialog component). `@/` → `resources/js/*`.
- Canonical headers (`AssetCsv::HEADERS`), order-fixed, shared by export + import: `['id','state','asset_type','manufacturer','model','place','owner','serial_number','buy_date','guarantee_end','buy_price','buy_type','tags']`.
- Import: synchronous, **row cap 2000**, per-row DB transaction, collect failures (don't abort). find-or-create asset_type/manufacturer/model(scoped)/place; auto-create login-disabled owner. `state`/`buy_type` accept enum value OR German label. dates ISO or `d.m.Y`. `id` present+existing → row error; present+new → asset UUID. tags comma-split.
- Export: streamed CSV of the **filtered** set (same `search`/`sort`/`filter[...]` as the index); `state`/`buy_type` as labels; dates ISO `Y-m-d`; tags comma-joined; relation names.
- No policy (gate on `auth`). TDD for backend; commit per task.

---

### Task 1: Export (shared query helper, `TableQuery::get()`, export controller, index button)

**Files:**
- Create: `app/Support/Assets/AssetCsv.php`, `app/Support/Assets/AssetTableQuery.php`, `app/Http/Controllers/App/AssetExportController.php`
- Modify: `app/Support/Table/TableQuery.php` (add `get()`), `app/Http/Controllers/App/AssetController.php` (use helper), `routes/web.php`, `resources/js/pages/assets/index.tsx` (Export button)
- Test: `tests/Feature/App/AssetExportControllerTest.php`, `tests/Feature/Support/TableQueryTest.php` (add a `get()` case)

**Interfaces:**
- Produces: `AssetCsv::HEADERS` (array); `AssetTableQuery::configure(Builder $query, Request $request): TableQuery` (adds the 4 joins + configures searchable/sortable/filterable, returns the `TableQuery`); `TableQuery::get(): \Illuminate\Support\Collection` (applies search/sort/filters, returns `$query->get()`); route `app.assets.export`.

- [ ] **Step 1: Add `TableQuery::get()` (TDD)**

Add to `tests/Feature/Support/TableQueryTest.php`:

```php
public function test_get_returns_filtered_unpaginated_collection(): void
{
    \App\Models\Manufacturer::factory()->create(['name' => 'Acme']);
    \App\Models\Manufacturer::factory()->create(['name' => 'Globex']);

    $request = \Illuminate\Http\Request::create('/x', 'GET', ['search' => 'acme']);
    $result = \App\Support\Table\TableQuery::for(\App\Models\Manufacturer::query(), $request)
        ->searchable(['name'])->get();

    $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    $this->assertCount(1, $result);
    $this->assertSame('Acme', $result->first()->name);
}
```

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/Support/TableQueryTest.php` → the new case FAILS (`get()` missing). Then add to `app/Support/Table/TableQuery.php`:

```php
public function get(): \Illuminate\Support\Collection
{
    $this->applyFilters();
    $this->applySearch();
    $this->applySort();

    return $this->query->get();
}
```

(Mirrors `paginate()`'s apply order; no pagination.) Re-run → PASS.

- [ ] **Step 2: Create `AssetCsv`**

```php
<?php // app/Support/Assets/AssetCsv.php
namespace App\Support\Assets;

class AssetCsv
{
    /** Canonical CSV column order, shared by export + import. */
    public const HEADERS = [
        'id', 'state', 'asset_type', 'manufacturer', 'model', 'place', 'owner',
        'serial_number', 'buy_date', 'guarantee_end', 'buy_price', 'buy_type', 'tags',
    ];
}
```

- [ ] **Step 3: Create `AssetTableQuery` helper and use it from `AssetController@index`**

```php
<?php // app/Support/Assets/AssetTableQuery.php
namespace App\Support\Assets;

use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AssetTableQuery
{
    /**
     * Add the standard asset relation joins and configure the shared
     * searchable/sortable/filterable whitelist. Callers set their own select()
     * before calling and choose paginate()/get() after.
     */
    public static function configure(Builder $query, Request $request): TableQuery
    {
        $query
            ->leftJoin('asset_types', 'asset_types.id', '=', 'assets.asset_type_id')
            ->leftJoin('asset_models', 'asset_models.id', '=', 'assets.model_id')
            ->leftJoin('manufacturers', 'manufacturers.id', '=', 'asset_models.manufacturer_id')
            ->leftJoin('users', 'users.id', '=', 'assets.owner_id');

        return TableQuery::for($query, $request)
            ->searchable(['assets.serial_number', 'asset_types.name', 'manufacturers.name', 'asset_models.name', 'users.name'])
            ->sortable(['assets.state', 'asset_type_name', 'manufacturer_name', 'model_name', 'owner_name', 'assets.serial_number', 'assets.buy_price', 'incidents_count'])
            ->filterable([
                'state' => 'assets.state',
                'asset_type_id' => 'assets.asset_type_id',
                'manufacturer_id' => 'asset_models.manufacturer_id',
            ]);
    }
}
```

Refactor `AssetController@index` to use it (keep the exact select + `withCount` + the existing behavior; only the joins + TableQuery config move into the helper):

```php
$query = Asset::query()
    ->select(
        'assets.id', 'assets.state', 'assets.serial_number', 'assets.buy_price',
        'asset_types.name as asset_type_name',
        'asset_models.name as model_name',
        'manufacturers.name as manufacturer_name',
        'users.name as owner_name',
    )
    ->withCount('incidents');

$assets = AssetTableQuery::configure($query, $request)->paginate();
```

(Add `use App\Support\Assets\AssetTableQuery;`. The rest of `index()` — the row transform + Inertia::render — is unchanged.)

- [ ] **Step 4: Write the failing export test**

```php
<?php // tests/Feature/App/AssetExportControllerTest.php
namespace Tests\Feature\App;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\AssetType;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User { return User::factory()->create(['login_enabled' => true]); }

    public function test_export_streams_csv_with_header_and_resolved_row(): void
    {
        $mfr = Manufacturer::factory()->create(['name' => 'Acme']);
        $model = AssetModel::factory()->create(['name' => 'X1', 'manufacturer_id' => $mfr->id]);
        $type = AssetType::factory()->create(['name' => 'Laptop']);
        $asset = Asset::factory()->create([
            'asset_type_id' => $type->id, 'model_id' => $model->id, 'owner_id' => null,
            'state' => AssetState::IN_USE->value, 'serial_number' => 'SN-9', 'buy_date' => '2024-01-05',
        ]);
        $asset->syncTags(['a', 'b']);

        $res = $this->actingAs($this->actor())->get('/app/assets/export');
        $res->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $res->streamedContent();

        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertStringContainsString('id,state,asset_type,manufacturer,model,place,owner,serial_number,buy_date,guarantee_end,buy_price,buy_type,tags', $lines[0]);
        $this->assertStringContainsString('Laptop', $csv);
        $this->assertStringContainsString('Acme', $csv);
        $this->assertStringContainsString('X1', $csv);
        $this->assertStringContainsString(AssetState::IN_USE->getLabel(), $csv);
        $this->assertStringContainsString('2024-01-05', $csv);
        $this->assertStringContainsString('"a, b"', $csv); // tags joined, quoted by fputcsv
    }

    public function test_export_respects_state_filter(): void
    {
        Asset::factory()->create(['state' => AssetState::IN_USE->value, 'serial_number' => 'KEEP']);
        Asset::factory()->create(['state' => AssetState::DEFECT->value, 'serial_number' => 'DROP']);

        $csv = $this->actingAs($this->actor())->get('/app/assets/export?filter[state]=in-use')->streamedContent();

        $this->assertStringContainsString('KEEP', $csv);
        $this->assertStringNotContainsString('DROP', $csv);
    }

    public function test_export_requires_auth(): void
    {
        $this->get('/app/assets/export')->assertRedirect();
    }
}
```

- [ ] **Step 5: Run to verify it fails**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetExportControllerTest.php`
Expected: FAIL — route/controller undefined.

- [ ] **Step 6: Create the export controller**

```php
<?php // app/Http/Controllers/App/AssetExportController.php
namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Support\Assets\AssetCsv;
use App\Support\Assets\AssetTableQuery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetExportController extends Controller
{
    public function index(Request $request): StreamedResponse
    {
        $query = Asset::query()
            ->with('tags')
            ->select(
                'assets.*',
                'asset_types.name as asset_type_name',
                'asset_models.name as model_name',
                'manufacturers.name as manufacturer_name',
                'users.name as owner_name',
            );

        $assets = AssetTableQuery::configure($query, $request)->get();

        return response()->streamDownload(function () use ($assets): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, AssetCsv::HEADERS);

            foreach ($assets as $asset) {
                fputcsv($out, [
                    $asset->id,
                    $asset->state?->getLabel(),
                    $asset->asset_type_name,
                    $asset->manufacturer_name,
                    $asset->model_name,
                    $asset->place_name ?? optional($asset->place)->name, // place not joined -> relation
                    $asset->owner_name,
                    $asset->serial_number,
                    optional($asset->buy_date)->format('Y-m-d'),
                    optional($asset->guarantee_end)->format('Y-m-d'),
                    $asset->buy_price,
                    $asset->buy_type?->getLabel(),
                    $asset->tags->pluck('name')->implode(', '),
                ]);
            }

            fclose($out);
        }, 'assets.csv', ['Content-Type' => 'text/csv']);
    }
}
```

NOTE: `place` is not one of the joined tables in `AssetTableQuery` (the index doesn't need a place column), so `place_name` isn't selected. Add `place` to the eager load for the export instead: change `->with('tags')` to `->with(['tags', 'place'])` and set the place cell to `optional($asset->place)->name` (remove the `place_name` reference). Ensure the CSV `place` column uses `optional($asset->place)->name`.

- [ ] **Step 7: Register the route (BEFORE the assets resource)**

In `routes/web.php`, inside the authenticated `/app` group, add the export route **before** `Route::resource('assets', ...)` so `/app/assets/export` isn't captured by the resource's `{asset}` show route:

```php
use App\Http\Controllers\App\AssetExportController;
// … inside the authenticated group, ABOVE Route::resource('assets', ...):
Route::get('assets/export', [AssetExportController::class, 'index'])->name('assets.export');
```

- [ ] **Step 8: Add the Export button to the index toolbar**

In `resources/js/pages/assets/index.tsx`, in the header toolbar (next to "New asset"), add a plain anchor download that carries the current query string (a file download, not an Inertia visit):

```tsx
<Button variant="outline" asChild>
    <a href={`/app/assets/export${typeof window !== 'undefined' ? window.location.search : ''}`}>Export</a>
</Button>
```

- [ ] **Step 9: Run tests + build**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetExportControllerTest.php tests/Feature/App/AssetControllerTest.php tests/Feature/Support/TableQueryTest.php`
Expected: PASS (export + the refactored index still green + TableQuery get()).
Run: `ddev exec pnpm run build` → succeeds.

- [ ] **Step 10: Commit**

```bash
git add app/Support/Assets/AssetCsv.php app/Support/Assets/AssetTableQuery.php app/Support/Table/TableQuery.php app/Http/Controllers/App/AssetExportController.php app/Http/Controllers/App/AssetController.php routes/web.php resources/js/pages/assets/index.tsx tests/Feature/App/AssetExportControllerTest.php tests/Feature/Support/TableQueryTest.php
git commit -m "feat(assets): CSV export (filtered) + shared AssetTableQuery helper + TableQuery::get()"
```

---

### Task 2: `AssetImport` service (resolution logic)

**Files:**
- Create: `app/Support/Assets/AssetImport.php`, `app/Support/Assets/RowImportException.php`
- Test: `tests/Feature/App/AssetImportTest.php`

**Interfaces:**
- Consumes: `Asset`, `AssetType`, `AssetModel`, `Manufacturer`, `Place`, `User`, `AssetState`, `BuyType`.
- Produces: `RowImportException extends \RuntimeException`; `AssetImport::import(array $rows): array` where each `$rows[$i]` is `array<string,string>` keyed by canonical header, returning `['imported' => int, 'failed' => [['row' => int, 'message' => string], ...]]`. Row numbers are 1-based **data** rows offset by the header, i.e. `row = index + 2`.

- [ ] **Step 1: Write the failing tests**

```php
<?php // tests/Feature/App/AssetImportTest.php
namespace Tests\Feature\App;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Manufacturer;
use App\Models\User;
use App\Support\Assets\AssetImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetImportTest extends TestCase
{
    use RefreshDatabase;

    private function row(array $overrides = []): array
    {
        return array_merge([
            'id' => '', 'state' => 'in-use', 'asset_type' => 'Laptop', 'manufacturer' => 'Acme',
            'model' => 'X1', 'place' => 'Office', 'owner' => 'Ada Lovelace', 'serial_number' => 'SN-1',
            'buy_date' => '2024-01-05', 'guarantee_end' => '', 'buy_price' => '999.50',
            'buy_type' => '', 'tags' => 'portable, audited',
        ], $overrides);
    }

    public function test_imports_a_row_creating_lookups_and_owner(): void
    {
        $result = (new AssetImport)->import([$this->row()]);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['failed']);

        $asset = Asset::firstWhere('serial_number', 'SN-1');
        $this->assertNotNull($asset);
        $this->assertSame(AssetState::IN_USE, $asset->state);
        $this->assertSame('Laptop', $asset->assetType->name);
        $this->assertSame('Acme', $asset->model->manufacturer->name);
        $this->assertSame('X1', $asset->model->name);
        $this->assertSame('Office', $asset->place->name);
        $owner = User::firstWhere('name', 'Ada Lovelace');
        $this->assertNotNull($owner);
        $this->assertFalse($owner->login_enabled);
        $this->assertSame('Ada', $owner->firstname);
        $this->assertSame('Lovelace', $owner->lastname);
        $this->assertEqualsCanonicalizing(['portable', 'audited'], $asset->tags->pluck('name')->all());
    }

    public function test_accepts_enum_label_and_german_date(): void
    {
        $result = (new AssetImport)->import([$this->row(['state' => 'Defekt', 'buy_date' => '05.01.2024'])]);
        $this->assertSame(1, $result['imported']);
        $asset = Asset::firstWhere('serial_number', 'SN-1');
        $this->assertSame(AssetState::DEFECT, $asset->state);
        $this->assertSame('2024-01-05', $asset->buy_date->toDateString());
    }

    public function test_model_without_manufacturer_fails_the_row(): void
    {
        $result = (new AssetImport)->import([$this->row(['manufacturer' => '', 'model' => 'Orphan'])]);
        $this->assertSame(0, $result['imported']);
        $this->assertSame(2, $result['failed'][0]['row']);
    }

    public function test_unknown_state_fails_the_row(): void
    {
        $result = (new AssetImport)->import([$this->row(['state' => 'nonsense'])]);
        $this->assertSame(0, $result['imported']);
        $this->assertStringContainsString('nonsense', $result['failed'][0]['message']);
    }

    public function test_invalid_date_fails_the_row(): void
    {
        $result = (new AssetImport)->import([$this->row(['buy_date' => 'not-a-date'])]);
        $this->assertSame(0, $result['imported']);
    }

    public function test_existing_id_fails_but_new_id_is_used(): void
    {
        $existing = Asset::factory()->create();
        $dup = (new AssetImport)->import([$this->row(['id' => $existing->id])]);
        $this->assertSame(0, $dup['imported']);

        $uuid = '11111111-1111-4111-8111-111111111111';
        $new = (new AssetImport)->import([$this->row(['id' => $uuid, 'serial_number' => 'SN-NEW'])]);
        $this->assertSame(1, $new['imported']);
        $this->assertNotNull(Asset::find($uuid));
    }

    public function test_owner_is_matched_case_insensitively_when_existing(): void
    {
        $u = User::factory()->create(['name' => 'Ada Lovelace']);
        (new AssetImport)->import([$this->row(['owner' => 'ada lovelace'])]);
        $this->assertSame(1, User::where('name', 'Ada Lovelace')->count()); // not duplicated
        $this->assertSame($u->id, Asset::firstWhere('serial_number', 'SN-1')->owner_id);
    }

    public function test_one_bad_row_does_not_block_good_rows(): void
    {
        $result = (new AssetImport)->import([
            $this->row(['serial_number' => 'GOOD-1']),
            $this->row(['serial_number' => 'BAD', 'state' => 'nope']),
            $this->row(['serial_number' => 'GOOD-2']),
        ]);
        $this->assertSame(2, $result['imported']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame(3, $result['failed'][0]['row']); // 2nd data row -> row 3
        $this->assertNull(Asset::firstWhere('serial_number', 'BAD'));
        $this->assertNotNull(Asset::firstWhere('serial_number', 'GOOD-1'));
        $this->assertNotNull(Asset::firstWhere('serial_number', 'GOOD-2'));
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetImportTest.php`
Expected: FAIL — `AssetImport`/`RowImportException` missing.

- [ ] **Step 3: Create the exception**

```php
<?php // app/Support/Assets/RowImportException.php
namespace App\Support\Assets;

class RowImportException extends \RuntimeException {}
```

- [ ] **Step 4: Implement the service**

```php
<?php // app/Support/Assets/AssetImport.php
namespace App\Support\Assets;

use App\Enums\AssetState;
use App\Enums\BuyType;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\AssetType;
use App\Models\Manufacturer;
use App\Models\Place;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class AssetImport
{
    /**
     * @param  array<int, array<string, string>>  $rows  each keyed by canonical header
     * @return array{imported:int, failed: array<int, array{row:int, message:string}>}
     */
    public function import(array $rows): array
    {
        $imported = 0;
        $failed = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // header is line 1; first data row is line 2
            try {
                DB::transaction(fn () => $this->importRow($row));
                $imported++;
            } catch (RowImportException $e) {
                $failed[] = ['row' => $rowNumber, 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                $failed[] = ['row' => $rowNumber, 'message' => 'Unexpected error: '.$e->getMessage()];
            }
        }

        return ['imported' => $imported, 'failed' => $failed];
    }

    private function importRow(array $row): void
    {
        $get = fn (string $k): string => trim((string) ($row[$k] ?? ''));

        $id = $get('id');
        $asset = new Asset;
        if ($id !== '') {
            if (Asset::whereKey($id)->exists()) {
                throw new RowImportException("An asset with id [{$id}] already exists.");
            }
            $asset->{$asset->getKeyName()} = $id;
        }

        // required state
        $asset->state = $this->resolveEnum(AssetState::class, $get('state'), required: true);

        // required asset type
        $asset->asset_type_id = $this->firstOrCreateByName(AssetType::class, $get('asset_type'), required: true)->getKey();

        // manufacturer + model (model requires manufacturer)
        $modelName = $get('model');
        if ($modelName !== '') {
            $manufacturerName = $get('manufacturer');
            if ($manufacturerName === '') {
                throw new RowImportException('A model requires a manufacturer.');
            }
            $manufacturer = $this->firstOrCreateByName(Manufacturer::class, $manufacturerName);
            $model = AssetModel::query()
                ->where('manufacturer_id', $manufacturer->getKey())
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($modelName)])
                ->first()
                ?? AssetModel::query()->create(['name' => $modelName, 'manufacturer_id' => $manufacturer->getKey()]);
            $asset->model_id = $model->getKey();
        }

        if (($place = $get('place')) !== '') {
            $asset->place_id = $this->firstOrCreateByName(Place::class, $place)->getKey();
        }
        if (($owner = $get('owner')) !== '') {
            $asset->owner_id = $this->resolveOwner($owner)->getKey();
        }

        $asset->serial_number = $get('serial_number') ?: null;
        $asset->buy_date = $this->parseDate($get('buy_date'));
        $asset->guarantee_end = $this->parseDate($get('guarantee_end'));
        $asset->buy_price = $get('buy_price') !== '' ? $get('buy_price') : null;
        if (($bt = $get('buy_type')) !== '') {
            $asset->buy_type = $this->resolveEnum(BuyType::class, $bt, required: false);
        }

        $asset->save();

        $tags = collect(explode(',', $get('tags')))->map(fn ($t) => trim($t))->filter()->values()->all();
        if ($tags !== []) {
            $asset->syncTags($tags);
        }
    }

    /** @param class-string $enumClass */
    private function resolveEnum(string $enumClass, string $value, bool $required): ?string
    {
        if ($value === '') {
            if ($required) {
                throw new RowImportException('Missing required value.');
            }

            return null;
        }
        if ($case = $enumClass::tryFrom($value)) {
            return $case->value;
        }
        foreach ($enumClass::cases() as $candidate) {
            if (mb_strtolower((string) $candidate->getLabel()) === mb_strtolower($value)) {
                return $candidate->value;
            }
        }
        throw new RowImportException("Unknown value [{$value}].");
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            throw new RowImportException("Invalid date [{$value}].");
        }
    }

    /** @param class-string<Model> $modelClass */
    private function firstOrCreateByName(string $modelClass, string $name, bool $required = false): Model
    {
        if ($name === '') {
            if ($required) {
                throw new RowImportException('Missing required value.');
            }
        }

        return $modelClass::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first()
            ?? $modelClass::query()->create(['name' => $name]);
    }

    private function resolveOwner(string $name): User
    {
        $user = User::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($user) {
            return $user;
        }
        $parts = preg_split('/\s+/', $name, 2);
        $firstname = $parts[0] ?? $name;
        $lastname = ($parts[1] ?? '') !== '' ? $parts[1] : '-';

        return User::query()->create([
            'name' => $name, 'firstname' => $firstname, 'lastname' => $lastname, 'login_enabled' => false,
        ]);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetImportTest.php`
Expected: PASS (8). Then full suite `ddev exec ./vendor/bin/phpunit` → green.

- [ ] **Step 6: Commit**

```bash
git add app/Support/Assets/AssetImport.php app/Support/Assets/RowImportException.php tests/Feature/App/AssetImportTest.php
git commit -m "feat(assets): AssetImport service (find-or-create, enum/date resolution, per-row errors)"
```

---

### Task 3: Import controller, request, route

**Files:**
- Create: `app/Http/Controllers/App/AssetImportController.php`, `app/Http/Requests/App/AssetImportRequest.php`
- Modify: `routes/web.php`, `app/Http/Controllers/App/AssetController.php` (pass `importResult` from session)
- Test: `tests/Feature/App/AssetImportControllerTest.php`, extend `tests/Feature/App/AssetControllerTest.php` (importResult prop)

**Interfaces:**
- Consumes: `AssetImport`, `AssetCsv::HEADERS`.
- Produces: route `app.assets.import` (POST `/app/assets/import`); redirect back to index with flashed `importResult`; `AssetController@index` exposes `importResult` prop.

- [ ] **Step 1: Write the failing tests**

```php
<?php // tests/Feature/App/AssetImportControllerTest.php
namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AssetImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User { return User::factory()->create(['login_enabled' => true]); }

    private function csv(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('assets.csv', $body);
    }

    private const HEADER = "id,state,asset_type,manufacturer,model,place,owner,serial_number,buy_date,guarantee_end,buy_price,buy_type,tags\n";

    public function test_import_creates_assets_and_flashes_summary(): void
    {
        $body = self::HEADER
            .",in-use,Laptop,Acme,X1,Office,,SN-A,2024-01-05,,100,,\n"
            .",storage,Monitor,Dell,U27,,,SN-B,,,,,\n";

        $this->actingAs($this->actor())
            ->post('/app/assets/import', ['file' => $this->csv($body)])
            ->assertRedirect('/app/assets')
            ->assertSessionHas('importResult', fn ($r) => $r['imported'] === 2 && $r['failed'] === []);

        $this->assertSame(2, Asset::count());
    }

    public function test_import_reports_failed_rows(): void
    {
        $body = self::HEADER
            .",in-use,Laptop,Acme,X1,,,SN-A,,,,,\n"
            .",nonsense,Laptop,Acme,X1,,,SN-B,,,,,\n";

        $this->actingAs($this->actor())
            ->post('/app/assets/import', ['file' => $this->csv($body)])
            ->assertSessionHas('importResult', fn ($r) => $r['imported'] === 1 && count($r['failed']) === 1);
    }

    public function test_import_rejects_wrong_header(): void
    {
        $body = "foo,bar\n1,2\n";
        $this->actingAs($this->actor())
            ->post('/app/assets/import', ['file' => $this->csv($body)])
            ->assertSessionHasErrors('file');
        $this->assertSame(0, Asset::count());
    }

    public function test_import_rejects_over_row_cap(): void
    {
        $rows = str_repeat(",in-use,Laptop,Acme,X1,,,S,,,,,\n", 2001);
        $this->actingAs($this->actor())
            ->post('/app/assets/import', ['file' => $this->csv(self::HEADER.$rows)])
            ->assertSessionHasErrors('file');
        $this->assertSame(0, Asset::count());
    }

    public function test_import_rejects_bad_mime(): void
    {
        $this->actingAs($this->actor())
            ->post('/app/assets/import', ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file');
    }

    public function test_import_requires_auth(): void
    {
        $this->post('/app/assets/import', [])->assertRedirect();
    }
}
```

Extend `tests/Feature/App/AssetControllerTest.php`:

```php
public function test_index_exposes_flashed_import_result(): void
{
    $this->actingAs(User::factory()->create(['login_enabled' => true]))
        ->withSession(['importResult' => ['imported' => 3, 'failed' => []]])
        ->get('/app/assets')
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p
            ->component('assets/index')
            ->where('importResult.imported', 3)
            ->etc());
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetImportControllerTest.php tests/Feature/App/AssetControllerTest.php`
Expected: FAIL — route/controller undefined; index lacks `importResult`.

- [ ] **Step 3: Create the request**

```php
<?php // app/Http/Requests/App/AssetImportRequest.php
namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class AssetImportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']];
    }
}
```

- [ ] **Step 4: Create the controller**

```php
<?php // app/Http/Controllers/App/AssetImportController.php
namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetImportRequest;
use App\Support\Assets\AssetCsv;
use App\Support\Assets\AssetImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AssetImportController extends Controller
{
    private const ROW_CAP = 2000;

    public function store(AssetImportRequest $request, AssetImport $import): RedirectResponse
    {
        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        $header = fgetcsv($handle);
        if ($header === false || ! $this->headerMatches($header)) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'The CSV header must be exactly: '.implode(', ', AssetCsv::HEADERS).'.',
            ]);
        }

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || (count($line) === 1 && trim((string) $line[0]) === '')) {
                continue; // skip blank lines
            }
            $rows[] = array_combine(AssetCsv::HEADERS, array_pad(array_slice($line, 0, count(AssetCsv::HEADERS)), count(AssetCsv::HEADERS), ''));
            if (count($rows) > self::ROW_CAP) {
                fclose($handle);
                throw ValidationException::withMessages([
                    'file' => 'Too many rows (max '.self::ROW_CAP.'). Split the file; a queued import is a later feature.',
                ]);
            }
        }
        fclose($handle);

        $result = $import->import($rows);

        return to_route('app.assets.index')->with('importResult', $result);
    }

    /** @param array<int, string|null> $header */
    private function headerMatches(array $header): bool
    {
        // Trim BOM/whitespace on each cell, then require an exact, order-sensitive match.
        $normalized = array_map(fn ($h) => trim((string) $h, " \t\n\r\0\x0B\u{FEFF}"), $header);

        return $normalized === AssetCsv::HEADERS;
    }
}
```

(`headerMatches` trims each cell — including a possible UTF-8 BOM on the first cell — then compares to `AssetCsv::HEADERS` exactly, order-sensitive.)

- [ ] **Step 5: Register the route + expose `importResult`**

In `routes/web.php`, inside the authenticated `/app` group (near the export route, both **before** `Route::resource('assets', ...)`):

```php
use App\Http\Controllers\App\AssetImportController;
Route::post('assets/import', [AssetImportController::class, 'store'])->name('assets.import');
```

In `app/Http/Controllers/App/AssetController.php` `index()`, add to the `Inertia::render('assets/index', [...])` array:

```php
'importResult' => $request->session()->get('importResult'),
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/AssetImportControllerTest.php tests/Feature/App/AssetControllerTest.php`
Expected: PASS. Then full suite → green.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/App/AssetImportController.php app/Http/Requests/App/AssetImportRequest.php routes/web.php app/Http/Controllers/App/AssetController.php tests/Feature/App/AssetImportControllerTest.php tests/Feature/App/AssetControllerTest.php
git commit -m "feat(assets): CSV import controller (header + row-cap validation, flashed summary)"
```

---

### Task 4: Import UI (dialog + result panel)

**Files:**
- Create: `resources/js/components/assets/asset-import-dialog.tsx`
- Modify: `resources/js/pages/assets/index.tsx`
- Test: `resources/js/components/assets/__tests__/asset-import-dialog.test.tsx`

**Interfaces:**
- Consumes: Inertia `useForm`, shadcn `Dialog`/`Button`/`Card`; the `importResult` prop from Task 3.
- Produces: `AssetImportDialog` (`{ }` — self-contained, posts to `/app/assets/import`); the index renders it + a result panel from `importResult`.

- [ ] **Step 1: Write the failing test**

```tsx
// resources/js/components/assets/__tests__/asset-import-dialog.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { AssetImportDialog } from '../asset-import-dialog';

describe('AssetImportDialog', () => {
    it('opens the dialog with a file input', () => {
        render(<AssetImportDialog />);
        fireEvent.click(screen.getByRole('button', { name: /import/i }));
        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByLabelText(/file/i)).toHaveAttribute('type', 'file');
    });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev exec pnpm exec vitest run resources/js/components/assets/__tests__/asset-import-dialog.test.tsx`
Expected: FAIL — module missing.

- [ ] **Step 3: Implement the dialog**

```tsx
// resources/js/components/assets/asset-import-dialog.tsx
import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger, DialogDescription } from '@/components/ui/dialog';
import { FormError } from '@/components/form/form-error';

export function AssetImportDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ file: File | null }>({ file: null });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/app/assets/import', { forceFormData: true, onSuccess: () => { setOpen(false); form.reset(); } });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild><Button variant="outline">Import</Button></DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Import assets</DialogTitle>
                    <DialogDescription>
                        CSV with columns: id, state, asset_type, manufacturer, model, place, owner, serial_number,
                        buy_date, guarantee_end, buy_price, buy_type, tags. Use Export as a template.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="import_file">File</Label>
                        <input id="import_file" type="file" accept=".csv,.txt"
                            onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                            className="block w-full text-sm file:mr-3 file:rounded-md file:border file:bg-secondary file:px-3 file:py-1.5" />
                        <FormError message={form.errors.file} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
                        <Button type="submit" disabled={form.processing || !form.data.file}>Import</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `ddev exec pnpm exec vitest run resources/js/components/assets/__tests__/asset-import-dialog.test.tsx`
Expected: PASS.

- [ ] **Step 5: Wire the dialog + result panel into the index**

In `resources/js/pages/assets/index.tsx`:
- Import `{ AssetImportDialog }` and (for the result) `Card`, `CardContent` from `@/components/ui/card`.
- Add `importResult?: { imported: number; failed: { row: number; message: string }[] } | null` to the page `Props` and destructure it.
- In the toolbar, add `<AssetImportDialog />` next to Export/New.
- Above the `DataTable`, render the result panel when `importResult` is present:

```tsx
{importResult && (
    <Card className="mb-4">
        <CardContent className="py-4 text-sm">
            <div className="font-medium">Imported {importResult.imported}{importResult.failed.length ? `, ${importResult.failed.length} failed` : ''}.</div>
            {importResult.failed.length > 0 && (
                <ul className="mt-2 max-h-48 space-y-1 overflow-y-auto text-muted-foreground">
                    {importResult.failed.map((f) => <li key={f.row}>Row {f.row}: {f.message}</li>)}
                </ul>
            )}
        </CardContent>
    </Card>
)}
```

- [ ] **Step 6: Build + full JS suite**

Run: `ddev exec pnpm run build` → succeeds, no type errors.
Run: `ddev exec pnpm run test` → green.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/assets/asset-import-dialog.tsx resources/js/pages/assets/index.tsx resources/js/components/assets/__tests__/asset-import-dialog.test.tsx
git commit -m "feat(assets): import dialog + result panel on the assets index"
```

---

### Task 5: Final verification

**Files:** none (verification only).

- [ ] **Step 1: Full PHP suite** — `ddev exec php artisan test` → green.
- [ ] **Step 2: Full JS suite** — `ddev exec pnpm run test` → green.
- [ ] **Step 3: Build** — `ddev exec pnpm run build` → succeeds.
- [ ] **Step 4: Manual verify** — on the assets index: **Export** downloads a CSV of the (filtered) list; edit the CSV / add a row, **Import** it → the result panel shows imported/failed counts (+ per-row errors for bad rows); re-export includes the new rows. `/app-old` Filament import/export still work.
- [ ] **Step 5:** Commit anything outstanding (releases automated — skip manual CHANGELOG).

---

## Self-Review Notes

- **Spec coverage:** canonical `AssetCsv::HEADERS` → Task 1; export (filtered, streamed, resolved values) + shared `AssetTableQuery` + `TableQuery::get()` → Task 1; `AssetImport` service (enum value/label, ISO+`d.m.Y` dates, find-or-create, model-needs-manufacturer, auto-owner+splitName, id-collision, tags, per-row failures) → Task 2; import controller (header + 2000-cap validation, flashed `importResult`) + index exposure → Task 3; import dialog + result panel → Task 4; verification → Task 5.
- **Type consistency:** `importResult` shape (Task 3 controller / Task 4 prop) = `{imported:number, failed:{row:number,message:string}[]}`; matches the service return (Task 2). `AssetCsv::HEADERS` order is the single source for export write + import parse.
- **Routing:** `assets/export` and `assets/import` declared BEFORE `Route::resource('assets', ...)` so they aren't shadowed by `{asset}`.
- **DRY:** the index refactor to `AssetTableQuery::configure()` keeps export filters in lockstep with the index (index tests guard the refactor). `place` is eager-loaded for export (not joined) — its cell uses `optional($asset->place)->name`.
- **Row numbers:** service reports `row = index + 2` (header line + 1-based data), asserted by the multi-row test.
- **Deferred:** queued processing, failed-rows CSV, mapping UI, update-by-id; Filament importer/exporter untouched.

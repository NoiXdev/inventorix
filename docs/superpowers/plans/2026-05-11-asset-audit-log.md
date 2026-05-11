# Asset & Incident Audit Log Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an immutable activity log to `Asset` and `Incident`, with a per-asset timeline tab in Filament that merges incident events and lets users attach free-text notes.

**Architecture:** `spatie/laravel-activitylog` captures field changes automatically via the `LogsActivity` trait on both models. An `AssetObserver` writes additional semantic events (`owner_changed`, `place_changed`, `state_changed`) on top of the generic `updated` row. A `HistoryRelationManager` on `AssetResource` displays a deduplicated merged timeline (asset events + incident events whose parent is the same asset) and exposes an "Add note" header action.

**Tech Stack:** PHP 8.3+, Laravel 13, Filament 5, PHPUnit 12, DDEV. New dependency: `spatie/laravel-activitylog` (install latest release compatible with Laravel 13).

**Spec:** `docs/superpowers/specs/2026-05-11-asset-audit-log-design.md`

**All commands run through DDEV** per project convention (see `CLAUDE.md`). Use `ddev composer …`, `ddev artisan …`, `ddev exec php artisan test …`.

---

## File Inventory

**New files:**
- `database/migrations/<ts>_create_activity_log_table.php` (published by package, then edited)
- `config/activitylog.php` (published by package, accepted as-is)
- `database/factories/{AssetType,Manufacturer,AssetModel,Place,Asset,Incident}Factory.php`
- `app/Observers/AssetObserver.php`
- `app/Support/History/SummaryBuilder.php`
- `app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php`
- `lang/de/history.php`
- `tests/Feature/ActivityLog/AssetActivityLogTest.php`
- `tests/Feature/ActivityLog/IncidentActivityLogTest.php`
- `tests/Feature/Filament/HistoryRelationManagerTest.php`
- `tests/Unit/History/SummaryBuilderTest.php`

**Modified files:**
- `composer.json` / `composer.lock` (via `composer require`)
- `app/Models/User.php` — add `CausesActivity` trait
- `app/Models/Asset.php` — add `LogsActivity`, `getActivitylogOptions()`, `#[ObservedBy(AssetObserver::class)]`, `HasFactory`
- `app/Models/Incident.php` — add `LogsActivity`, `getActivitylogOptions()`, `HasFactory`
- `app/Models/AssetType.php`, `Manufacturer.php`, `AssetModel.php`, `Place.php` — add `HasFactory` trait
- `app/Filament/App/Resources/Assets/AssetResource.php` — register `HistoryRelationManager`

**Note on translations:** The project ships only `lang/de/` today; there is no `lang/en/`. The plan creates only `lang/de/history.php`, consistent with the existing pattern. The spec's "{de,en}" wording is therefore narrowed to de-only.

**Note on Incident PK:** Incident uses a bigint `$table->id()` while Asset/User use UUIDs. The activity_log `subject_id` and `causer_id` columns are `string(36)` to hold both — the spec was corrected to reflect this before plan creation.

---

## Task 1: Install package and create UUID-compatible migration

**Files:**
- Modify: `composer.json`, `composer.lock` (via `composer require`)
- Create: `database/migrations/<timestamp>_create_activity_log_table.php` (published, then edited)
- Create: `config/activitylog.php` (published)

- [ ] **Step 1: Install the package via DDEV composer**

Run:
```bash
ddev composer require spatie/laravel-activitylog
```

Expected: composer adds `spatie/laravel-activitylog` to `require`, updates `composer.lock`, no errors.

- [ ] **Step 2: Publish the migration**

Run:
```bash
ddev artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
```

Expected: a new file appears at `database/migrations/<timestamp>_create_activity_log_table.php`.

- [ ] **Step 3: Replace the `morphs` lines with explicit string columns**

Open the published migration. It contains a `Schema::create('activity_log', function (Blueprint $table) { … })` block. Replace the entire block body with:

```php
$table->bigIncrements('id');
$table->string('log_name')->nullable()->index();
$table->text('description');
$table->string('subject_type', 255)->nullable();
$table->string('subject_id', 36)->nullable();
$table->index(['subject_type', 'subject_id'], 'subject');
$table->string('event')->nullable();
$table->string('causer_type', 255)->nullable();
$table->string('causer_id', 36)->nullable();
$table->index(['causer_type', 'causer_id'], 'causer');
$table->json('properties')->nullable();
$table->uuid('batch_uuid')->nullable();
$table->timestamps();
$table->index(['subject_type', 'subject_id', 'created_at'], 'subject_timeline');
```

Keep the migration's `down()` method (`Schema::dropIfExists('activity_log');`) unchanged.

- [ ] **Step 4: Publish the config**

Run:
```bash
ddev artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
```

Expected: `config/activitylog.php` is created. No edits required — defaults are fine.

- [ ] **Step 5: Run the migration**

Run:
```bash
ddev artisan migrate
```

Expected: the `activity_log` table is created. `ddev artisan migrate:status` should show the new migration as ran.

- [ ] **Step 6: Verify the table accepts both UUID and bigint as subject_id**

Run:
```bash
ddev artisan tinker --execute="\Illuminate\Support\Facades\DB::table('activity_log')->insert([['log_name'=>'t','description'=>'x','subject_type'=>'A','subject_id'=>'01H7QZ7XYZABC0123456789012345','created_at'=>now(),'updated_at'=>now()],['log_name'=>'t','description'=>'x','subject_type'=>'B','subject_id'=>'42','created_at'=>now(),'updated_at'=>now()]]); echo \Illuminate\Support\Facades\DB::table('activity_log')->count();"
```

Expected output: `2` (both rows inserted; the UUID and the bigint-as-string coexist). Then clean up:

```bash
ddev artisan tinker --execute="\Illuminate\Support\Facades\DB::table('activity_log')->where('log_name','t')->delete();"
```

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock config/activitylog.php database/migrations
git commit -m "feat(audit-log): install spatie/laravel-activitylog and add UUID-compatible migration"
```

---

## Task 2: Add model factories for upstream entities and Asset/Incident

**Why this task exists:** Tests in later tasks need `Asset::factory()->create()` and `Incident::factory()->create()`. Asset depends on `AssetType`, `Manufacturer`, optionally `AssetModel`, `Place`, `User`. Only `UserFactory` exists today.

**Files:**
- Create: `database/factories/AssetTypeFactory.php`
- Create: `database/factories/ManufacturerFactory.php`
- Create: `database/factories/AssetModelFactory.php`
- Create: `database/factories/PlaceFactory.php`
- Create: `database/factories/AssetFactory.php`
- Create: `database/factories/IncidentFactory.php`
- Modify: `app/Models/AssetType.php`, `Manufacturer.php`, `AssetModel.php`, `Place.php`, `Asset.php`, `Incident.php` — add `use HasFactory;`

- [ ] **Step 1: Create `AssetTypeFactory`**

`database/factories/AssetTypeFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\AssetType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetType> */
class AssetTypeFactory extends Factory
{
    protected $model = AssetType::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->word()];
    }
}
```

- [ ] **Step 2: Create `ManufacturerFactory`** (same shape as Step 1, model `Manufacturer`, single `name` field).

`database/factories/ManufacturerFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Manufacturer> */
class ManufacturerFactory extends Factory
{
    protected $model = Manufacturer::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->company()];
    }
}
```

- [ ] **Step 3: Create `AssetModelFactory`**

`database/factories/AssetModelFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\AssetModel;
use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetModel> */
class AssetModelFactory extends Factory
{
    protected $model = AssetModel::class;

    public function definition(): array
    {
        return [
            'name'            => fake()->unique()->word(),
            'manufacturer_id' => Manufacturer::factory(),
        ];
    }
}
```

- [ ] **Step 4: Create `PlaceFactory`**

`database/factories/PlaceFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Place> */
class PlaceFactory extends Factory
{
    protected $model = Place::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->city()];
    }
}
```

- [ ] **Step 5: Create `AssetFactory`**

`database/factories/AssetFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\AssetType;
use App\Models\Manufacturer;
use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'state'           => AssetState::NEW->value,
            'asset_type_id'   => AssetType::factory(),
            'manufacturer_id' => Manufacturer::factory(),
            'model_id'        => AssetModel::factory(),
            'owner_id'        => User::factory(),
            'place_id'        => Place::factory(),
            'serial_number'   => fake()->bothify('SN-#####??'),
            'buy_date'        => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'buy_price'       => fake()->randomFloat(2, 100, 5000),
            'guarantee_end'   => fake()->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            'invoice'         => fake()->bothify('INV-####'),
        ];
    }
}
```

- [ ] **Step 6: Create `IncidentFactory`**

`database/factories/IncidentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Incident> */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        return [
            'asset_id'    => Asset::factory(),
            'title'       => fake()->sentence(3),
            'notes'       => fake()->paragraph(),
            'open_date'   => now(),
            'closed_date' => null,
        ];
    }
}
```

- [ ] **Step 7: Add `HasFactory` to each model**

Edit each of `AssetType.php`, `Manufacturer.php`, `AssetModel.php`, `Place.php`, `Asset.php`, `Incident.php`. In each, add:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;
```

and add `HasFactory` to the existing `use` line inside the class. Example for `Asset`:

```php
use HasUuids, HasTags, HasFactory;
```

For `Incident` (which currently has no `use` line inside the class), add:

```php
use HasFactory;
```

- [ ] **Step 8: Smoke-test that factories work end-to-end**

Run:
```bash
ddev exec php artisan tinker --execute="echo \App\Models\Asset::factory()->create()->id; echo PHP_EOL; echo \App\Models\Incident::factory()->create()->id;"
```

Expected: prints a 36-char UUID (Asset), then an integer (Incident). No exceptions.

Clean up the created rows:
```bash
ddev exec php artisan tinker --execute="\App\Models\Incident::query()->delete(); \App\Models\Asset::query()->delete(); \App\Models\AssetModel::query()->delete(); \App\Models\Manufacturer::query()->delete(); \App\Models\AssetType::query()->delete(); \App\Models\Place::query()->delete(); \App\Models\User::query()->delete();"
```

- [ ] **Step 9: Commit**

```bash
git add database/factories app/Models
git commit -m "test(factories): add factories for AssetType, Manufacturer, AssetModel, Place, Asset, Incident"
```

---

## Task 3: Add `CausesActivity` trait to User

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: Add the trait import and `use` line**

In `app/Models/User.php`, add to the import block:

```php
use Spatie\Activitylog\Models\Concerns\CausesActivity;
```

In the class `use` line, append `CausesActivity`:

```php
use HasFactory, Notifiable, HasUuids, CausesActivity;
```

- [ ] **Step 2: Verify nothing breaks**

Run:
```bash
ddev exec php artisan test --filter=MicrosoftLoginTest
```

Expected: existing auth tests still pass.

- [ ] **Step 3: Commit**

```bash
git add app/Models/User.php
git commit -m "feat(audit-log): add CausesActivity trait to User"
```

---

## Task 4: Incident — `LogsActivity` trait and tests

**Files:**
- Modify: `app/Models/Incident.php`
- Create: `tests/Feature/ActivityLog/IncidentActivityLogTest.php`

- [ ] **Step 1: Write the failing test file**

`tests/Feature/ActivityLog/IncidentActivityLogTest.php`:

```php
<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class IncidentActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_incident_logs_a_created_activity(): void
    {
        $incident = Incident::factory()->create();

        $activity = Activity::query()
            ->where('subject_type', Incident::class)
            ->where('subject_id', (string) $incident->id)
            ->where('description', 'created')
            ->first();

        $this->assertNotNull($activity, 'expected a created activity row');
        $this->assertSame('incident', $activity->log_name);
    }

    public function test_updating_logged_fields_writes_an_updated_activity_with_dirty_only(): void
    {
        $incident = Incident::factory()->create(['title' => 'Old']);

        $incident->update(['title' => 'New', 'notes' => 'fresh']);

        $activity = Activity::query()
            ->where('subject_id', (string) $incident->id)
            ->where('description', 'updated')
            ->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('New', $activity->properties['attributes']['title']);
        $this->assertSame('Old', $activity->properties['old']['title']);
        $this->assertArrayHasKey('notes', $activity->properties['attributes']);
        $this->assertArrayNotHasKey('id', $activity->properties['attributes']);
    }

    public function test_deleting_writes_a_deleted_activity(): void
    {
        $incident = Incident::factory()->create();
        $id = $incident->id;

        $incident->delete();

        $this->assertTrue(
            Activity::query()
                ->where('subject_id', (string) $id)
                ->where('description', 'deleted')
                ->exists(),
            'expected a deleted activity row',
        );
    }

    public function test_authenticated_user_is_recorded_as_causer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $incident = Incident::factory()->create();

        $activity = Activity::query()
            ->where('subject_id', (string) $incident->id)
            ->where('description', 'created')
            ->first();

        $this->assertSame((string) $user->id, (string) $activity->causer_id);
        $this->assertSame(User::class, $activity->causer_type);
    }

    public function test_cli_changes_leave_causer_null(): void
    {
        $incident = Incident::factory()->create();
        $this->assertNull(
            Activity::query()
                ->where('subject_id', (string) $incident->id)
                ->where('description', 'created')
                ->first()
                ->causer_id,
        );
    }
}
```

- [ ] **Step 2: Run the test, confirm all 5 fail**

Run:
```bash
ddev exec php artisan test --filter=IncidentActivityLogTest
```

Expected: 5 failures (no `created` rows because trait isn't applied yet).

- [ ] **Step 3: Add `LogsActivity` to the Incident model**

Edit `app/Models/Incident.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['asset_id', 'notes', 'title', 'open_date', 'closed_date'])]
class Incident extends Model
{
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'open_date'   => 'datetime',
            'closed_date' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'notes', 'open_date', 'closed_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('incident');
    }
}
```

(Keep the `HasFactory` already added in Task 2.)

- [ ] **Step 4: Run the test, confirm all 5 pass**

Run:
```bash
ddev exec php artisan test --filter=IncidentActivityLogTest
```

Expected: 5 passing.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Incident.php tests/Feature/ActivityLog/IncidentActivityLogTest.php
git commit -m "feat(audit-log): log Incident lifecycle to activity_log"
```

---

## Task 5: Asset — `LogsActivity` trait and tests

**Files:**
- Modify: `app/Models/Asset.php`
- Create: `tests/Feature/ActivityLog/AssetActivityLogTest.php`

- [ ] **Step 1: Write the failing test file**

`tests/Feature/ActivityLog/AssetActivityLogTest.php`:

```php
<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AssetActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_asset_logs_a_created_activity(): void
    {
        $asset = Asset::factory()->create();

        $activity = Activity::query()
            ->where('subject_type', Asset::class)
            ->where('subject_id', $asset->id)
            ->where('description', 'created')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('asset', $activity->log_name);
    }

    public function test_updating_non_semantic_fields_writes_only_dirty_fields(): void
    {
        $asset = Asset::factory()->create(['serial_number' => 'SN-OLD']);

        $asset->update(['serial_number' => 'SN-NEW', 'invoice' => 'INV-99']);

        $activity = Activity::query()
            ->where('subject_id', $asset->id)
            ->where('description', 'updated')
            ->latest('id')->first();

        $this->assertSame('SN-NEW', $activity->properties['attributes']['serial_number']);
        $this->assertSame('SN-OLD', $activity->properties['old']['serial_number']);
        $this->assertArrayHasKey('invoice', $activity->properties['attributes']);
        $this->assertArrayNotHasKey('id', $activity->properties['attributes']);
    }

    public function test_deleting_writes_a_deleted_activity(): void
    {
        $asset = Asset::factory()->create();
        $id = $asset->id;

        $asset->delete();

        $this->assertTrue(
            Activity::query()
                ->where('subject_id', $id)
                ->where('description', 'deleted')
                ->exists(),
        );
    }

    public function test_authenticated_user_is_recorded_as_causer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $asset = Asset::factory()->create();

        $activity = Activity::query()
            ->where('subject_id', $asset->id)
            ->where('description', 'created')
            ->first();

        $this->assertSame((string) $user->id, (string) $activity->causer_id);
    }
}
```

- [ ] **Step 2: Run the test, confirm all 4 fail**

Run:
```bash
ddev exec php artisan test --filter=AssetActivityLogTest
```

Expected: 4 failures.

- [ ] **Step 3: Add `LogsActivity` to the Asset model**

Edit `app/Models/Asset.php`. Add imports:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
```

Update the in-class `use` line:

```php
use HasUuids, HasTags, HasFactory, LogsActivity;
```

Append this method to the class:

```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logOnly([
            'asset_type_id',
            'model_id',
            'owner_id',
            'place_id',
            'serial_number',
            'buy_date',
            'buy_type',
            'buy_price',
            'guarantee_end',
            'invoice',
            'state',
        ])
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs()
        ->useLogName('asset');
}
```

(Note: `manufacturer_id` is intentionally absent — migration `2025_06_03_070938_new_manufacturer_model_logic.php` dropped that column from `assets` and moved the relationship to `asset_models`. The Asset Filament resource currently has no manufacturer field; manufacturer info is read transitively via `asset.model.manufacturer`.)

- [ ] **Step 4: Run the test, confirm all 4 pass**

Run:
```bash
ddev exec php artisan test --filter=AssetActivityLogTest
```

Expected: 4 passing.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Asset.php tests/Feature/ActivityLog/AssetActivityLogTest.php
git commit -m "feat(audit-log): log Asset lifecycle to activity_log"
```

---

## Task 6: AssetObserver for semantic owner/place/state events

**Files:**
- Create: `app/Observers/AssetObserver.php`
- Modify: `app/Models/Asset.php` — add `#[ObservedBy(AssetObserver::class)]` class attribute
- Extend: `tests/Feature/ActivityLog/AssetActivityLogTest.php`

- [ ] **Step 1: Add the failing tests to `AssetActivityLogTest`**

Append these three methods inside the existing `AssetActivityLogTest` class:

```php
public function test_owner_change_writes_an_owner_changed_activity(): void
{
    $asset = Asset::factory()->create();
    $newOwner = User::factory()->create();
    $oldOwnerId = $asset->owner_id;

    $asset->update(['owner_id' => $newOwner->id]);

    $activity = Activity::query()
        ->where('subject_id', $asset->id)
        ->where('description', 'owner_changed')
        ->first();

    $this->assertNotNull($activity);
    $this->assertSame($oldOwnerId, $activity->properties['from']);
    $this->assertSame($newOwner->id, $activity->properties['to']);
}

public function test_place_change_writes_a_place_changed_activity(): void
{
    $asset = Asset::factory()->create();
    $newPlace = \App\Models\Place::factory()->create();
    $oldPlaceId = $asset->place_id;

    $asset->update(['place_id' => $newPlace->id]);

    $activity = Activity::query()
        ->where('subject_id', $asset->id)
        ->where('description', 'place_changed')
        ->first();

    $this->assertNotNull($activity);
    $this->assertSame($oldPlaceId, $activity->properties['from']);
    $this->assertSame($newPlace->id, $activity->properties['to']);
}

public function test_state_change_writes_a_state_changed_activity(): void
{
    $asset = Asset::factory()->create(['state' => AssetState::NEW->value]);

    $asset->update(['state' => AssetState::IN_USE->value]);

    $activity = Activity::query()
        ->where('subject_id', $asset->id)
        ->where('description', 'state_changed')
        ->first();

    $this->assertNotNull($activity);
    $this->assertSame(AssetState::NEW->value, $activity->properties['from']);
    $this->assertSame(AssetState::IN_USE->value, $activity->properties['to']);
}

public function test_a_semantic_change_also_keeps_the_generic_updated_row(): void
{
    $asset = Asset::factory()->create();
    $newOwner = User::factory()->create();

    $asset->update(['owner_id' => $newOwner->id]);

    $this->assertSame(
        1,
        Activity::query()
            ->where('subject_id', $asset->id)
            ->where('description', 'updated')
            ->count(),
        'generic updated row should still be written',
    );
    $this->assertSame(
        1,
        Activity::query()
            ->where('subject_id', $asset->id)
            ->where('description', 'owner_changed')
            ->count(),
    );
}
```

- [ ] **Step 2: Run, confirm 4 new failures**

Run:
```bash
ddev exec php artisan test --filter=AssetActivityLogTest
```

Expected: previously passing 4 still pass; 4 new tests fail.

- [ ] **Step 3: Create the observer**

`app/Observers/AssetObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\Asset;

class AssetObserver
{
    private const FIELD_EVENT_MAP = [
        'owner_id' => 'owner_changed',
        'place_id' => 'place_changed',
        'state'    => 'state_changed',
    ];

    public function updating(Asset $asset): void
    {
        foreach (self::FIELD_EVENT_MAP as $field => $event) {
            if (! $asset->isDirty($field)) {
                continue;
            }

            activity('asset')
                ->performedOn($asset)
                ->causedBy(auth()->user())
                ->withProperties([
                    'from' => $asset->getOriginal($field),
                    'to'   => $asset->getAttribute($field),
                ])
                ->log($event);
        }
    }
}
```

- [ ] **Step 4: Attach the observer to Asset via attribute**

Edit `app/Models/Asset.php`. Add import:

```php
use App\Observers\AssetObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
```

Add the attribute on the class declaration line (immediately above `class Asset`):

```php
#[ObservedBy(AssetObserver::class)]
class Asset extends Model
```

- [ ] **Step 5: Run, confirm all 8 tests pass**

Run:
```bash
ddev exec php artisan test --filter=AssetActivityLogTest
```

Expected: 8 passing.

- [ ] **Step 6: Commit**

```bash
git add app/Observers/AssetObserver.php app/Models/Asset.php tests/Feature/ActivityLog/AssetActivityLogTest.php
git commit -m "feat(audit-log): record semantic owner/place/state changes via AssetObserver"
```

---

## Task 7: German translation file

**Files:**
- Create: `lang/de/history.php`

(No tests — translation files are exercised by Task 9 onward.)

- [ ] **Step 1: Create the file**

`lang/de/history.php`:

```php
<?php

return [
    'label'        => 'Historie',
    'label-plural' => 'Historie',
    'tab'          => 'Historie',
    'empty_state'  => 'Noch keine Historie — Änderungen an diesem Gegenstand erscheinen hier.',

    'add_note'      => 'Notiz hinzufügen',
    'add_note_body' => 'Notiz',
    'add_note_save' => 'Speichern',

    'event' => [
        'created'        => 'Erstellt',
        'updated'        => 'Geändert',
        'deleted'        => 'Gelöscht',
        'note'           => 'Notiz',
        'owner_changed'  => 'Besitzer geändert',
        'place_changed'  => 'Standort geändert',
        'state_changed'  => 'Status geändert',
    ],

    'summary' => [
        'created'         => 'Erstellt',
        'deleted'         => 'Gelöscht',
        'fields_changed'  => ':count Feld(er) geändert',
        'set'             => ':attr gesetzt auf :value',
        'cleared'         => ':attr geleert',
        'incident_prefix' => 'Vorfall #:id: ',
        'incident_removed' => 'Vorfall (gelöscht): ',
    ],

    'causer' => [
        'system'        => 'System',
        'former_user'   => 'System (ehemaliger Nutzer)',
    ],
];
```

- [ ] **Step 2: Sanity-check the file loads**

Run:
```bash
ddev exec php artisan tinker --execute="echo trans('history.label'); echo PHP_EOL; echo trans('history.event.owner_changed');"
```

Expected: prints `Historie` then `Besitzer geändert`. (If the app's default locale is not `de`, set `APP_LOCALE=de` in `.env` and re-run.)

- [ ] **Step 3: Commit**

```bash
git add lang/de/history.php
git commit -m "feat(audit-log): add German translations for history feature"
```

---

## Task 8: SummaryBuilder helper

**Purpose:** Convert a raw `Activity` row into the one-line summary string the relation manager displays, including incident-prefix and "System" / "System (former user)" fallbacks.

**Files:**
- Create: `app/Support/History/SummaryBuilder.php`
- Create: `tests/Unit/History/SummaryBuilderTest.php`

- [ ] **Step 1: Write the failing tests**

`tests/Unit/History/SummaryBuilderTest.php`:

```php
<?php

namespace Tests\Unit\History;

use App\Enums\AssetState;
use App\Models\Asset;
use App\Models\Incident;
use App\Models\Place;
use App\Models\User;
use App\Support\History\SummaryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SummaryBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function buildActivity(array $overrides): Activity
    {
        return Activity::create(array_merge([
            'log_name'    => 'asset',
            'description' => 'updated',
            'subject_type'=> Asset::class,
            'subject_id'  => (string) \Illuminate\Support\Str::uuid(),
            'properties'  => [],
        ], $overrides));
    }

    public function test_created_summary(): void
    {
        $activity = $this->buildActivity(['description' => 'created']);
        $this->assertSame(trans('history.summary.created'), (new SummaryBuilder())->forActivity($activity));
    }

    public function test_deleted_summary(): void
    {
        $activity = $this->buildActivity(['description' => 'deleted']);
        $this->assertSame(trans('history.summary.deleted'), (new SummaryBuilder())->forActivity($activity));
    }

    public function test_updated_summary_counts_changed_fields(): void
    {
        $activity = $this->buildActivity([
            'description' => 'updated',
            'properties'  => ['attributes' => ['a' => 1, 'b' => 2, 'c' => 3], 'old' => ['a' => 0, 'b' => 0, 'c' => 0]],
        ]);
        $this->assertSame(
            trans('history.summary.fields_changed', ['count' => 3]),
            (new SummaryBuilder())->forActivity($activity),
        );
    }

    public function test_owner_changed_summary_resolves_names(): void
    {
        $old = User::factory()->create(['name' => 'Anna']);
        $new = User::factory()->create(['name' => 'Lukas']);
        $activity = $this->buildActivity([
            'description' => 'owner_changed',
            'properties'  => ['from' => $old->id, 'to' => $new->id],
        ]);
        $this->assertSame('Anna → Lukas', (new SummaryBuilder())->forActivity($activity));
    }

    public function test_owner_changed_summary_handles_null(): void
    {
        $new = User::factory()->create(['name' => 'Lukas']);
        $activity = $this->buildActivity([
            'description' => 'owner_changed',
            'properties'  => ['from' => null, 'to' => $new->id],
        ]);
        $this->assertSame('— → Lukas', (new SummaryBuilder())->forActivity($activity));
    }

    public function test_place_changed_summary_resolves_names(): void
    {
        $old = Place::factory()->create(['name' => 'Lager A']);
        $new = Place::factory()->create(['name' => 'Lager B']);
        $activity = $this->buildActivity([
            'description' => 'place_changed',
            'properties'  => ['from' => $old->id, 'to' => $new->id],
        ]);
        $this->assertSame('Lager A → Lager B', (new SummaryBuilder())->forActivity($activity));
    }

    public function test_state_changed_summary_uses_enum_labels(): void
    {
        $activity = $this->buildActivity([
            'description' => 'state_changed',
            'properties'  => ['from' => AssetState::NEW->value, 'to' => AssetState::IN_USE->value],
        ]);
        $this->assertSame(
            AssetState::NEW->getLabel() . ' → ' . AssetState::IN_USE->getLabel(),
            (new SummaryBuilder())->forActivity($activity),
        );
    }

    public function test_note_summary_truncates(): void
    {
        $long = str_repeat('x', 200);
        $activity = $this->buildActivity([
            'description' => 'note',
            'properties'  => ['body' => $long],
        ]);
        $result = (new SummaryBuilder())->forActivity($activity);
        $this->assertStringStartsWith(trans('history.event.note') . ': ', $result);
        $this->assertLessThanOrEqual(strlen(trans('history.event.note') . ': ') + 80 + 3 /* ellipsis */, strlen($result));
    }

    public function test_incident_subject_gets_prefix(): void
    {
        $asset = Asset::factory()->create();
        $incident = Incident::factory()->create(['asset_id' => $asset->id]);
        $activity = $this->buildActivity([
            'log_name'     => 'incident',
            'subject_type' => Incident::class,
            'subject_id'   => (string) $incident->id,
            'description'  => 'created',
        ]);
        $result = (new SummaryBuilder())->forActivity($activity);
        $this->assertStringStartsWith(trans('history.summary.incident_prefix', ['id' => $incident->id]), $result);
    }

    public function test_incident_subject_for_deleted_incident_gets_removed_prefix(): void
    {
        $activity = $this->buildActivity([
            'log_name'     => 'incident',
            'subject_type' => Incident::class,
            'subject_id'   => '999999', // does not exist
            'description'  => 'created',
        ]);
        $result = (new SummaryBuilder())->forActivity($activity);
        $this->assertStringStartsWith(trans('history.summary.incident_removed'), $result);
    }
}
```

- [ ] **Step 2: Run the tests, confirm 10 failures**

Run:
```bash
ddev exec php artisan test --filter=SummaryBuilderTest
```

Expected: 10 failures (`SummaryBuilder` class missing).

- [ ] **Step 3: Create `SummaryBuilder`**

`app/Support/History/SummaryBuilder.php`:

```php
<?php

namespace App\Support\History;

use App\Enums\AssetState;
use App\Models\Incident;
use App\Models\Place;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class SummaryBuilder
{
    public function forActivity(Activity $activity): string
    {
        $body = $this->bodyFor($activity);

        if ($activity->subject_type === Incident::class) {
            $prefix = Incident::find($activity->subject_id) === null
                ? trans('history.summary.incident_removed')
                : trans('history.summary.incident_prefix', ['id' => $activity->subject_id]);

            return $prefix . $body;
        }

        return $body;
    }

    private function bodyFor(Activity $activity): string
    {
        return match ($activity->description) {
            'created'        => trans('history.summary.created'),
            'deleted'        => trans('history.summary.deleted'),
            'updated'        => trans('history.summary.fields_changed', [
                'count' => count($activity->properties['attributes'] ?? []),
            ]),
            'owner_changed'  => $this->userArrow($activity),
            'place_changed'  => $this->placeArrow($activity),
            'state_changed'  => $this->stateArrow($activity),
            'note'           => trans('history.event.note') . ': ' . Str::limit(
                (string) ($activity->properties['body'] ?? ''),
                80,
            ),
            default          => (string) $activity->description,
        };
    }

    private function userArrow(Activity $activity): string
    {
        return $this->arrow(
            $activity->properties['from'] ?? null,
            $activity->properties['to'] ?? null,
            fn ($id) => User::find($id)?->name,
        );
    }

    private function placeArrow(Activity $activity): string
    {
        return $this->arrow(
            $activity->properties['from'] ?? null,
            $activity->properties['to'] ?? null,
            fn ($id) => Place::find($id)?->name,
        );
    }

    private function stateArrow(Activity $activity): string
    {
        return $this->arrow(
            $activity->properties['from'] ?? null,
            $activity->properties['to'] ?? null,
            fn ($value) => $value === null ? null : AssetState::from($value)->getLabel(),
        );
    }

    private function arrow(mixed $fromKey, mixed $toKey, callable $resolve): string
    {
        return ($fromKey === null ? '—' : ($resolve($fromKey) ?? '—'))
            . ' → '
            . ($toKey === null ? '—' : ($resolve($toKey) ?? '—'));
    }
}
```

- [ ] **Step 4: Run the tests, confirm 10 pass**

Run:
```bash
ddev exec php artisan test --filter=SummaryBuilderTest
```

Expected: 10 passing.

- [ ] **Step 5: Commit**

```bash
git add app/Support/History/SummaryBuilder.php tests/Unit/History/SummaryBuilderTest.php
git commit -m "feat(audit-log): add SummaryBuilder for activity rows"
```

---

## Task 9: HistoryRelationManager (asset events only, registered on AssetResource)

**Scope of this task:** the relation manager class, its query restricted to **Asset-subject activity only**, columns, default sort, empty state, and registration on `AssetResource`. Merging incident events, de-dup, and the "Add note" action are added in Tasks 10–12.

**Files:**
- Create: `app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php`
- Modify: `app/Filament/App/Resources/Assets/AssetResource.php`
- Create: `tests/Feature/Filament/HistoryRelationManagerTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/Filament/HistoryRelationManagerTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Resources\Assets\RelationManagers\HistoryRelationManager;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HistoryRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_relation_manager_lists_asset_events(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $asset = Asset::factory()->create();
        $asset->update(['serial_number' => 'SN-NEW']);

        Livewire::test(HistoryRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
        ])
            ->assertCanSeeTableRecords(
                \Spatie\Activitylog\Models\Activity::query()
                    ->where('subject_id', $asset->id)
                    ->get()
            );
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run:
```bash
ddev exec php artisan test --filter=HistoryRelationManagerTest
```

Expected: failure (class not found).

- [ ] **Step 3: Create the relation manager**

`app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php`:

```php
<?php

namespace App\Filament\App\Resources\Assets\RelationManagers;

use App\Models\Asset;
use App\Support\History\SummaryBuilder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'activities'; // overridden by getTableQuery()

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return trans('history.tab');
    }

    public static function getNavigationIcon(): string
    {
        return Heroicon::OutlinedClock->value;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function getTableQuery(): Builder
    {
        /** @var Asset $asset */
        $asset = $this->getOwnerRecord();

        return Activity::query()
            ->where('subject_type', Asset::class)
            ->where('subject_id', $asset->id);
    }

    public function table(Table $table): Table
    {
        $summary = new SummaryBuilder();

        return $table
            ->query($this->getTableQuery())
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(trans('history.empty_state'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(trans('history.label'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label('User')
                    ->default(trans('history.causer.system')),

                TextColumn::make('description')
                    ->label('Event')
                    ->formatStateUsing(fn (string $state) => trans('history.event.' . $state, [], default: $state))
                    ->badge(),

                TextColumn::make('summary')
                    ->label('')
                    ->state(fn (Activity $record) => $summary->forActivity($record))
                    ->wrap(),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
```

- [ ] **Step 4: Register on `AssetResource::getRelations()`**

Edit `app/Filament/App/Resources/Assets/AssetResource.php`. Add to imports:

```php
use App\Filament\App\Resources\Assets\RelationManagers\HistoryRelationManager;
```

And update `getRelations`:

```php
public static function getRelations(): array
{
    return [
        IncidentsRelationManager::class,
        HistoryRelationManager::class,
    ];
}
```

- [ ] **Step 5: Run the test, confirm it passes**

Run:
```bash
ddev exec php artisan test --filter=HistoryRelationManagerTest
```

Expected: passing.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php app/Filament/App/Resources/Assets/AssetResource.php tests/Feature/Filament/HistoryRelationManagerTest.php
git commit -m "feat(audit-log): add HistoryRelationManager showing asset events"
```

---

## Task 10: Merge Incident events into the asset timeline

**Files:**
- Modify: `app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php`
- Modify: `tests/Feature/Filament/HistoryRelationManagerTest.php`

- [ ] **Step 1: Add the failing test**

Append to `HistoryRelationManagerTest`:

```php
public function test_history_includes_incident_events_for_this_asset(): void
{
    $user = User::factory()->create();
    $this->actingAs($user);

    $asset = Asset::factory()->create();
    $incident = \App\Models\Incident::factory()->create(['asset_id' => $asset->id]);

    $otherAsset = Asset::factory()->create();
    $otherIncident = \App\Models\Incident::factory()->create(['asset_id' => $otherAsset->id]);

    Livewire::test(HistoryRelationManager::class, [
        'ownerRecord' => $asset,
        'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
    ])
        ->assertCanSeeTableRecords(
            \Spatie\Activitylog\Models\Activity::query()
                ->where(function ($q) use ($asset, $incident) {
                    $q->where(fn ($q) => $q->where('subject_type', Asset::class)->where('subject_id', $asset->id))
                      ->orWhere(fn ($q) => $q->where('subject_type', \App\Models\Incident::class)->where('subject_id', (string) $incident->id));
                })->get()
        )
        ->assertCanNotSeeTableRecords(
            \Spatie\Activitylog\Models\Activity::query()
                ->where('subject_type', \App\Models\Incident::class)
                ->where('subject_id', (string) $otherIncident->id)
                ->get()
        );
}
```

- [ ] **Step 2: Run, confirm failure**

```bash
ddev exec php artisan test --filter=HistoryRelationManagerTest
```

Expected: the new test fails (incident rows missing).

- [ ] **Step 3: Extend `getTableQuery()`**

Edit `HistoryRelationManager::getTableQuery()`:

```php
protected function getTableQuery(): Builder
{
    /** @var Asset $asset */
    $asset = $this->getOwnerRecord();

    $incidentIds = \App\Models\Incident::query()
        ->where('asset_id', $asset->id)
        ->pluck('id')
        ->map(fn ($id) => (string) $id)
        ->all();

    return Activity::query()
        ->where(function (Builder $q) use ($asset, $incidentIds): void {
            $q->where(function (Builder $inner) use ($asset): void {
                $inner->where('subject_type', Asset::class)
                      ->where('subject_id', $asset->id);
            });

            if (! empty($incidentIds)) {
                $q->orWhere(function (Builder $inner) use ($incidentIds): void {
                    $inner->where('subject_type', \App\Models\Incident::class)
                          ->whereIn('subject_id', $incidentIds);
                });
            }
        });
}
```

(Note: hard-deleted incidents are intentionally excluded from this query because their IDs no longer exist in the `incidents` table. If you later want to keep their rows visible, persist incident IDs separately or pivot to a `belongs_to_asset` column on `activity_log`. Out of scope for v1.)

- [ ] **Step 4: Run, confirm pass**

```bash
ddev exec php artisan test --filter=HistoryRelationManagerTest
```

Expected: both tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php tests/Feature/Filament/HistoryRelationManagerTest.php
git commit -m "feat(audit-log): merge incident events into asset history"
```

---

## Task 11: De-dup the generic `updated` row when a semantic row exists

**Files:**
- Modify: `app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php`
- Modify: `tests/Feature/Filament/HistoryRelationManagerTest.php`

- [ ] **Step 1: Add the failing tests**

Append to `HistoryRelationManagerTest`:

```php
public function test_generic_updated_row_is_hidden_when_a_semantic_row_exists(): void
{
    $user = User::factory()->create();
    $this->actingAs($user);

    $asset = Asset::factory()->create();
    $newOwner = User::factory()->create();
    $asset->update(['owner_id' => $newOwner->id]);

    $rows = \Spatie\Activitylog\Models\Activity::query()
        ->where('subject_id', $asset->id)
        ->get();

    $genericUpdated = $rows->firstWhere('description', 'updated');
    $semantic = $rows->firstWhere('description', 'owner_changed');

    $this->assertNotNull($genericUpdated, 'generic updated row should exist in DB');
    $this->assertNotNull($semantic, 'semantic row should exist in DB');

    Livewire::test(HistoryRelationManager::class, [
        'ownerRecord' => $asset,
        'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
    ])
        ->assertCanSeeTableRecords(collect([$semantic]))
        ->assertCanNotSeeTableRecords(collect([$genericUpdated]));
}

public function test_generic_updated_row_is_shown_when_no_semantic_row_exists(): void
{
    $user = User::factory()->create();
    $this->actingAs($user);

    $asset = Asset::factory()->create();
    $asset->update(['serial_number' => 'SN-NEW']);

    $genericUpdated = \Spatie\Activitylog\Models\Activity::query()
        ->where('subject_id', $asset->id)
        ->where('description', 'updated')
        ->first();

    Livewire::test(HistoryRelationManager::class, [
        'ownerRecord' => $asset,
        'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
    ])
        ->assertCanSeeTableRecords(collect([$genericUpdated]));
}
```

- [ ] **Step 2: Run, confirm the first fails, second passes**

```bash
ddev exec php artisan test --filter=HistoryRelationManagerTest
```

Expected: first new test fails (generic row still shown alongside semantic); second passes.

- [ ] **Step 3: Add the de-dup clause to `getTableQuery()`**

Replace the body of `getTableQuery()` with:

```php
protected function getTableQuery(): Builder
{
    /** @var Asset $asset */
    $asset = $this->getOwnerRecord();

    $incidentIds = \App\Models\Incident::query()
        ->where('asset_id', $asset->id)
        ->pluck('id')
        ->map(fn ($id) => (string) $id)
        ->all();

    return Activity::query()
        ->where(function (Builder $q) use ($asset, $incidentIds): void {
            $q->where(function (Builder $inner) use ($asset): void {
                $inner->where('subject_type', Asset::class)
                      ->where('subject_id', $asset->id);
            });

            if (! empty($incidentIds)) {
                $q->orWhere(function (Builder $inner) use ($incidentIds): void {
                    $inner->where('subject_type', \App\Models\Incident::class)
                          ->whereIn('subject_id', $incidentIds);
                });
            }
        })
        ->where(function (Builder $q): void {
            $q->where('description', '!=', 'updated')
              ->orWhereNotExists(function ($sub): void {
                  $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                      ->from('activity_log as semantic')
                      ->whereColumn('semantic.subject_id', 'activity_log.subject_id')
                      ->whereColumn('semantic.subject_type', 'activity_log.subject_type')
                      ->whereIn('semantic.description', ['owner_changed', 'place_changed', 'state_changed'])
                      ->where(function ($causerMatch): void {
                          $causerMatch->whereColumn('semantic.causer_id', 'activity_log.causer_id')
                                      ->orWhere(function ($bothNull): void {
                                          $bothNull->whereNull('semantic.causer_id')
                                                   ->whereNull('activity_log.causer_id');
                                      });
                      })
                      ->whereRaw('ABS(strftime("%s", semantic.created_at) - strftime("%s", activity_log.created_at)) <= 1');
              });
        });
}
```

**Driver-aware time-diff:** the `whereRaw(...)` line must work on both SQLite (the test DB, per `phpunit.xml`) and the production driver. Use this exact replacement instead of the SQLite-only `strftime` form:

```php
->where(function ($timeBound): void {
    $driver = \Illuminate\Support\Facades\DB::getDriverName();
    $timeBound->whereRaw(match ($driver) {
        'sqlite' => 'ABS(strftime("%s", semantic.created_at) - strftime("%s", activity_log.created_at)) <= 1',
        'mysql', 'mariadb' => 'ABS(TIMESTAMPDIFF(SECOND, semantic.created_at, activity_log.created_at)) <= 1',
        'pgsql' => 'ABS(EXTRACT(EPOCH FROM semantic.created_at - activity_log.created_at)) <= 1',
        default => '1=1',
    });
})
```

So the final `getTableQuery()` is the version above with this driver-aware time-bound subclause **in place of** the bare `whereRaw('ABS(strftime…)')` line.

- [ ] **Step 4: Run, confirm both new tests pass**

```bash
ddev exec php artisan test --filter=HistoryRelationManagerTest
```

Expected: all relation-manager tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php tests/Feature/Filament/HistoryRelationManagerTest.php
git commit -m "feat(audit-log): hide generic updated row when a semantic event exists in same request"
```

---

## Task 12: "Add note" header action

**Files:**
- Modify: `app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php`
- Modify: `tests/Feature/Filament/HistoryRelationManagerTest.php`

- [ ] **Step 1: Add the failing tests**

Append to `HistoryRelationManagerTest`:

```php
public function test_add_note_action_writes_a_note_activity_against_the_asset(): void
{
    $user = User::factory()->create();
    $this->actingAs($user);

    $asset = Asset::factory()->create();

    Livewire::test(HistoryRelationManager::class, [
        'ownerRecord' => $asset,
        'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
    ])
        ->callAction('add_note', data: ['body' => 'Found dent on lid'])
        ->assertHasNoActionErrors();

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('subject_type', Asset::class)
        ->where('subject_id', $asset->id)
        ->where('description', 'note')
        ->first();

    $this->assertNotNull($activity);
    $this->assertSame('Found dent on lid', $activity->properties['body']);
    $this->assertSame((string) $user->id, (string) $activity->causer_id);
}

public function test_add_note_action_requires_a_body(): void
{
    $user = User::factory()->create();
    $this->actingAs($user);

    $asset = Asset::factory()->create();

    Livewire::test(HistoryRelationManager::class, [
        'ownerRecord' => $asset,
        'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
    ])
        ->callAction('add_note', data: ['body' => ''])
        ->assertHasActionErrors(['body']);
}
```

- [ ] **Step 2: Run, confirm failures**

```bash
ddev exec php artisan test --filter=HistoryRelationManagerTest
```

Expected: 2 new failures (action `add_note` does not exist).

- [ ] **Step 3: Add the header action**

Edit `app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php`. Add imports:

```php
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
```

In `table()`, replace `->headerActions([])` with:

```php
->headerActions([
    Action::make('add_note')
        ->label(trans('history.add_note'))
        ->modalHeading(trans('history.add_note'))
        ->modalSubmitActionLabel(trans('history.add_note_save'))
        ->schema([
            Textarea::make('body')
                ->label(trans('history.add_note_body'))
                ->required()
                ->minLength(1)
                ->maxLength(2000)
                ->rows(4),
        ])
        ->action(function (array $data): void {
            /** @var Asset $asset */
            $asset = $this->getOwnerRecord();

            activity('asset')
                ->performedOn($asset)
                ->causedBy(auth()->user())
                ->withProperties(['body' => $data['body']])
                ->log('note');
        }),
])
```

- [ ] **Step 4: Run, confirm passes**

```bash
ddev exec php artisan test --filter=HistoryRelationManagerTest
```

Expected: all relation-manager tests pass.

- [ ] **Step 5: Run the full test suite to catch regressions**

```bash
ddev exec php artisan test
```

Expected: all suites green.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php tests/Feature/Filament/HistoryRelationManagerTest.php
git commit -m "feat(audit-log): add 'Add note' action to history tab"
```

---

## Task 13: Event-kind / date filters and "System (former user)" causer rendering

**Files:**
- Modify: `app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php`
- Modify: `tests/Feature/Filament/HistoryRelationManagerTest.php`

- [ ] **Step 1: Add the failing tests**

Append to `HistoryRelationManagerTest`:

```php
public function test_event_filter_narrows_results(): void
{
    $user = User::factory()->create();
    $this->actingAs($user);

    $asset = Asset::factory()->create();
    $newOwner = User::factory()->create();
    $asset->update(['owner_id' => $newOwner->id, 'serial_number' => 'X']);

    $ownerChanged = \Spatie\Activitylog\Models\Activity::query()
        ->where('subject_id', $asset->id)->where('description', 'owner_changed')->first();

    Livewire::test(HistoryRelationManager::class, [
        'ownerRecord' => $asset,
        'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
    ])
        ->filterTable('event_kind', ['owner_changed'])
        ->assertCanSeeTableRecords(collect([$ownerChanged]));
}

public function test_former_user_causer_renders_as_system_former_user(): void
{
    $user = User::factory()->create();
    $this->actingAs($user);

    $asset = Asset::factory()->create();

    $user->delete();

    Livewire::test(HistoryRelationManager::class, [
        'ownerRecord' => $asset,
        'pageClass'   => \App\Filament\App\Resources\Assets\Pages\EditAsset::class,
    ])
        ->assertSeeText(trans('history.causer.former_user'));
}
```

- [ ] **Step 2: Run, confirm both fail**

```bash
ddev exec php artisan test --filter=HistoryRelationManagerTest
```

Expected: 2 new failures (filter not defined; "former user" string not rendered).

- [ ] **Step 3: Add filters and former-user rendering**

Edit `HistoryRelationManager`. Add imports:

```php
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
```

In `table()`:

Replace the existing `TextColumn::make('causer.name')` block with:

```php
TextColumn::make('causer_label')
    ->label('User')
    ->state(function (\Spatie\Activitylog\Models\Activity $record): string {
        if ($record->causer_id === null) {
            return trans('history.causer.system');
        }
        if ($record->causer === null) {
            return trans('history.causer.former_user');
        }
        return (string) $record->causer->name;
    }),
```

Replace `->filters([])` with:

```php
->filters([
    SelectFilter::make('event_kind')
        ->label('Event')
        ->multiple()
        ->options([
            'created'        => trans('history.event.created'),
            'updated'        => trans('history.event.updated'),
            'deleted'        => trans('history.event.deleted'),
            'note'           => trans('history.event.note'),
            'owner_changed'  => trans('history.event.owner_changed'),
            'place_changed'  => trans('history.event.place_changed'),
            'state_changed'  => trans('history.event.state_changed'),
        ])
        ->query(function (Builder $query, array $data): Builder {
            $values = $data['values'] ?? [];
            return empty($values) ? $query : $query->whereIn('description', $values);
        }),
    Filter::make('date_range')
        ->schema([
            DatePicker::make('from')->label('From'),
            DatePicker::make('until')->label('Until'),
        ])
        ->query(function (Builder $query, array $data): Builder {
            return $query
                ->when($data['from']  ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
        }),
])
```

- [ ] **Step 4: Run, confirm both pass and nothing else broke**

```bash
ddev exec php artisan test --filter=HistoryRelationManagerTest
```

Expected: all relation-manager tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/App/Resources/Assets/RelationManagers/HistoryRelationManager.php tests/Feature/Filament/HistoryRelationManagerTest.php
git commit -m "feat(audit-log): add event-kind/date filters and former-user causer rendering"
```

---

## Verification gate (after Task 13)

Run the full project test suite plus `composer install --dry-run` to confirm a clean state.

```bash
ddev exec php artisan test
ddev artisan migrate:fresh   # double-check the migration cleanly recreates
ddev exec php artisan test   # re-run after fresh DB
```

Expected: all suites pass twice.

Then click through the Filament UI manually:

1. Open an existing Asset, switch to the **Historie** tab → see "Erstellt" entry from the asset's own creation log (if it existed before this work, the tab is empty for it; create a new asset to validate).
2. Edit the asset's owner / place / state via the standard edit form. Re-open Historie → see the semantic entry, NOT a generic "Geändert" duplicate.
3. Click **Notiz hinzufügen**, type "Test", save. New row appears at the top.
4. Open an Incident on this asset, edit and save. Re-open the Asset's Historie tab → the incident's "Geändert" row appears with the "Vorfall #X:" prefix.

This is the end of v1.

---

## Self-Review notes (for the worker)

- Every code block in this plan is complete enough to paste verbatim; no placeholders.
- Translation keys used in tests (e.g. `history.summary.fields_changed`, `history.summary.incident_prefix`, `history.causer.former_user`) are defined in Task 7.
- The `SummaryBuilder` referenced in Task 9 onward is defined in Task 8 — Task 8 must complete before Task 9.
- The de-dup query in Task 11 is driver-aware (sqlite / mysql / mariadb / pgsql) so tests (SQLite) and production agree without conditional code.
- If `composer require spatie/laravel-activitylog` reports an L13 compatibility error in Task 1, install the latest release that supports L13 explicitly: `ddev composer require "spatie/laravel-activitylog:^4.x-dev"` or check the package's GitHub releases page for the L13-compatible tag, then re-run.
- Tasks run **in numbered order** — each task depends on artifacts from earlier ones (factories from Task 2, traits from Tasks 3–5, observer from Task 6, translations from Task 7, summary builder from Task 8, relation-manager base from Task 9, etc.).

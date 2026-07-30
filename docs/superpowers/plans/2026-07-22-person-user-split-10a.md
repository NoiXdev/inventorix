# Person / User Separation — 10a (Backend & Data) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce a `Person` model + `people` table, back it by the existing users (same UUID), re-point asset owner / handover recipient / handover pivot / reports to `Person`, and add a nullable `users.person_id` — all backend/data, no UI. Suite green on SQLite.

**Architecture:** Additive first (new table/column/relations, suite unaffected), then one coordinated migration+code cutover of the ownership FKs, then verification. The `users` name columns and the Users UI are intentionally left for 10c.

**Tech Stack:** Laravel 13/PHP 8.4, spatie, PHPUnit (SQLite `:memory:` in tests, MariaDB in dev/prod). ddev.

## Global Constraints

- ddev for all commands; **PHPUnit** (`./vendor/bin/phpunit`). Migrations must run on **SQLite** (tests) AND **live MariaDB** (ddev) — verify both.
- **Same-UUID backfill:** `people.id = users.id`, so ownership FK values need NO migration — only the FK target changes.
- **Do NOT** in 10a: drop the `users` name columns, change `UserController`/user-form/`UserRequest`, change the auth-prop, or build any UI (all deferred to 10b/10c). `users` keeps `firstname/lastname/name` (temporarily redundant).
- Audit FKs stay on `users`: `handovers.created_by`, `attachments.uploaded_by`.
- FKs are uuid (`foreignUuid(...)->constrained(...)`); each migration needs a working `down()`. Pint is a CI gate. Commit trailer:
  `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`

---

### Task 1: `Person` model + `people` table + `users.person_id` (additive)

**Files:** create `app/Models/Person.php`, `database/factories/PersonFactory.php`, two migrations; modify `app/Models/User.php`.
**Interfaces produced:** `Person` (HasUuids, `assets()`); `User::person()`; `people` table; `users.person_id`.

This task is purely additive — existing behavior/tests unchanged.

- [ ] **Step 1: `people` migration + backfill**

```php
<?php // database/migrations/2026_07_22_000001_create_people_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // Backfill one person per existing user, reusing the same UUID so the
        // ownership FKs (which currently hold user ids) re-point with no value
        // change. No-op on a fresh DB (tests) where users is empty.
        DB::table('users')->orderBy('id')->chunk(500, function ($users) {
            DB::table('people')->insert($users->map(fn ($u) => [
                'id' => $u->id,
                'firstname' => $u->firstname,
                'lastname' => $u->lastname,
                'name' => $u->name,
                'email' => $u->email,
                'created_at' => $u->created_at,
                'updated_at' => $u->updated_at,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
```

- [ ] **Step 2: `users.person_id` migration + backfill** (nullable; keep name cols)

```php
<?php // database/migrations/2026_07_22_000002_add_person_id_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('person_id')->nullable()->after('id')->constrained('people')->nullOnDelete();
        });

        // Each existing user is its own person (same UUID).
        DB::table('users')->update(['person_id' => DB::raw('id')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropColumn('person_id');
        });
    }
};
```

- [ ] **Step 3: `Person` model**

```php
<?php // app/Models/Person.php
namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['firstname', 'lastname', 'name', 'email'])]
class Person extends Model
{
    use HasFactory, HasUuids;

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'owner_id');
    }
}
```

- [ ] **Step 4: `PersonFactory`** (move the name logic from UserFactory's shape)

```php
<?php // database/factories/PersonFactory.php
namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Person> */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'firstname' => $first,
            'lastname' => $last,
            'name' => $first.' '.$last,
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
```

- [ ] **Step 5: `User::person()`** — in `app/Models/User.php`, add `person_id` to `#[Fillable]`, add the relation, and leave `assets()` for now (removed in Task 2):

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// …
public function person(): BelongsTo
{
    return $this->belongsTo(Person::class, 'person_id');
}
```

- [ ] **Step 6: Test + run + commit**

```php
<?php // tests/Feature/App/PersonModelTest.php
namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_has_assets(): void
    {
        $person = Person::factory()->create();
        Asset::factory()->count(2)->create(['owner_id' => $person->id]);
        $this->assertCount(2, $person->assets);
    }

    public function test_user_links_to_a_person(): void
    {
        $person = Person::factory()->create();
        $user = User::factory()->create(['person_id' => $person->id]);
        $this->assertTrue($user->person->is($person));
    }
}
```
Note: this test needs `AssetFactory` to accept a `people` owner id. In Task 1 `assets.owner_id` still FKs to `users`, so on SQLite (FK enforced) `owner_id => $person->id` would violate the FK. **If** the SQLite FK blocks it, mark `test_person_has_assets` `@depends`/skip until Task 2, OR (preferred) write it in Task 2 after the re-point. Keep `test_user_links_to_a_person` here (person_id → people is valid now).

Run `ddev exec ./vendor/bin/phpunit` (full suite still green — additive change) + `ddev exec php artisan migrate:fresh` on ddev MariaDB to confirm the migration + backfill run on both engines. Pint the new/changed PHP.
```bash
git add app/Models/Person.php app/Models/User.php database/factories/PersonFactory.php database/migrations/2026_07_22_0000{01,02}_* tests/Feature/App/PersonModelTest.php
git commit -m "feat(person): Person model + people table + users.person_id (additive)"
```

---

### Task 2: Coordinated ownership re-point (asset owner + handover recipient + pivot + reports)

**Files:** migrations (re-point 3 FKs + rename); `app/Models/{Asset,User,Handover}.php`; `app/DataObjects/HandoverData.php`; `app/Services/HandoverService.php`; `app/Http/Requests/App/{AssetRequest,HandoverRequest}.php`; `app/Http/Controllers/App/{AssetController,HandoverController}.php`; `app/Support/Assets/AssetTableQuery.php` (owner join); `app/Reports/{GuaranteeStatusReport,AssetsPerEmployeeReport,AssetValueReport}.php`; `database/factories/{AssetFactory,HandoverFactory}.php`; the React handover wizard prop (`personOptions`); and **all tests** that create a `User` as an asset owner or handover recipient.

This is one coordinated cutover — intermediate states aren't green (a handover writes the recipient into `asset.owner_id`, so asset-owner and handover-recipient must move together).

- [ ] **Step 1: Migrations — re-point FKs**

```php
<?php // database/migrations/2026_07_22_000003_point_asset_owner_to_people.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->foreign('owner_id')->references('id')->on('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
```

```php
<?php // database/migrations/2026_07_22_000004_point_handover_people.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handovers', function (Blueprint $table) {
            $table->dropForeign(['recipient_user_id']);
            $table->renameColumn('recipient_user_id', 'recipient_person_id');
        });
        Schema::table('handovers', function (Blueprint $table) {
            $table->foreign('recipient_person_id')->references('id')->on('people')->nullOnDelete();
        });
        Schema::table('handover_asset', function (Blueprint $table) {
            $table->dropForeign(['owner_from_id']);
            $table->dropForeign(['owner_to_id']);
            $table->foreign('owner_from_id')->references('id')->on('people')->nullOnDelete();
            $table->foreign('owner_to_id')->references('id')->on('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('handover_asset', function (Blueprint $table) {
            $table->dropForeign(['owner_from_id']);
            $table->dropForeign(['owner_to_id']);
            $table->foreign('owner_from_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('owner_to_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('handovers', function (Blueprint $table) {
            $table->dropForeign(['recipient_person_id']);
            $table->renameColumn('recipient_person_id', 'recipient_user_id');
        });
        Schema::table('handovers', function (Blueprint $table) {
            $table->foreign('recipient_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
```

**CRITICAL — verify on BOTH engines** before proceeding: `ddev exec php artisan migrate:fresh` on ddev MariaDB, and `ddev exec ./vendor/bin/phpunit --filter PersonModelTest` (which triggers a fresh SQLite migrate). SQLite rebuilds tables for `dropForeign`/`renameColumn`; if either engine errors, adapt (e.g. split the rename from the FK add — already split above — or use `->change()`), but keep the end state identical. Do not proceed until both migrate cleanly.

- [ ] **Step 2: Models** — `Asset::owner()` → `belongsTo(Person::class, 'owner_id')`; `Handover`: rename `recipientUser()` → `recipientPerson()` = `belongsTo(Person::class, 'recipient_person_id')`, and change the `#[Fillable]`/fillable entry `recipient_user_id` → `recipient_person_id`; `User`: **remove** `assets()`. (`Handover::createdBy()` unchanged.)

- [ ] **Step 3: DTO + service** — `HandoverData`: `public ?string $recipientUserId` → `public ?string $recipientPersonId`. `HandoverService::commit`: replace `$data->recipientUserId` with `$data->recipientPersonId` and the pivot/handover write `recipient_user_id` → `recipient_person_id` (owner_from/to/asset.owner_id logic unchanged — they already carry person ids).

- [ ] **Step 4: Requests** — `AssetRequest`: `owner_id` rule `exists:users,id` → `exists:people,id`. `HandoverRequest`: `recipient_user_id` → `recipient_person_id`, rule `exists:users,id` → `exists:people,id` (keep `required_if:recipient_kind,internal`, `nullable`, `uuid`).

- [ ] **Step 5: Controllers + query + reports**
  - `AssetController`: `formOptions()` `ownerOptions` query `User` → `Person`; `index()` `owner_name` comes via the `people` join (see AssetTableQuery); `Asset::with('owner')` now a Person (no code change beyond the relation).
  - `AssetTableQuery`: the owner join/`owner_name` select switches from `users` to `people` (find the `join('users'…)`/`leftJoin` on owner and repoint to `people`; the sortable/searchable `owner_name` alias stays).
  - `HandoverController`: `create()` `userOptions` → `personOptions` (query `Person::orderBy('name')`); `store()` builds `HandoverData(recipientPersonId: $validated['recipient_person_id'] ?? null, …)`; `index()`/`show()` recipient display via `recipientPerson`.
  - Reports `GuaranteeStatusReport`, `AssetsPerEmployeeReport`, `AssetValueReport`: the `employees` filter options (and any `User::query()`) → `Person::query()`; grouping/`owner` relation now Person (labels unchanged).

- [ ] **Step 6: Factories** — `AssetFactory`: `owner_id => Person::factory()` (import `App\Models\Person`, drop the `User` import if now unused). `HandoverFactory`: `recipient_user_id => User::factory()` → `recipient_person_id => Person::factory()`; `created_by => User::factory()` stays.

- [ ] **Step 7: React wizard prop** — `resources/js/pages/handovers/handover-wizard.tsx` (and its create page prop): rename the `userOptions` prop/usage to `personOptions` and the form field `recipient_user_id` → `recipient_person_id` to match the controller + request. Update `resources/js/pages/handovers/__tests__/handover-wizard.test.tsx` accordingly.

- [ ] **Step 8: Update the tests** — search and update every test that created a `User` as an **asset owner** or **handover recipient**:
```bash
grep -rln "owner_id.*User::factory\|recipient_user_id\|->assets\b" tests/
```
For each: replace the owner/recipient `User::factory()` with `Person::factory()`; rename `recipient_user_id` → `recipient_person_id`; if a test asserted `->assets` on a `User`, move it to a `Person`. Keep the **actor** (`User::factory()->create(['login_enabled'=>true])` used for `actingAs`) as a `User`. Files likely touched: `tests/Feature/App/{AssetControllerTest,HandoverControllerTest,AssetImportTest,AssetImportControllerTest,AssetExportControllerTest}.php`, `tests/Feature/App/Reports/*`, and the `HandoverService` unit test if present.

- [ ] **Step 9: Run + commit** — `ddev exec ./vendor/bin/phpunit` full green; `ddev exec pnpm run test` + `ddev exec pnpm run build` (wizard prop rename) green; Pint clean; `migrate:fresh` clean on ddev MariaDB.
```bash
git add -A
git commit -m "refactor(person): re-point asset owner + handover recipient/pivot + reports to Person"
```

---

### Task 3: Final verification

- [ ] **Step 1:** `ddev exec ./vendor/bin/phpunit` → green.
- [ ] **Step 2:** `ddev exec pnpm run test` + `ddev exec pnpm run build` → green.
- [ ] **Step 3:** `ddev exec ./vendor/bin/pint --test app/Models app/Services app/Http app/Reports app/DataObjects database` → clean.
- [ ] **Step 4: Both engines** — `ddev exec php artisan migrate:fresh --seed` on ddev MariaDB runs clean; SQLite is exercised by the suite. Confirm `assets.owner_id`, `handovers.recipient_person_id`, `handover_asset.owner_from_id/owner_to_id` all FK to `people`, and `users.person_id` FKs to `people`.
- [ ] **Step 5: Data sanity (backfill)** — on a DB with existing users (dev), confirm a `Person` exists per user with the same id, `users.person_id = users.id`, and asset owners resolve to people. (`ddev exec php artisan tinker` spot check, or a one-off assertion.)
- [ ] **Step 6:** Commit anything outstanding.

---

## Self-Review Notes

- **Spec coverage:** Person+table+person_id+factories+relations → Task 1; ownership re-point (asset/handover/pivot) + models/DTO/service/requests/controllers/reports/factories/wizard/tests → Task 2; verification → Task 3.
- **Green ordering:** Task 1 is additive (suite unaffected); Task 2 moves asset-owner and handover-recipient together (they're coupled via `owner_to → asset.owner_id`) so there's no red intermediate; user name columns + Users UI + auth-prop are untouched (deferred to 10c).
- **Same-UUID backfill:** ownership FK values never change — only the FK target. Verified `people.id = users.id` and `users.person_id = users.id`.
- **Cross-engine:** every migration is verified on SQLite (tests) AND MariaDB (ddev); `down()` provided for each.
- **Deferred:** dropping `users.{firstname,lastname,name}`, `UserController`/user-form/`UserRequest`, the auth-prop display change, and all People/Users UI → 10b (People CRUD + pickers) and 10c (Users login-only + drop name cols + auth prop).

# Microsoft Entra ID Login Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add optional, single-tenant Microsoft Entra ID sign-in to the Filament panel via a render-hook button, alongside the existing email/password login.

**Architecture:** Laravel Socialite + `socialiteproviders/microsoft-azure`. A controller handles the OAuth redirect/callback. A pure-logic service performs tenant validation, user lookup (oid → email fallback), and attribute sync. A Blade partial injected via Filament's `panels::auth.login.form.before` render hook adds the button. Feature is gated by `MS_LOGIN_ENABLED` (default `false`).

**Tech Stack:** PHP 8.2, Laravel 12, Filament 3.3, Laravel Socialite, `socialiteproviders/microsoft-azure`, PHPUnit 11, ddev for command execution.

**Reference spec:** `docs/superpowers/specs/2026-05-10-entra-id-login-design.md`

---

## Notes for the implementer

- **Run every command via ddev**: `ddev composer …`, `ddev artisan …`, `ddev artisan test …`. The CLAUDE.md at the repo root makes this an explicit project rule.
- **Tests use SQLite `:memory:`** (configured in `phpunit.xml`). `RefreshDatabase` is required on any test that touches the DB.
- **`phpunit.xml` will get a new env line** `MS_LOGIN_ENABLED=true` in Task 1 so routes are registered in the test environment.
- **Belt-and-suspenders flag check**: Per spec, routes are gated in `routes/web.php`. In addition (and as a small, deliberate plan deviation acknowledged here), the controller methods also `abort_unless(config('services.microsoft-azure.enabled'), 404)`. This is what makes the disabled-state test (Task 14) trivially expressible without re-bootstrapping the app. Both layers produce identical observable behavior (404 when off).
- **Commit style** follows the existing project convention: `feat: …`, `chore: …`, `test: …`, lowercase imperative.

---

## File Structure

| File | Action | Responsibility |
|---|---|---|
| `composer.json`, `composer.lock` | Modify | Add `socialiteproviders/microsoft-azure` (pulls Socialite as a transitive). |
| `config/services.php` | Modify | New `microsoft-azure` config block. |
| `.env.example` | Modify | Add five `MS_*` variables. |
| `phpunit.xml` | Modify | Add `MS_LOGIN_ENABLED=true` to test env. |
| `database/migrations/2026_05_10_120000_add_entra_id_to_users_table.php` | Create | Add `entra_id` column to `users`. |
| `database/factories/UserFactory.php` | Modify | Add `firstname`, `lastname`, `login_enabled` defaults so tests can `User::factory()->create()` against the current schema. |
| `app/Exceptions/Auth/EntraAuthException.php` | Create | Abstract base; `getUserMessage(): string`. |
| `app/Exceptions/Auth/EntraTenantMismatchException.php` | Create | Tenant mismatch. |
| `app/Exceptions/Auth/EntraUserNotProvisionedException.php` | Create | User not in DB. |
| `app/Exceptions/Auth/EntraLoginDisabledException.php` | Create | `login_enabled=false`. |
| `app/Services/Auth/EntraIdAuthService.php` | Create | Pure logic: tenant check, user lookup, attribute sync. |
| `app/Http/Controllers/Auth/MicrosoftAuthController.php` | Create | OAuth redirect + callback orchestration. |
| `routes/web.php` | Modify | Register the two `/auth/microsoft/*` routes inside a flag guard. |
| `app/Providers/AppServiceProvider.php` | Modify | Register `SocialiteWasCalled` listener for the `microsoft-azure` driver. |
| `resources/views/filament/auth/entra-button.blade.php` | Create | Renders the button + flashed error. Self-gates on the config flag. |
| `app/Providers/Filament/AppPanelProvider.php` | Modify | Register the render hook injecting the Blade partial. |
| `tests/Unit/Auth/EntraIdAuthServiceTest.php` | Create | Unit tests for the service. |
| `tests/Feature/Auth/MicrosoftLoginTest.php` | Create | Feature tests for the controller + flag-disabled paths + login-page rendering. |

---

## Task 1: Install package, add config, env, phpunit env

**Files:**
- Modify: `composer.json`, `composer.lock` (auto)
- Modify: `config/services.php`
- Modify: `.env.example`
- Modify: `phpunit.xml`

- [ ] **Step 1: Install the Socialite Microsoft-Azure provider**

Run:

```bash
ddev composer require socialiteproviders/microsoft-azure
```

Expected output ends with `Generating optimized autoload files` and no errors.

- [ ] **Step 2: Add the `microsoft-azure` block to `config/services.php`**

Open `config/services.php`. Append to the returned array (before the closing `];`):

```php
    'microsoft-azure' => [
        'enabled'       => filter_var(env('MS_LOGIN_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'client_id'     => env('MS_CLIENT_ID'),
        'client_secret' => env('MS_CLIENT_SECRET'),
        'redirect'      => env('MS_REDIRECT_URI'),
        'tenant'        => env('MS_TENANT_ID'),
    ],
```

- [ ] **Step 3: Append env keys to `.env.example`**

Append at the end of `.env.example`:

```
MS_LOGIN_ENABLED=false
MS_CLIENT_ID=
MS_CLIENT_SECRET=
MS_TENANT_ID=
MS_REDIRECT_URI="${APP_URL}/auth/microsoft/callback"
```

- [ ] **Step 4: Add `MS_LOGIN_ENABLED=true` to `phpunit.xml` test env**

In `phpunit.xml`, inside the `<php>` block, add:

```xml
        <env name="MS_LOGIN_ENABLED" value="true"/>
        <env name="MS_TENANT_ID" value="test-tenant-id"/>
```

- [ ] **Step 5: Verify the test suite still runs**

Run:

```bash
ddev artisan test
```

Expected: existing test suite passes (or status quo — if there were pre-existing failures unrelated to this change, note them but proceed).

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock config/services.php .env.example phpunit.xml
git commit -m "feat: install microsoft-azure socialite provider and add config"
```

---

## Task 2: Migration — add `entra_id` to `users`

**Files:**
- Create: `database/migrations/2026_05_10_120000_add_entra_id_to_users_table.php`

(Use the actual current timestamp when running `ddev artisan make:migration`; the filename above is illustrative.)

- [ ] **Step 1: Generate the migration**

```bash
ddev artisan make:migration add_entra_id_to_users_table --table=users
```

Note the generated filename.

- [ ] **Step 2: Replace the migration body**

Replace the file contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('entra_id')->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['entra_id']);
            $table->dropColumn('entra_id');
        });
    }
};
```

- [ ] **Step 3: Run migrations against the dev database**

```bash
ddev artisan migrate
```

Expected: shows the new migration ran, no errors.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat: add entra_id column to users"
```

---

## Task 3: Update `UserFactory` to satisfy current schema

The existing `UserFactory` is missing `firstname`, `lastname`, and `login_enabled`. The current `users` table requires `firstname` and `lastname` (NOT NULL) and has `login_enabled` (NOT NULL, default false). Tests that `User::factory()->create()` will fail without these. Fix it now so all later tests work.

**Files:**
- Modify: `database/factories/UserFactory.php`

- [ ] **Step 1: Write a smoke test**

Create `tests/Unit/UserFactorySmokeTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFactorySmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_a_user_satisfying_schema(): void
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->firstname);
        $this->assertNotEmpty($user->lastname);
        $this->assertIsBool($user->login_enabled);
    }
}
```

- [ ] **Step 2: Run it — expect failure**

```bash
ddev artisan test --filter=UserFactorySmokeTest
```

Expected: FAIL — "SQLSTATE … NOT NULL constraint failed: users.firstname" or similar.

- [ ] **Step 3: Update the factory**

Replace `definition()` in `database/factories/UserFactory.php` with:

```php
    public function definition(): array
    {
        $first = fake()->firstName();
        $last  = fake()->lastName();

        return [
            'firstname'      => $first,
            'lastname'       => $last,
            'name'           => $first . ' ' . $last,
            'email'          => fake()->unique()->safeEmail(),
            'password'       => static::$password ??= Hash::make('password'),
            'login_enabled'  => true,
            'remember_token' => Str::random(10),
        ];
    }
```

- [ ] **Step 4: Run — expect pass**

```bash
ddev artisan test --filter=UserFactorySmokeTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/factories/UserFactory.php tests/Unit/UserFactorySmokeTest.php
git commit -m "test: align UserFactory with current users schema"
```

---

## Task 4: Exception classes

Four small classes in one task — each is two methods at most.

**Files:**
- Create: `app/Exceptions/Auth/EntraAuthException.php`
- Create: `app/Exceptions/Auth/EntraTenantMismatchException.php`
- Create: `app/Exceptions/Auth/EntraUserNotProvisionedException.php`
- Create: `app/Exceptions/Auth/EntraLoginDisabledException.php`

- [ ] **Step 1: Create the abstract base**

Create `app/Exceptions/Auth/EntraAuthException.php`:

```php
<?php

namespace App\Exceptions\Auth;

use RuntimeException;

abstract class EntraAuthException extends RuntimeException
{
    abstract public function getUserMessage(): string;
}
```

- [ ] **Step 2: Create the three concrete subclasses**

Create `app/Exceptions/Auth/EntraTenantMismatchException.php`:

```php
<?php

namespace App\Exceptions\Auth;

class EntraTenantMismatchException extends EntraAuthException
{
    public function getUserMessage(): string
    {
        return __('This Microsoft account is not from the authorized tenant.');
    }
}
```

Create `app/Exceptions/Auth/EntraUserNotProvisionedException.php`:

```php
<?php

namespace App\Exceptions\Auth;

class EntraUserNotProvisionedException extends EntraAuthException
{
    public function getUserMessage(): string
    {
        return __('Your Microsoft account is not authorized for this app. Contact an administrator.');
    }
}
```

Create `app/Exceptions/Auth/EntraLoginDisabledException.php`:

```php
<?php

namespace App\Exceptions\Auth;

class EntraLoginDisabledException extends EntraAuthException
{
    public function getUserMessage(): string
    {
        return __('Your account is disabled. Contact an administrator.');
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Exceptions/Auth/
git commit -m "feat: add Entra ID auth exception hierarchy"
```

---

## Task 5: `EntraIdAuthService::assertTenantMatches`

**Files:**
- Create: `app/Services/Auth/EntraIdAuthService.php`
- Create: `tests/Unit/Auth/EntraIdAuthServiceTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Auth/EntraIdAuthServiceTest.php`:

```php
<?php

namespace Tests\Unit\Auth;

use App\Exceptions\Auth\EntraTenantMismatchException;
use App\Services\Auth\EntraIdAuthService;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class EntraIdAuthServiceTest extends TestCase
{
    private function makeSocialiteUser(array $overrides = []): SocialiteUser
    {
        $u = new SocialiteUser();
        $u->id    = $overrides['id']    ?? 'oid-abc-123';
        $u->email = $overrides['email'] ?? 'alice@example.com';
        $u->name  = $overrides['name']  ?? 'Alice Example';
        $u->user  = array_merge([
            'tid'               => 'test-tenant-id',
            'givenName'         => 'Alice',
            'surname'           => 'Example',
            'displayName'       => 'Alice Example',
            'mail'              => 'alice@example.com',
            'userPrincipalName' => 'alice@example.com',
        ], $overrides['user'] ?? []);
        return $u;
    }

    public function test_assert_tenant_matches_passes_when_tid_matches_config(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        $svc = new EntraIdAuthService();

        $svc->assertTenantMatches($this->makeSocialiteUser());

        $this->expectNotToPerformAssertions();
    }

    public function test_assert_tenant_matches_throws_on_mismatch(): void
    {
        config()->set('services.microsoft-azure.tenant', 'expected-tenant');
        $svc = new EntraIdAuthService();

        $this->expectException(EntraTenantMismatchException::class);

        $svc->assertTenantMatches($this->makeSocialiteUser([
            'user' => ['tid' => 'wrong-tenant'],
        ]));
    }

    public function test_assert_tenant_matches_throws_when_tid_missing(): void
    {
        config()->set('services.microsoft-azure.tenant', 'expected-tenant');
        $svc = new EntraIdAuthService();

        $this->expectException(EntraTenantMismatchException::class);

        $u = $this->makeSocialiteUser();
        $u->user = []; // no tid claim
        $svc->assertTenantMatches($u);
    }
}
```

- [ ] **Step 2: Run — expect failure**

```bash
ddev artisan test --filter=EntraIdAuthServiceTest
```

Expected: FAIL — `Class "App\Services\Auth\EntraIdAuthService" not found`.

- [ ] **Step 3: Implement the service skeleton with `assertTenantMatches`**

Create `app/Services/Auth/EntraIdAuthService.php`:

```php
<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\EntraTenantMismatchException;
use Laravel\Socialite\Two\User as SocialiteUser;

class EntraIdAuthService
{
    public function assertTenantMatches(SocialiteUser $msUser): void
    {
        $expected = config('services.microsoft-azure.tenant');
        $actual   = $msUser->user['tid'] ?? null;

        if ($expected === null || $actual === null || $actual !== $expected) {
            throw new EntraTenantMismatchException();
        }
    }
}
```

- [ ] **Step 4: Run — expect pass**

```bash
ddev artisan test --filter=EntraIdAuthServiceTest
```

Expected: 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Auth/EntraIdAuthService.php tests/Unit/Auth/EntraIdAuthServiceTest.php
git commit -m "feat: add EntraIdAuthService::assertTenantMatches"
```

---

## Task 6: `EntraIdAuthService::resolveUser` — match by `entra_id`

**Files:**
- Modify: `app/Services/Auth/EntraIdAuthService.php`
- Modify: `tests/Unit/Auth/EntraIdAuthServiceTest.php`

- [ ] **Step 1: Add the failing test**

Append the `RefreshDatabase` trait usage and a new test method to the existing `EntraIdAuthServiceTest`:

At the top of the class, after the `class` declaration, add:

```php
    use \Illuminate\Foundation\Testing\RefreshDatabase;
```

Add the import at the top of the file:

```php
use App\Models\User;
```

Add this test method:

```php
    public function test_resolve_user_returns_existing_user_matched_by_entra_id(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        $existing = User::factory()->create([
            'entra_id'      => 'oid-abc-123',
            'email'         => 'unrelated@elsewhere.test',
            'login_enabled' => true,
        ]);

        $svc = new EntraIdAuthService();
        $resolved = $svc->resolveUser($this->makeSocialiteUser());

        $this->assertTrue($existing->is($resolved));
    }
```

- [ ] **Step 2: Run — expect failure**

```bash
ddev artisan test --filter=test_resolve_user_returns_existing_user_matched_by_entra_id
```

Expected: FAIL — `Method "resolveUser" not found` or similar.

- [ ] **Step 3: Implement `resolveUser` with the entra_id lookup branch and `login_enabled` gate**

Add to `EntraIdAuthService`:

```php
use App\Exceptions\Auth\EntraLoginDisabledException;
use App\Exceptions\Auth\EntraUserNotProvisionedException;
use App\Models\User;
```

```php
    public function resolveUser(SocialiteUser $msUser): User
    {
        $user = User::query()->where('entra_id', $msUser->id)->first();

        if ($user === null) {
            throw new EntraUserNotProvisionedException();
        }

        if (! $user->login_enabled) {
            throw new EntraLoginDisabledException();
        }

        return $user;
    }
```

- [ ] **Step 4: Run — expect pass**

```bash
ddev artisan test --filter=test_resolve_user_returns_existing_user_matched_by_entra_id
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Auth/EntraIdAuthService.php tests/Unit/Auth/EntraIdAuthServiceTest.php
git commit -m "feat: resolve user by entra_id"
```

---

## Task 7: `EntraIdAuthService::resolveUser` — email fallback links account on first SSO

**Files:**
- Modify: `app/Services/Auth/EntraIdAuthService.php`
- Modify: `tests/Unit/Auth/EntraIdAuthServiceTest.php`

- [ ] **Step 1: Add the failing test**

Append to `EntraIdAuthServiceTest`:

```php
    public function test_resolve_user_links_existing_user_by_email_when_entra_id_is_null(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        $existing = User::factory()->create([
            'entra_id'      => null,
            'email'         => 'alice@example.com',
            'login_enabled' => true,
        ]);

        $svc = new EntraIdAuthService();
        $resolved = $svc->resolveUser($this->makeSocialiteUser());

        $this->assertTrue($existing->is($resolved));
        $this->assertSame('oid-abc-123', $resolved->fresh()->entra_id);
    }

    public function test_resolve_user_email_match_is_case_insensitive(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        User::factory()->create([
            'entra_id'      => null,
            'email'         => 'Alice@EXAMPLE.com',
            'login_enabled' => true,
        ]);

        $svc = new EntraIdAuthService();
        $resolved = $svc->resolveUser($this->makeSocialiteUser([
            'email' => 'alice@example.com',
        ]));

        $this->assertSame('oid-abc-123', $resolved->fresh()->entra_id);
    }

    public function test_resolve_user_falls_back_to_user_principal_name_when_mail_is_null(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        User::factory()->create([
            'entra_id'      => null,
            'email'         => 'alice@example.com',
            'login_enabled' => true,
        ]);

        $svc = new EntraIdAuthService();
        $msUser = $this->makeSocialiteUser([
            'email' => null,
            'user'  => ['mail' => null, 'userPrincipalName' => 'alice@example.com'],
        ]);
        $resolved = $svc->resolveUser($msUser);

        $this->assertSame('oid-abc-123', $resolved->fresh()->entra_id);
    }
```

- [ ] **Step 2: Run — expect failure**

```bash
ddev artisan test --filter=EntraIdAuthServiceTest
```

Expected: the three new tests fail (existing one still passes).

- [ ] **Step 3: Extend `resolveUser` with the email fallback branch**

Replace the body of `resolveUser` in `app/Services/Auth/EntraIdAuthService.php` with:

```php
    public function resolveUser(SocialiteUser $msUser): User
    {
        $user = User::query()->where('entra_id', $msUser->id)->first();

        if ($user === null) {
            $email = $this->extractEmail($msUser);
            if ($email !== null) {
                $user = User::query()
                    ->whereNull('entra_id')
                    ->whereRaw('LOWER(email) = ?', [strtolower($email)])
                    ->first();

                if ($user !== null) {
                    $user->entra_id = $msUser->id;
                    $user->save();
                }
            }
        }

        if ($user === null) {
            throw new EntraUserNotProvisionedException();
        }

        if (! $user->login_enabled) {
            throw new EntraLoginDisabledException();
        }

        return $user;
    }

    private function extractEmail(SocialiteUser $msUser): ?string
    {
        $email = $msUser->email
            ?? ($msUser->user['mail'] ?? null)
            ?? ($msUser->user['userPrincipalName'] ?? null);

        return ($email === null || $email === '') ? null : $email;
    }
```

- [ ] **Step 4: Run — expect pass**

```bash
ddev artisan test --filter=EntraIdAuthServiceTest
```

Expected: all current tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Auth/EntraIdAuthService.php tests/Unit/Auth/EntraIdAuthServiceTest.php
git commit -m "feat: link existing user via email on first SSO login"
```

---

## Task 8: `resolveUser` — rejection paths (not provisioned, login disabled)

**Files:**
- Modify: `tests/Unit/Auth/EntraIdAuthServiceTest.php`

- [ ] **Step 1: Add the failing tests**

Append to `EntraIdAuthServiceTest`:

```php
    public function test_resolve_user_throws_not_provisioned_when_no_match(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');

        $svc = new EntraIdAuthService();

        $this->expectException(\App\Exceptions\Auth\EntraUserNotProvisionedException::class);

        $svc->resolveUser($this->makeSocialiteUser());
    }

    public function test_resolve_user_throws_not_provisioned_when_email_and_upn_are_null(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        User::factory()->create([
            'entra_id'      => null,
            'email'         => 'alice@example.com',
            'login_enabled' => true,
        ]);

        $svc = new EntraIdAuthService();

        $this->expectException(\App\Exceptions\Auth\EntraUserNotProvisionedException::class);

        $svc->resolveUser($this->makeSocialiteUser([
            'email' => null,
            'user'  => ['mail' => null, 'userPrincipalName' => null],
        ]));
    }

    public function test_resolve_user_throws_login_disabled_when_user_is_disabled(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        User::factory()->create([
            'entra_id'      => 'oid-abc-123',
            'login_enabled' => false,
        ]);

        $svc = new EntraIdAuthService();

        $this->expectException(\App\Exceptions\Auth\EntraLoginDisabledException::class);

        $svc->resolveUser($this->makeSocialiteUser());
    }
```

- [ ] **Step 2: Run — expect pass (these test existing behavior)**

```bash
ddev artisan test --filter=EntraIdAuthServiceTest
```

Expected: all tests pass — the rejection paths are already implemented; this task locks them in.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Auth/EntraIdAuthServiceTest.php
git commit -m "test: lock in resolveUser rejection paths"
```

---

## Task 9: `EntraIdAuthService::syncAttributes`

**Files:**
- Modify: `app/Services/Auth/EntraIdAuthService.php`
- Modify: `tests/Unit/Auth/EntraIdAuthServiceTest.php`

- [ ] **Step 1: Add the failing test**

Append to `EntraIdAuthServiceTest`:

```php
    public function test_sync_attributes_overwrites_firstname_lastname_name_email(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        $user = User::factory()->create([
            'firstname' => 'Old',
            'lastname'  => 'Name',
            'name'      => 'Old Name',
            'email'     => 'old@local.test',
            'entra_id'  => 'oid-abc-123',
        ]);

        $svc = new EntraIdAuthService();
        $svc->syncAttributes($user, $this->makeSocialiteUser([
            'user' => [
                'givenName'         => 'Alice',
                'surname'           => 'Example',
                'displayName'       => 'Alice Example',
                'mail'              => 'alice@example.com',
                'userPrincipalName' => 'alice@example.com',
            ],
        ]));

        $fresh = $user->fresh();
        $this->assertSame('Alice', $fresh->firstname);
        $this->assertSame('Example', $fresh->lastname);
        $this->assertSame('Alice Example', $fresh->name);
        $this->assertSame('alice@example.com', $fresh->email);
    }

    public function test_sync_attributes_uses_user_principal_name_when_mail_missing(): void
    {
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
        $user = User::factory()->create(['entra_id' => 'oid-abc-123']);

        $svc = new EntraIdAuthService();
        $svc->syncAttributes($user, $this->makeSocialiteUser([
            'user' => [
                'givenName'         => 'Alice',
                'surname'           => 'Example',
                'displayName'       => 'Alice Example',
                'mail'              => null,
                'userPrincipalName' => 'alice@example.com',
            ],
        ]));

        $this->assertSame('alice@example.com', $user->fresh()->email);
    }
```

- [ ] **Step 2: Run — expect failure**

```bash
ddev artisan test --filter=test_sync_attributes
```

Expected: FAIL — `Method syncAttributes does not exist`.

- [ ] **Step 3: Implement `syncAttributes`**

Append to `EntraIdAuthService`:

```php
    public function syncAttributes(User $user, SocialiteUser $msUser): void
    {
        $raw = $msUser->user;

        $first = $raw['givenName']   ?? $user->firstname;
        $last  = $raw['surname']     ?? $user->lastname;
        $name  = $raw['displayName'] ?? trim($first . ' ' . $last);
        $email = $raw['mail'] ?? $raw['userPrincipalName'] ?? $msUser->email ?? $user->email;

        $user->forceFill([
            'firstname' => $first,
            'lastname'  => $last,
            'name'      => $name,
            'email'     => $email,
        ])->save();
    }
```

- [ ] **Step 4: Run — expect pass**

```bash
ddev artisan test --filter=EntraIdAuthServiceTest
```

Expected: all unit tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Auth/EntraIdAuthService.php tests/Unit/Auth/EntraIdAuthServiceTest.php
git commit -m "feat: sync user attributes from Entra Graph claims"
```

---

## Task 10: Register Socialite extension in `AppServiceProvider`

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add the listener**

Open `app/Providers/AppServiceProvider.php`. In the `boot()` method, add:

```php
        \Illuminate\Support\Facades\Event::listen(
            \SocialiteProviders\Manager\SocialiteWasCalled::class,
            function ($event) {
                $event->extendSocialite(
                    'microsoft-azure',
                    \SocialiteProviders\Azure\Provider::class,
                );
            },
        );
```

(Use FQNs to avoid touching imports if you prefer; otherwise add the two `use` statements at the top of the file.)

- [ ] **Step 2: Verify the driver resolves**

```bash
ddev artisan tinker --execute="config(['services.microsoft-azure.tenant' => 'x', 'services.microsoft-azure.client_id' => 'y', 'services.microsoft-azure.client_secret' => 'z', 'services.microsoft-azure.redirect' => 'http://localhost/cb']); var_dump(get_class(\Laravel\Socialite\Facades\Socialite::driver('microsoft-azure')));"
```

Expected output contains `SocialiteProviders\Azure\Provider`.

- [ ] **Step 3: Commit**

```bash
git add app/Providers/AppServiceProvider.php
git commit -m "feat: register microsoft-azure socialite extension"
```

---

## Task 11: `MicrosoftAuthController` + routes — happy path callback

**Files:**
- Create: `app/Http/Controllers/Auth/MicrosoftAuthController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Auth/MicrosoftLoginTest.php`

- [ ] **Step 1: Write the failing happy-path feature test**

Create `tests/Feature/Auth/MicrosoftLoginTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class MicrosoftLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.microsoft-azure.enabled', true);
        config()->set('services.microsoft-azure.tenant', 'test-tenant-id');
    }

    private function makeSocialiteUser(array $overrides = []): SocialiteUser
    {
        $u = new SocialiteUser();
        $u->id    = $overrides['id']    ?? 'oid-abc-123';
        $u->email = $overrides['email'] ?? 'alice@example.com';
        $u->name  = $overrides['name']  ?? 'Alice Example';
        $u->user  = array_merge([
            'tid'               => 'test-tenant-id',
            'givenName'         => 'Alice',
            'surname'           => 'Example',
            'displayName'       => 'Alice Example',
            'mail'              => 'alice@example.com',
            'userPrincipalName' => 'alice@example.com',
        ], $overrides['user'] ?? []);
        return $u;
    }

    private function fakeSocialiteReturning(SocialiteUser $msUser): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($msUser);
        $driver->shouldReceive('scopes')->andReturnSelf();
        Socialite::shouldReceive('driver')->with('microsoft-azure')->andReturn($driver);
    }

    public function test_callback_logs_in_existing_user_matched_by_entra_id(): void
    {
        $user = User::factory()->create([
            'entra_id'      => 'oid-abc-123',
            'email'         => 'unrelated@elsewhere.test',
            'firstname'     => 'Old',
            'lastname'      => 'Name',
            'name'          => 'Old Name',
            'login_enabled' => true,
        ]);

        $this->fakeSocialiteReturning($this->makeSocialiteUser());

        $response = $this->get('/auth/microsoft/callback?code=fake&state=fake');

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $fresh = $user->fresh();
        $this->assertSame('Alice', $fresh->firstname);
        $this->assertSame('Example', $fresh->lastname);
        $this->assertSame('alice@example.com', $fresh->email);
    }
}
```

- [ ] **Step 2: Run — expect failure**

```bash
ddev artisan test --filter=test_callback_logs_in_existing_user_matched_by_entra_id
```

Expected: FAIL — 404 (route not registered) or class-not-found.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Auth/MicrosoftAuthController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\Auth\EntraAuthException;
use App\Services\Auth\EntraIdAuthService;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class MicrosoftAuthController
{
    public function redirect(): RedirectResponse
    {
        abort_unless(config('services.microsoft-azure.enabled'), 404);

        return Socialite::driver('microsoft-azure')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(EntraIdAuthService $auth): RedirectResponse
    {
        abort_unless(config('services.microsoft-azure.enabled'), 404);

        try {
            $msUser = Socialite::driver('microsoft-azure')->user();
            $auth->assertTenantMatches($msUser);
            $user = $auth->resolveUser($msUser);
            $auth->syncAttributes($user, $msUser);

            Auth::guard('web')->login($user, remember: true);
            request()->session()->regenerate();

            return redirect()->intended(Filament::getUrl());
        } catch (EntraAuthException $e) {
            return redirect()->route('filament.app.auth.login')
                ->with('entra_error', $e->getUserMessage());
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('filament.app.auth.login')
                ->with('entra_error', __('Microsoft sign-in failed. Please try again.'));
        }
    }
}
```

- [ ] **Step 4: Register the routes (gated)**

Open `routes/web.php`. Add at the top of the file:

```php
use App\Http\Controllers\Auth\MicrosoftAuthController;
```

Append at the end of the file:

```php
if (config('services.microsoft-azure.enabled')) {
    Route::get('/auth/microsoft/redirect', [MicrosoftAuthController::class, 'redirect'])
        ->name('auth.microsoft.redirect');
    Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback'])
        ->name('auth.microsoft.callback');
}
```

- [ ] **Step 5: Run — expect pass**

```bash
ddev artisan test --filter=test_callback_logs_in_existing_user_matched_by_entra_id
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Auth/MicrosoftAuthController.php routes/web.php tests/Feature/Auth/MicrosoftLoginTest.php
git commit -m "feat: add Microsoft Entra ID auth controller and routes"
```

---

## Task 12: Callback — first-link-by-email path

**Files:**
- Modify: `tests/Feature/Auth/MicrosoftLoginTest.php`

- [ ] **Step 1: Add the test**

Append to `MicrosoftLoginTest`:

```php
    public function test_callback_links_existing_user_by_email_when_entra_id_is_null(): void
    {
        $user = User::factory()->create([
            'entra_id'      => null,
            'email'         => 'alice@example.com',
            'login_enabled' => true,
        ]);

        $this->fakeSocialiteReturning($this->makeSocialiteUser());

        $this->get('/auth/microsoft/callback?code=fake&state=fake')
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('oid-abc-123', $user->fresh()->entra_id);
    }
```

- [ ] **Step 2: Run — expect pass (uses already-implemented service logic)**

```bash
ddev artisan test --filter=test_callback_links_existing_user_by_email
```

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Auth/MicrosoftLoginTest.php
git commit -m "test: callback links existing user by email"
```

---

## Task 13: Callback — rejection paths

**Files:**
- Modify: `tests/Feature/Auth/MicrosoftLoginTest.php`

- [ ] **Step 1: Add three rejection-path tests**

Append to `MicrosoftLoginTest`:

```php
    public function test_callback_rejects_unknown_user(): void
    {
        $this->fakeSocialiteReturning($this->makeSocialiteUser());

        $response = $this->get('/auth/microsoft/callback?code=fake&state=fake');

        $response->assertRedirect(route('filament.app.auth.login'));
        $response->assertSessionHas('entra_error');
        $this->assertGuest();
    }

    public function test_callback_rejects_disabled_user(): void
    {
        User::factory()->create([
            'entra_id'      => 'oid-abc-123',
            'login_enabled' => false,
        ]);

        $this->fakeSocialiteReturning($this->makeSocialiteUser());

        $response = $this->get('/auth/microsoft/callback?code=fake&state=fake');

        $response->assertRedirect(route('filament.app.auth.login'));
        $response->assertSessionHas('entra_error', __('Your account is disabled. Contact an administrator.'));
        $this->assertGuest();
    }

    public function test_callback_rejects_wrong_tenant(): void
    {
        User::factory()->create([
            'entra_id'      => 'oid-abc-123',
            'login_enabled' => true,
        ]);

        $this->fakeSocialiteReturning($this->makeSocialiteUser([
            'user' => ['tid' => 'some-other-tenant'],
        ]));

        $response = $this->get('/auth/microsoft/callback?code=fake&state=fake');

        $response->assertRedirect(route('filament.app.auth.login'));
        $response->assertSessionHas('entra_error', __('This Microsoft account is not from the authorized tenant.'));
        $this->assertGuest();
    }
```

- [ ] **Step 2: Run — expect pass**

```bash
ddev artisan test --filter=MicrosoftLoginTest
```

Expected: all current tests pass. The behavior is already implemented; these tests lock it in.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Auth/MicrosoftLoginTest.php
git commit -m "test: callback rejection paths (unknown, disabled, wrong tenant)"
```

---

## Task 14: Callback — generic error path + disabled-flag 404 routes

**Files:**
- Modify: `tests/Feature/Auth/MicrosoftLoginTest.php`

- [ ] **Step 1: Add two more tests**

Append to `MicrosoftLoginTest`:

```php
    public function test_callback_redirects_with_generic_error_on_unexpected_failure(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andThrow(new \RuntimeException('boom'));
        $driver->shouldReceive('scopes')->andReturnSelf();
        Socialite::shouldReceive('driver')->with('microsoft-azure')->andReturn($driver);

        $response = $this->get('/auth/microsoft/callback?code=fake&state=fake');

        $response->assertRedirect(route('filament.app.auth.login'));
        $response->assertSessionHas('entra_error', __('Microsoft sign-in failed. Please try again.'));
        $this->assertGuest();
    }

    public function test_routes_return_404_when_feature_disabled(): void
    {
        config()->set('services.microsoft-azure.enabled', false);

        $this->get('/auth/microsoft/redirect')->assertNotFound();
        $this->get('/auth/microsoft/callback?code=x&state=y')->assertNotFound();
    }
```

- [ ] **Step 2: Run — expect pass**

```bash
ddev artisan test --filter=MicrosoftLoginTest
```

Expected: all tests pass.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Auth/MicrosoftLoginTest.php
git commit -m "test: callback generic error path and disabled-flag 404"
```

---

## Task 15: Login button Blade partial

**Files:**
- Create: `resources/views/filament/auth/entra-button.blade.php`

- [ ] **Step 1: Create the view**

Create `resources/views/filament/auth/entra-button.blade.php`:

```blade
@if (config('services.microsoft-azure.enabled'))
    @if (session('entra_error'))
        <div class="mb-4 rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-400/10 dark:text-danger-400">
            {{ session('entra_error') }}
        </div>
    @endif

    <a
        href="{{ route('auth.microsoft.redirect') }}"
        class="fi-btn fi-btn-color-gray fi-btn-outlined fi-btn-size-md fi-btn-fullwidth mb-4 inline-flex w-full items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-white/5"
    >
        {{ __('Login via Entra ID') }}
    </a>

    <div class="mb-4 text-center text-sm text-gray-500">
        {{ __('or sign in with email') }}
    </div>
@endif
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/filament/auth/entra-button.blade.php
git commit -m "feat: add Entra ID login button blade partial"
```

(Visual verification of the button styling happens in the manual checklist; no automated assertion on CSS classes.)

---

## Task 16: Wire render hook in `AppPanelProvider` + button visibility tests

**Files:**
- Modify: `app/Providers/Filament/AppPanelProvider.php`
- Modify: `tests/Feature/Auth/MicrosoftLoginTest.php`

- [ ] **Step 1: Add failing tests for button visibility on the login page**

Append to `MicrosoftLoginTest`:

```php
    public function test_login_page_renders_button_when_feature_enabled(): void
    {
        $this->get(route('filament.app.auth.login'))
            ->assertOk()
            ->assertSeeText(__('Login via Entra ID'));
    }

    public function test_login_page_does_not_render_button_when_feature_disabled(): void
    {
        config()->set('services.microsoft-azure.enabled', false);

        $this->get(route('filament.app.auth.login'))
            ->assertOk()
            ->assertDontSeeText(__('Login via Entra ID'));
    }
```

- [ ] **Step 2: Run — expect failure**

```bash
ddev artisan test --filter=test_login_page_renders_button_when_feature_enabled
```

Expected: FAIL — `Failed asserting that the page contains the text [Login via Entra ID]` (the render hook isn't wired yet).

- [ ] **Step 3: Wire the render hook**

Open `app/Providers/Filament/AppPanelProvider.php`. Add the import at the top:

```php
use Filament\View\PanelsRenderHook;
```

In the `panel()` method, inside the chained call (after `->renderHook('panels::global-search.before', ...)`), add:

```php
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn () => view('filament.auth.entra-button'),
            )
```

- [ ] **Step 4: Run — expect pass**

```bash
ddev artisan test --filter=MicrosoftLoginTest
```

Expected: all tests pass — both the enabled-state and disabled-state visibility tests.

- [ ] **Step 5: Run the entire suite to confirm no regressions**

```bash
ddev artisan test
```

Expected: green.

- [ ] **Step 6: Commit**

```bash
git add app/Providers/Filament/AppPanelProvider.php tests/Feature/Auth/MicrosoftLoginTest.php
git commit -m "feat: inject Entra ID login button via filament render hook"
```

---

## Task 17: Manual verification

This task is human-only — no code, no commit. Run through the spec's manual checklist before declaring done.

- [ ] App registration created in the Entra portal (single-tenant), redirect URI `<APP_URL>/auth/microsoft/callback` registered.
- [ ] `.env` populated: `MS_LOGIN_ENABLED=true`, `MS_CLIENT_ID`, `MS_CLIENT_SECRET`, `MS_TENANT_ID`, `MS_REDIRECT_URI`.
- [ ] `ddev artisan config:clear` then load login page → "Login via Entra ID" button visible above the email/password form.
- [ ] Click button → redirected to Microsoft → consent (first time) → returned to panel, signed in as the matching local user.
- [ ] Sign out, attempt to sign in with a non-tenant Microsoft account → rejected with the tenant-mismatch message.
- [ ] Disable a user (`login_enabled = false`), attempt SSO → rejected with the disabled-account message.
- [ ] Email/password form still works for break-glass.
- [ ] Set `MS_LOGIN_ENABLED=false`, `ddev artisan config:clear`, reload login page → button is gone, `/auth/microsoft/redirect` returns 404.

---

## Self-review notes

- **Spec coverage check**: every spec section maps to at least one task — install (1), config (1), env (1), migration (2), exceptions (4), service (5–9), socialite registration (10), controller+routes (11), all rejection paths (13–14), button view (15), render hook (16), feature flag disabled state (14, 16), manual checklist (17).
- **Plan deviation**: routes are belt-and-suspenders — gated in `routes/web.php` (per spec) AND `abort_unless` in the controller (deviation). Acknowledged in the notes block at the top.
- **`UserFactory` gap**: existing factory doesn't satisfy NOT NULL `firstname`/`lastname`, so Task 3 fixes it before any service test depends on it.
- **No placeholders**: every code block is the actual code to paste. Every command is the exact command to run.
- **TDD discipline**: every implementation step is preceded by a failing test step and followed by a passing-test step. Tasks 8 and 13 deliberately add tests against already-implemented behavior to lock it in (these are still useful regression tests).

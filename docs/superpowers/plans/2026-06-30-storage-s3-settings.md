# Storage (S3) Settings Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "Storage" settings page under the Settings cluster that configures S3 (or any S3-compatible provider) as the app's file storage, with a connection test and a safe local fallback for fresh installs.

**Architecture:** Follows the existing settings pattern: a Spatie `Settings` class (`StorageSettings`) seeded by a settings migration, applied to runtime `filesystems.*` config by `App\Support\ApplySettings` (already run per request by the `ApplyRuntimeSettings` middleware), and edited through a Filament `SettingsPage` (`ManageStorageSettings`) modeled on `ManageMailSettings`.

**Tech Stack:** Laravel 13, Filament 5, `spatie/laravel-settings`, PHPUnit 12. All shell commands run inside ddev (`ddev artisan`, `ddev exec`).

## Global Constraints

- Run all PHP/artisan/test commands through ddev (e.g. `ddev artisan test`, never bare `php`/`artisan`).
- The `s3` disk already exists in `config/filesystems.php` with keys: `key`, `secret`, `region`, `bucket`, `url`, `endpoint`, `use_path_style_endpoint`. Do not redefine the disk; only overwrite these values at runtime.
- Secrets (`secret`) must be stored encrypted (`addEncrypted` / `encrypted()`) and must never be sent to the browser.
- Only the `de` locale exists. All user-facing strings go through `trans('settings.storage.*')` with German values in `lang/de/settings.php`.
- Fallback rule: `filesystems.default` becomes `'s3'` only when `StorageSettings::isConfigured()` is true (`key`, `secret`, `bucket` all filled); otherwise it is left untouched (stays `local`).

---

### Task 1: `StorageSettings` class + settings migration

**Files:**
- Create: `app/Settings/StorageSettings.php`
- Create: `database/settings/2026_06_30_000001_create_storage_settings.php`
- Test: `tests/Unit/Settings/StorageSettingsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - Class `App\Settings\StorageSettings` with public props `?string $key`, `?string $secret`, `?string $region`, `?string $bucket`, `?string $endpoint`, `bool $use_path_style_endpoint`, `?string $url`.
  - `public static function group(): string` → `'storage'`.
  - `public static function encrypted(): array` → `['secret']`.
  - `public function isConfigured(): bool` → `filled($this->key) && filled($this->secret) && filled($this->bucket)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Settings/StorageSettingsTest.php`:

```php
<?php

namespace Tests\Unit\Settings;

use App\Settings\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_values_and_round_trips_the_secret(): void
    {
        $settings = app(StorageSettings::class);
        $settings->key = 'AKIA-test';
        $settings->secret = 'super-secret';
        $settings->region = 'eu-central-1';
        $settings->bucket = 'inventorix';
        $settings->endpoint = 'https://minio.example.test';
        $settings->use_path_style_endpoint = true;
        $settings->url = 'https://cdn.example.test';
        $settings->save();

        $fresh = app(StorageSettings::class)->refresh();
        $this->assertSame('AKIA-test', $fresh->key);
        $this->assertSame('super-secret', $fresh->secret);
        $this->assertSame('eu-central-1', $fresh->region);
        $this->assertSame('inventorix', $fresh->bucket);
        $this->assertSame('https://minio.example.test', $fresh->endpoint);
        $this->assertTrue($fresh->use_path_style_endpoint);
        $this->assertSame('https://cdn.example.test', $fresh->url);
    }

    public function test_is_configured_requires_key_secret_and_bucket(): void
    {
        $settings = app(StorageSettings::class);
        $settings->key = 'AKIA-test';
        $settings->secret = 'super-secret';
        $settings->bucket = null;
        $settings->save();
        $this->assertFalse($settings->isConfigured());

        $settings->bucket = 'inventorix';
        $settings->save();
        $this->assertTrue($settings->isConfigured());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev artisan test --filter=StorageSettingsTest`
Expected: FAIL — `Class "App\Settings\StorageSettings" not found`.

- [ ] **Step 3: Create the settings class**

Create `app/Settings/StorageSettings.php`:

```php
<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class StorageSettings extends Settings
{
    public ?string $key;

    public ?string $secret;

    public ?string $region;

    public ?string $bucket;

    public ?string $endpoint;

    public bool $use_path_style_endpoint;

    public ?string $url;

    public static function group(): string
    {
        return 'storage';
    }

    public static function encrypted(): array
    {
        return ['secret'];
    }

    public function isConfigured(): bool
    {
        return filled($this->key) && filled($this->secret) && filled($this->bucket);
    }
}
```

- [ ] **Step 4: Create the settings migration**

Create `database/settings/2026_06_30_000001_create_storage_settings.php`:

```php
<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('storage.key', env('AWS_ACCESS_KEY_ID'));
        $this->migrator->addEncrypted('storage.secret', env('AWS_SECRET_ACCESS_KEY'));
        $this->migrator->add('storage.region', env('AWS_DEFAULT_REGION', 'us-east-1'));
        $this->migrator->add('storage.bucket', env('AWS_BUCKET'));
        $this->migrator->add('storage.endpoint', env('AWS_ENDPOINT'));
        $this->migrator->add('storage.use_path_style_endpoint', (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', false));
        $this->migrator->add('storage.url', env('AWS_URL'));
    }
};
```

- [ ] **Step 5: Run test to verify it passes**

Run: `ddev artisan test --filter=StorageSettingsTest`
Expected: PASS (both tests). `RefreshDatabase` runs the settings migration automatically.

- [ ] **Step 6: Commit**

```bash
git add app/Settings/StorageSettings.php database/settings/2026_06_30_000001_create_storage_settings.php tests/Unit/Settings/StorageSettingsTest.php
git commit -m "feat(storage): StorageSettings class and migration

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Apply storage settings to runtime config (with fallback)

**Files:**
- Modify: `app/Support/ApplySettings.php`
- Test: `tests/Unit/Settings/ApplySettingsTest.php` (add cases)

**Interfaces:**
- Consumes: `App\Settings\StorageSettings` (Task 1), including `isConfigured()`.
- Produces: `App\Support\ApplySettings::applyStorage(StorageSettings $storage): void`, invoked from `__invoke()`. Writes `filesystems.disks.s3.*`; sets `filesystems.default` to `'s3'` only when configured.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/Settings/ApplySettingsTest.php` — add `use App\Settings\StorageSettings;` to the imports, then add these methods to the class:

```php
    public function test_it_applies_s3_disk_config_and_switches_default_when_configured(): void
    {
        $storage = app(StorageSettings::class);
        $storage->key = 'AKIA-test';
        $storage->secret = 'super-secret';
        $storage->region = 'eu-central-1';
        $storage->bucket = 'inventorix';
        $storage->endpoint = 'https://minio.example.test';
        $storage->use_path_style_endpoint = true;
        $storage->url = 'https://cdn.example.test';
        $storage->save();

        app(ApplySettings::class)();

        $this->assertSame('s3', config('filesystems.default'));
        $this->assertSame('AKIA-test', config('filesystems.disks.s3.key'));
        $this->assertSame('super-secret', config('filesystems.disks.s3.secret'));
        $this->assertSame('eu-central-1', config('filesystems.disks.s3.region'));
        $this->assertSame('inventorix', config('filesystems.disks.s3.bucket'));
        $this->assertSame('https://minio.example.test', config('filesystems.disks.s3.endpoint'));
        $this->assertTrue(config('filesystems.disks.s3.use_path_style_endpoint'));
        $this->assertSame('https://cdn.example.test', config('filesystems.disks.s3.url'));
    }

    public function test_it_keeps_local_default_when_storage_is_not_configured(): void
    {
        config(['filesystems.default' => 'local']);

        $storage = app(StorageSettings::class);
        $storage->key = 'AKIA-test';
        $storage->secret = 'super-secret';
        $storage->bucket = null; // incomplete
        $storage->save();

        app(ApplySettings::class)();

        $this->assertSame('local', config('filesystems.default'));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `ddev artisan test --filter=ApplySettingsTest`
Expected: FAIL — the two new tests fail (default stays unchanged / s3 config not written) while the existing mail/general/auth tests still pass.

- [ ] **Step 3: Implement `applyStorage` and wire it in**

In `app/Support/ApplySettings.php`, add the import near the other `use App\Settings\...` lines:

```php
use App\Settings\StorageSettings;
```

Add the call inside `__invoke()` after `$this->applyAuth(...)`:

```php
        $this->applyStorage(app(StorageSettings::class));
```

Add the method (place it after `applyAuth`):

```php
    protected function applyStorage(StorageSettings $storage): void
    {
        // Reload from repository so long-lived Octane/Horizon workers never serve stale values.
        $storage->refresh();

        config([
            'filesystems.disks.s3.key' => $storage->key,
            'filesystems.disks.s3.secret' => $storage->secret,
            'filesystems.disks.s3.region' => $storage->region,
            'filesystems.disks.s3.bucket' => $storage->bucket,
            'filesystems.disks.s3.url' => $storage->url,
            'filesystems.disks.s3.endpoint' => $storage->endpoint,
            'filesystems.disks.s3.use_path_style_endpoint' => $storage->use_path_style_endpoint,
        ]);

        // S3-only intent, but fall back to local until the config is complete so
        // fresh installs don't hard-fail on uploads.
        if ($storage->isConfigured()) {
            config(['filesystems.default' => 's3']);
        }
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `ddev artisan test --filter=ApplySettingsTest`
Expected: PASS (all tests, including the two new ones and the pre-existing ones).

- [ ] **Step 5: Commit**

```bash
git add app/Support/ApplySettings.php tests/Unit/Settings/ApplySettingsTest.php
git commit -m "feat(storage): apply S3 settings to runtime config with local fallback

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: `ManageStorageSettings` page, translations, and connection test

**Files:**
- Create: `app/Filament/App/Clusters/Settings/Pages/ManageStorageSettings.php`
- Modify: `lang/de/settings.php`
- Test: `tests/Feature/Filament/ManageStorageSettingsTest.php`

**Interfaces:**
- Consumes: `App\Settings\StorageSettings` (Task 1), `App\Support\ApplySettings` (Task 2), `App\Filament\App\Clusters\Settings` (existing cluster).
- Produces: Livewire/Filament page `ManageStorageSettings` with form fields matching the settings props and a `testConnection` header action.

- [ ] **Step 1: Add translation keys**

In `lang/de/settings.php`, add a `'storage' => [...]` block inside the top-level return array (e.g. directly after the `'mail' => [...]` block):

```php
    'storage' => [
        'nav' => 'Speicher',
        'title' => 'Speicher-Einstellungen',
        'section' => [
            's3' => 'S3-Speicher',
        ],
        'field' => [
            'key' => 'Access Key ID',
            'secret' => 'Secret Access Key',
            'region' => 'Region',
            'bucket' => 'Bucket',
            'endpoint' => 'Endpoint',
            'endpoint_help' => 'Nur für S3-kompatible Anbieter (MinIO, Cloudflare R2, DigitalOcean Spaces, …). Für echtes AWS S3 leer lassen.',
            'use_path_style_endpoint' => 'Path-Style-Endpoint verwenden',
            'use_path_style_endpoint_help' => 'Bei den meisten S3-kompatiblen Anbietern (z. B. MinIO) erforderlich.',
            'url' => 'Öffentliche/CDN-URL',
            'url_help' => 'Optionale Basis-URL für öffentliche Datei-Links (z. B. CDN). Leer lassen, um die Standard-URL des Anbieters zu nutzen.',
        ],
        'test' => [
            'action' => 'Verbindung testen',
            'success_title' => 'Verbindung erfolgreich',
            'success_body' => 'Eine Testdatei wurde im Bucket geschrieben, gelesen und wieder gelöscht.',
            'failure_title' => 'Verbindung fehlgeschlagen',
        ],
    ],
```

- [ ] **Step 2: Write the failing feature tests**

Create `tests/Feature/Filament/ManageStorageSettingsTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Clusters\Settings\Pages\ManageStorageSettings;
use App\Models\User;
use App\Settings\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ManageStorageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_s3_settings(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageStorageSettings::class)
            ->fillForm([
                'key' => 'AKIA-test',
                'secret' => 'super-secret',
                'region' => 'eu-central-1',
                'bucket' => 'inventorix',
                'endpoint' => 'https://minio.example.test',
                'use_path_style_endpoint' => true,
                'url' => 'https://cdn.example.test',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(StorageSettings::class)->refresh();
        $this->assertSame('AKIA-test', $settings->key);
        $this->assertSame('super-secret', $settings->secret);
        $this->assertSame('inventorix', $settings->bucket);
        $this->assertTrue($settings->use_path_style_endpoint);
    }

    public function test_blank_secret_keeps_the_existing_value(): void
    {
        $existing = app(StorageSettings::class);
        $existing->key = 'AKIA-test';
        $existing->secret = 'original-secret';
        $existing->region = 'eu-central-1';
        $existing->bucket = 'inventorix';
        $existing->endpoint = null;
        $existing->use_path_style_endpoint = false;
        $existing->url = null;
        $existing->save();

        $this->actingAs(User::factory()->create());

        Livewire::test(ManageStorageSettings::class)
            ->fillForm([
                'bucket' => 'changed-bucket',
                'secret' => '', // left blank on purpose
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(StorageSettings::class)->refresh();
        $this->assertSame('changed-bucket', $settings->bucket);
        $this->assertSame('original-secret', $settings->secret);
    }

    public function test_it_does_not_expose_the_secret_to_the_form(): void
    {
        $existing = app(StorageSettings::class);
        $existing->key = 'AKIA-test';
        $existing->secret = 'original-secret';
        $existing->region = 'eu-central-1';
        $existing->bucket = 'inventorix';
        $existing->endpoint = null;
        $existing->use_path_style_endpoint = false;
        $existing->url = null;
        $existing->save();

        $this->actingAs(User::factory()->create());

        Livewire::test(ManageStorageSettings::class)
            ->assertFormSet([
                'key' => 'AKIA-test',
                'secret' => null,
            ]);
    }

    public function test_connection_test_action_succeeds(): void
    {
        Storage::fake('s3');

        $this->actingAs(User::factory()->create());

        Livewire::test(ManageStorageSettings::class)
            ->fillForm([
                'key' => 'AKIA-test',
                'secret' => 'super-secret',
                'region' => 'eu-central-1',
                'bucket' => 'inventorix',
            ])
            ->call('save')
            ->callAction('testConnection')
            ->assertNotified(trans('settings.storage.test.success_title'));
    }

    public function test_connection_test_action_notifies_on_failure(): void
    {
        Storage::shouldReceive('disk')
            ->with('s3')
            ->andThrow(new \RuntimeException('Could not reach endpoint'));

        $this->actingAs(User::factory()->create());

        Livewire::test(ManageStorageSettings::class)
            ->fillForm([
                'key' => 'AKIA-test',
                'secret' => 'super-secret',
                'region' => 'eu-central-1',
                'bucket' => 'inventorix',
            ])
            ->call('save')
            ->callAction('testConnection')
            ->assertNotified(trans('settings.storage.test.failure_title'));
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `ddev artisan test --filter=ManageStorageSettingsTest`
Expected: FAIL — `Class "App\Filament\App\Clusters\Settings\Pages\ManageStorageSettings" not found`.

- [ ] **Step 4: Create the page**

Create `app/Filament/App/Clusters/Settings/Pages/ManageStorageSettings.php`:

```php
<?php

namespace App\Filament\App\Clusters\Settings\Pages;

use App\Filament\App\Clusters\Settings;
use App\Settings\StorageSettings;
use App\Support\ApplySettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ManageStorageSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $cluster = Settings::class;

    protected static string $settings = StorageSettings::class;

    protected bool $suppressSavedNotification = false;

    protected static ?int $navigationSort = 50;

    /**
     * Secret fields are never sent to the browser; a blank submit keeps the stored value.
     */
    protected array $secretFields = ['secret'];

    public static function getNavigationLabel(): string
    {
        return trans('settings.storage.nav');
    }

    public function getTitle(): string
    {
        return trans('settings.storage.title');
    }

    public function getSavedNotification(): ?Notification
    {
        if ($this->suppressSavedNotification) {
            return null;
        }

        return parent::getSavedNotification();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(trans('settings.storage.section.s3'))
                    ->schema([
                        TextInput::make('key')->label(trans('settings.storage.field.key')),
                        TextInput::make('secret')
                            ->label(trans('settings.storage.field.secret'))
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('region')->label(trans('settings.storage.field.region'))->default('us-east-1'),
                        TextInput::make('bucket')->label(trans('settings.storage.field.bucket')),
                        TextInput::make('endpoint')
                            ->label(trans('settings.storage.field.endpoint'))
                            ->url()
                            ->helperText(trans('settings.storage.field.endpoint_help')),
                        Toggle::make('use_path_style_endpoint')
                            ->label(trans('settings.storage.field.use_path_style_endpoint'))
                            ->helperText(trans('settings.storage.field.use_path_style_endpoint_help')),
                        TextInput::make('url')
                            ->label(trans('settings.storage.field.url'))
                            ->url()
                            ->helperText(trans('settings.storage.field.url_help')),
                    ])
                    ->columns(2),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label(trans('settings.storage.test.action'))
                ->icon(Heroicon::OutlinedSignal)
                ->action(function (): void {
                    // Persist current form state (suppress the "Saved" toast — we show our own
                    // result below), then apply it so the test uses what is on screen.
                    $this->suppressSavedNotification = true;
                    try {
                        $this->save();
                    } finally {
                        $this->suppressSavedNotification = false;
                    }
                    app(ApplySettings::class)();

                    try {
                        $disk = Storage::disk('s3');
                        $probe = 'inventorix-connection-test-'.Str::random(16).'.txt';
                        $disk->put($probe, 'ok');
                        $disk->get($probe);
                        $disk->delete($probe);

                        Notification::make()
                            ->title(trans('settings.storage.test.success_title'))
                            ->body(trans('settings.storage.test.success_body'))
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(trans('settings.storage.test.failure_title'))
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Never expose stored secrets to the browser.
        foreach ($this->secretFields as $field) {
            $data[$field] = null;
        }

        return $data;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `ddev artisan test --filter=ManageStorageSettingsTest`
Expected: PASS (all five tests).

- [ ] **Step 6: Run the full settings test suite for regressions**

Run: `ddev artisan test --filter=Settings`
Expected: PASS — Storage, ApplySettings, and other settings tests all green.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/App/Clusters/Settings/Pages/ManageStorageSettings.php lang/de/settings.php tests/Feature/Filament/ManageStorageSettingsTest.php
git commit -m "feat(storage): Storage settings page with S3 config and connection test

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review Notes

- **Spec coverage:** StorageSettings + migration (Task 1) ✓; runtime apply + fallback by `isConfigured()` (Task 2) ✓; Filament page with all fields, encrypted secret handling, endpoint/path-style/url (Task 3) ✓; test connection action (Task 3) ✓; translations (Task 3) ✓; tests for persistence/secret-never-leaks/blank-secret-kept/default-switch-when-complete/stays-local-when-incomplete (Tasks 1–3) ✓.
- **Out of scope (per spec):** migrating existing local files to S3; per-disk visibility/ACL — intentionally not in this plan.
- **Type consistency:** `isConfigured()` defined in Task 1, used in Task 2; `secretFields`/`testConnection`/`suppressSavedNotification` names consistent across Task 3 page and tests; `s3` disk config keys match `config/filesystems.php`.
- **Verify before checking the "icon" off:** `Heroicon::OutlinedSignal` and `Heroicon::OutlinedCircleStack` are assumed to exist in the Filament icon enum; if either is missing, substitute an existing outlined icon (e.g. `OutlinedCloud` / `OutlinedServerStack`) — this is the only non-verified symbol in the plan.
```

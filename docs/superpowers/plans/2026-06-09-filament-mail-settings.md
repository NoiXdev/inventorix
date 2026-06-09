# Filament Mail Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let admins configure mail delivery (and `app.name`) through a Filament Settings cluster instead of `.env`, stored in the DB and applied to runtime config without worker restarts.

**Architecture:** Two `spatie/laravel-settings` classes (`MailSettings`, `GeneralSettings`) back DB rows seeded from current env. A single invokable `ApplySettings` action refreshes those settings from the repository and writes them into Laravel's runtime `config('mail.*' | 'services.*' | 'postal.*' | 'app.name')`, then purges the resolved mailer. It runs once per web request (panel middleware) and once per queued job (`JobProcessing` listener), so it stays current under Octane + Horizon. A Filament `Settings` cluster hosts `ManageMailSettings` (driver picker + masked secrets + test-email action) and `ManageGeneralSettings`.

**Tech Stack:** Laravel 13, Filament 5, `spatie/laravel-settings`, `filament/spatie-laravel-settings-plugin`, `synergitech/laravel-postal` (installed), plus `aws/aws-sdk-php`, `symfony/postmark-mailer`, `resend/resend-php` for the SES/Postmark/Resend transports. PHPUnit (class-based) + Livewire testing. All CLI commands run through **ddev**.

---

## Notes for the implementer

- **Run everything through ddev**: `ddev composer ...`, `ddev artisan ...`. Never call bare `php`/`composer`.
- **Tests are class-based PHPUnit** extending `Tests\TestCase`, using `RefreshDatabase` and `Livewire::test(...)`. Match that style — do not write Pest `it()` functions.
- **`RefreshDatabase` runs the spatie settings migrations** (in `database/settings/`) automatically, so settings rows exist and are seeded in every test.
- **Secrets** use spatie's `encrypted()` mechanism. The Filament form never shows stored secret values and a blank secret submit keeps the existing value.
- **Why `->refresh()` in `ApplySettings`:** spatie binds each settings class as a singleton. Under Octane/Horizon a worker holds that instance across requests/jobs, so it would go stale after an admin saves elsewhere. Calling `->refresh()` reloads from the repository (which spatie keeps cache-coherent on save), guaranteeing fresh values every request/job. This replaces any manual cache-busting.

---

## File structure

```
app/Settings/MailSettings.php                                  (new) typed mail settings
app/Settings/GeneralSettings.php                               (new) typed general settings
app/Support/ApplySettings.php                                  (new) settings -> runtime config
app/Mail/TestMail.php                                          (new) test email mailable
app/Http/Middleware/ApplyRuntimeSettings.php                   (new) applies settings per web request
app/Listeners/ApplySettingsToJob.php                           (new) applies settings per queued job
app/Filament/App/Clusters/Settings.php                         (new) Settings cluster
app/Filament/App/Clusters/Settings/Pages/ManageGeneralSettings.php  (new)
app/Filament/App/Clusters/Settings/Pages/ManageMailSettings.php     (new)
database/settings/2026_06_09_000001_create_mail_settings.php   (new)
database/settings/2026_06_09_000002_create_general_settings.php(new)
config/settings.php                                            (published by spatie)
app/Providers/Filament/AppPanelProvider.php                    (modify) register cluster, pages, middleware
app/Providers/AppServiceProvider.php                           (modify) register JobProcessing listener
resources/views/mail/test.blade.php                            (new) test email body
tests/Unit/Settings/ApplySettingsTest.php                      (new)
tests/Feature/Filament/ManageGeneralSettingsTest.php           (new)
tests/Feature/Filament/ManageMailSettingsTest.php              (new)
```

---

## Task 1: Install packages and publish spatie settings config

**Files:**
- Modify: `composer.json` (via composer require)
- Create: `config/settings.php` (published)

- [ ] **Step 1: Require the runtime packages**

Run:
```bash
ddev composer require spatie/laravel-settings filament/spatie-laravel-settings-plugin:"^5.0" aws/aws-sdk-php symfony/postmark-mailer resend/resend-php
```
Expected: composer resolves and writes to `composer.json` / `composer.lock` with no errors. If `filament/spatie-laravel-settings-plugin:"^5.0"` fails to resolve, retry without the version constraint: `ddev composer require filament/spatie-laravel-settings-plugin`.

- [ ] **Step 2: Publish the spatie settings config and migration**

Run:
```bash
ddev artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag=settings
```
Expected: creates `config/settings.php` and a migration that creates the `settings` table. The published config's `setting_migrations_path` should point at `database/settings`.

- [ ] **Step 3: Run migrations to verify the settings table is created**

Run:
```bash
ddev artisan migrate
```
Expected: the create-`settings`-table migration runs successfully.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock config/settings.php database/migrations
git commit -m "chore: install spatie settings + filament plugin + mail transports"
```

---

## Task 2: `MailSettings` class

**Files:**
- Create: `app/Settings/MailSettings.php`

- [ ] **Step 1: Write the settings class**

```php
<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MailSettings extends Settings
{
    public string $default_mailer;

    public string $from_address;

    public string $from_name;

    // SMTP
    public ?string $smtp_host;

    public ?int $smtp_port;

    public ?string $smtp_scheme;

    public ?string $smtp_username;

    public ?string $smtp_password;

    // SES
    public ?string $ses_key;

    public ?string $ses_secret;

    public ?string $ses_region;

    // Postmark
    public ?string $postmark_token;

    public ?string $postmark_message_stream_id;

    // Resend
    public ?string $resend_key;

    // Postal (synergitech/laravel-postal)
    public ?string $postal_domain;

    public ?string $postal_key;

    public static function group(): string
    {
        return 'mail';
    }

    public static function encrypted(): array
    {
        return [
            'smtp_password',
            'ses_secret',
            'postmark_token',
            'resend_key',
            'postal_key',
        ];
    }
}
```

- [ ] **Step 2: Commit (migration in next task makes it loadable)**

```bash
git add app/Settings/MailSettings.php
git commit -m "feat: add MailSettings settings class"
```

---

## Task 3: `MailSettings` migration seeded from env

**Files:**
- Create: `database/settings/2026_06_09_000001_create_mail_settings.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mail.default_mailer', env('MAIL_MAILER', 'log'));
        $this->migrator->add('mail.from_address', env('MAIL_FROM_ADDRESS', 'hello@example.com'));
        $this->migrator->add('mail.from_name', env('MAIL_FROM_NAME', env('APP_NAME', 'Inventorix')));

        // SMTP
        $this->migrator->add('mail.smtp_host', env('MAIL_HOST', '127.0.0.1'));
        $this->migrator->add('mail.smtp_port', (int) env('MAIL_PORT', 2525));
        $this->migrator->add('mail.smtp_scheme', env('MAIL_SCHEME'));
        $this->migrator->add('mail.smtp_username', env('MAIL_USERNAME'));
        $this->migrator->addEncrypted('mail.smtp_password', env('MAIL_PASSWORD'));

        // SES
        $this->migrator->add('mail.ses_key', env('AWS_ACCESS_KEY_ID'));
        $this->migrator->addEncrypted('mail.ses_secret', env('AWS_SECRET_ACCESS_KEY'));
        $this->migrator->add('mail.ses_region', env('AWS_DEFAULT_REGION', 'us-east-1'));

        // Postmark
        $this->migrator->addEncrypted('mail.postmark_token', env('POSTMARK_API_KEY'));
        $this->migrator->add('mail.postmark_message_stream_id', env('POSTMARK_MESSAGE_STREAM_ID'));

        // Resend
        $this->migrator->addEncrypted('mail.resend_key', env('RESEND_API_KEY'));

        // Postal
        $this->migrator->add('mail.postal_domain', env('POSTAL_DOMAIN'));
        $this->migrator->addEncrypted('mail.postal_key', env('POSTAL_KEY'));
    }
};
```

- [ ] **Step 2: Run the settings migration**

Run:
```bash
ddev artisan migrate
```
Expected: the migration runs and the `settings` table now contains the `mail.*` rows.

- [ ] **Step 3: Verify the settings class loads (tinker smoke check)**

Run:
```bash
ddev artisan tinker --execute="echo app(\App\Settings\MailSettings::class)->default_mailer;"
```
Expected: prints the current `MAIL_MAILER` value (e.g. `log`) with no `MissingSettings` exception.

- [ ] **Step 4: Commit**

```bash
git add database/settings/2026_06_09_000001_create_mail_settings.php
git commit -m "feat: seed mail settings from env"
```

---

## Task 4: `GeneralSettings` class + migration

**Files:**
- Create: `app/Settings/GeneralSettings.php`
- Create: `database/settings/2026_06_09_000002_create_general_settings.php`

- [ ] **Step 1: Write the settings class**

```php
<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $app_name;

    public static function group(): string
    {
        return 'general';
    }
}
```

- [ ] **Step 2: Write the migration**

```php
<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.app_name', config('app.name', 'Inventorix'));
    }
};
```

- [ ] **Step 3: Run the settings migration**

Run:
```bash
ddev artisan migrate
```
Expected: migration runs; `general.app_name` row created.

- [ ] **Step 4: Commit**

```bash
git add app/Settings/GeneralSettings.php database/settings/2026_06_09_000002_create_general_settings.php
git commit -m "feat: add GeneralSettings with app_name"
```

---

## Task 5: `ApplySettings` action (TDD)

**Files:**
- Create: `app/Support/ApplySettings.php`
- Test: `tests/Unit/Settings/ApplySettingsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Settings;

use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use App\Support\ApplySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_smtp_settings_to_runtime_config(): void
    {
        $mail = app(MailSettings::class);
        $mail->default_mailer = 'smtp';
        $mail->from_address = 'noreply@example.test';
        $mail->from_name = 'Inventorix Test';
        $mail->smtp_host = 'mail.example.test';
        $mail->smtp_port = 587;
        $mail->smtp_scheme = 'tls';
        $mail->smtp_username = 'user';
        $mail->smtp_password = 'secret';
        $mail->save();

        app(ApplySettings::class)();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('noreply@example.test', config('mail.from.address'));
        $this->assertSame('Inventorix Test', config('mail.from.name'));
        $this->assertSame('mail.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('tls', config('mail.mailers.smtp.scheme'));
        $this->assertSame('user', config('mail.mailers.smtp.username'));
        $this->assertSame('secret', config('mail.mailers.smtp.password'));
    }

    public function test_it_applies_postal_settings_and_registers_the_mailer(): void
    {
        $mail = app(MailSettings::class);
        $mail->default_mailer = 'postal';
        $mail->postal_domain = 'https://postal.example.test';
        $mail->postal_key = 'postal-key';
        $mail->save();

        app(ApplySettings::class)();

        $this->assertSame('postal', config('mail.default'));
        $this->assertSame('postal', config('mail.mailers.postal.transport'));
        $this->assertSame('https://postal.example.test', config('postal.domain'));
        $this->assertSame('postal-key', config('postal.key'));
    }

    public function test_it_applies_general_app_name(): void
    {
        $general = app(GeneralSettings::class);
        $general->app_name = 'My Inventory';
        $general->save();

        app(ApplySettings::class)();

        $this->assertSame('My Inventory', config('app.name'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run:
```bash
ddev artisan test --filter=ApplySettingsTest
```
Expected: FAIL — `Class "App\Support\ApplySettings" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php

namespace App\Support;

use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use Illuminate\Support\Facades\Mail;

class ApplySettings
{
    public function __invoke(): void
    {
        $this->applyGeneral(app(GeneralSettings::class));
        $this->applyMail(app(MailSettings::class));
    }

    protected function applyGeneral(GeneralSettings $general): void
    {
        // Reload from repository so long-lived Octane/Horizon workers never serve stale values.
        $general->refresh();

        config(['app.name' => $general->app_name]);
    }

    protected function applyMail(MailSettings $mail): void
    {
        $mail->refresh();

        config([
            'mail.default' => $mail->default_mailer,
            'mail.from.address' => $mail->from_address,
            'mail.from.name' => $mail->from_name,
        ]);

        match ($mail->default_mailer) {
            'smtp' => config([
                'mail.mailers.smtp.host' => $mail->smtp_host,
                'mail.mailers.smtp.port' => $mail->smtp_port,
                'mail.mailers.smtp.scheme' => $mail->smtp_scheme,
                'mail.mailers.smtp.username' => $mail->smtp_username,
                'mail.mailers.smtp.password' => $mail->smtp_password,
            ]),
            'ses' => config([
                'services.ses.key' => $mail->ses_key,
                'services.ses.secret' => $mail->ses_secret,
                'services.ses.region' => $mail->ses_region,
            ]),
            'postmark' => config([
                'services.postmark.token' => $mail->postmark_token,
                'mail.mailers.postmark.message_stream_id' => $mail->postmark_message_stream_id,
            ]),
            'resend' => config([
                'services.resend.key' => $mail->resend_key,
            ]),
            'postal' => config([
                'mail.mailers.postal' => ['transport' => 'postal'],
                'postal.domain' => $mail->postal_domain,
                'postal.key' => $mail->postal_key,
            ]),
            default => null,
        };

        // Drop any mailer instance resolved with the previous config so the next send rebuilds it.
        Mail::purge($mail->default_mailer);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run:
```bash
ddev artisan test --filter=ApplySettingsTest
```
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/ApplySettings.php tests/Unit/Settings/ApplySettingsTest.php
git commit -m "feat: apply DB mail/general settings to runtime config"
```

---

## Task 6: Web middleware to apply settings per request

**Files:**
- Create: `app/Http/Middleware/ApplyRuntimeSettings.php`
- Modify: `app/Providers/Filament/AppPanelProvider.php` (middleware array)

- [ ] **Step 1: Write the middleware**

```php
<?php

namespace App\Http\Middleware;

use App\Support\ApplySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyRuntimeSettings
{
    public function __construct(protected ApplySettings $applySettings) {}

    public function handle(Request $request, Closure $next): Response
    {
        ($this->applySettings)();

        return $next($request);
    }
}
```

- [ ] **Step 2: Register the middleware on the App panel**

In `app/Providers/Filament/AppPanelProvider.php`, add the import near the other `use` statements:

```php
use App\Http\Middleware\ApplyRuntimeSettings;
```

Then add `ApplyRuntimeSettings::class` to the end of the `->middleware([...])` array (after `DispatchServingFilamentEvent::class`):

```php
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                ApplyRuntimeSettings::class,
            ])
```

- [ ] **Step 3: Verify the app still boots**

Run:
```bash
ddev artisan route:list --path=app >/dev/null && echo OK
```
Expected: prints `OK` with no exception (panel middleware resolves).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Middleware/ApplyRuntimeSettings.php app/Providers/Filament/AppPanelProvider.php
git commit -m "feat: apply runtime mail settings on each panel request"
```

---

## Task 7: Queue listener to apply settings per job

**Files:**
- Create: `app/Listeners/ApplySettingsToJob.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Write the listener**

```php
<?php

namespace App\Listeners;

use App\Support\ApplySettings;
use Illuminate\Queue\Events\JobProcessing;

class ApplySettingsToJob
{
    public function __construct(protected ApplySettings $applySettings) {}

    public function handle(JobProcessing $event): void
    {
        ($this->applySettings)();
    }
}
```

- [ ] **Step 2: Register the listener**

In `app/Providers/AppServiceProvider.php`, add imports at the top:

```php
use App\Listeners\ApplySettingsToJob;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
```

In the `boot()` method, add:

```php
        Event::listen(JobProcessing::class, ApplySettingsToJob::class);
```

- [ ] **Step 3: Verify registration**

Run:
```bash
ddev artisan event:list | grep -i JobProcessing
```
Expected: lists `App\Listeners\ApplySettingsToJob` under `Illuminate\Queue\Events\JobProcessing`.

- [ ] **Step 4: Commit**

```bash
git add app/Listeners/ApplySettingsToJob.php app/Providers/AppServiceProvider.php
git commit -m "feat: apply runtime mail settings before each queued job"
```

---

## Task 8: Settings cluster + register in panel

**Files:**
- Create: `app/Filament/App/Clusters/Settings.php`
- Modify: `app/Providers/Filament/AppPanelProvider.php`

- [ ] **Step 1: Write the cluster class**

```php
<?php

namespace App\Filament\App\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class Settings extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Settings';
}
```

- [ ] **Step 2: Register the cluster on the panel**

In `app/Providers/Filament/AppPanelProvider.php`, add the import:

```php
use App\Filament\App\Clusters\Settings as SettingsCluster;
```

Add a `->clusters([...])` call to the panel chain, right after the `->pages([...])` block:

```php
            ->clusters([
                SettingsCluster::class,
            ])
```

- [ ] **Step 3: Verify it boots**

Run:
```bash
ddev artisan route:list --path=app >/dev/null && echo OK
```
Expected: prints `OK`. (The cluster has no pages yet, so no settings routes appear until the next tasks; this just confirms the class resolves.)

- [ ] **Step 4: Commit**

```bash
git add app/Filament/App/Clusters/Settings.php app/Providers/Filament/AppPanelProvider.php
git commit -m "feat: add Settings cluster to app panel"
```

---

## Task 9: `ManageGeneralSettings` page (TDD)

**Files:**
- Create: `app/Filament/App/Clusters/Settings/Pages/ManageGeneralSettings.php`
- Modify: `app/Providers/Filament/AppPanelProvider.php` (register page)
- Test: `tests/Feature/Filament/ManageGeneralSettingsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Clusters\Settings\Pages\ManageGeneralSettings;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageGeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_the_app_name(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageGeneralSettings::class)
            ->fillForm(['app_name' => 'My Inventory'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('My Inventory', app(GeneralSettings::class)->refresh()->app_name);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run:
```bash
ddev artisan test --filter=ManageGeneralSettingsTest
```
Expected: FAIL — `Class "App\Filament\App\Clusters\Settings\Pages\ManageGeneralSettings" not found`.

- [ ] **Step 3: Write the page**

```php
<?php

namespace App\Filament\App\Clusters\Settings\Pages;

use App\Filament\App\Clusters\Settings;
use App\Settings\GeneralSettings;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use BackedEnum;

class ManageGeneralSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'General';

    protected static ?string $title = 'General settings';

    protected static ?string $cluster = Settings::class;

    protected static string $settings = GeneralSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('app_name')
                    ->label('Application name')
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
```

> If the `filament/spatie-laravel-settings-plugin` for v5 exposes the form via the older `form(Form $form): Form` signature instead of `Schema`, match whatever signature the installed `Filament\Pages\SettingsPage` base class declares — check `vendor/filament/spatie-laravel-settings-plugin/src/SettingsPage.php`. The component list (`TextInput::make('app_name')...`) is identical either way.

- [ ] **Step 4: Register the page on the panel**

In `app/Providers/Filament/AppPanelProvider.php`, add the import:

```php
use App\Filament\App\Clusters\Settings\Pages\ManageGeneralSettings;
```

Add the page to the `->pages([...])` array:

```php
            ->pages([
                Dashboard::class,
                ManageGeneralSettings::class,
            ])
```

- [ ] **Step 5: Run the test to verify it passes**

Run:
```bash
ddev artisan test --filter=ManageGeneralSettingsTest
```
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/App/Clusters/Settings/Pages/ManageGeneralSettings.php app/Providers/Filament/AppPanelProvider.php tests/Feature/Filament/ManageGeneralSettingsTest.php
git commit -m "feat: add General settings page to Settings cluster"
```

---

## Task 10: `ManageMailSettings` page with driver picker and masked secrets (TDD)

**Files:**
- Create: `app/Filament/App/Clusters/Settings/Pages/ManageMailSettings.php`
- Modify: `app/Providers/Filament/AppPanelProvider.php` (register page)
- Test: `tests/Feature/Filament/ManageMailSettingsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Clusters\Settings\Pages\ManageMailSettings;
use App\Models\User;
use App\Settings\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageMailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_smtp_settings(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageMailSettings::class)
            ->fillForm([
                'default_mailer' => 'smtp',
                'from_address' => 'noreply@example.test',
                'from_name' => 'Inventorix',
                'smtp_host' => 'mail.example.test',
                'smtp_port' => 587,
                'smtp_scheme' => 'tls',
                'smtp_username' => 'user',
                'smtp_password' => 'secret',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(MailSettings::class)->refresh();
        $this->assertSame('smtp', $settings->default_mailer);
        $this->assertSame('mail.example.test', $settings->smtp_host);
        $this->assertSame('secret', $settings->smtp_password);
    }

    public function test_blank_secret_keeps_the_existing_value(): void
    {
        $existing = app(MailSettings::class);
        $existing->default_mailer = 'smtp';
        $existing->from_address = 'noreply@example.test';
        $existing->from_name = 'Inventorix';
        $existing->smtp_host = 'mail.example.test';
        $existing->smtp_port = 587;
        $existing->smtp_password = 'original-secret';
        $existing->save();

        $this->actingAs(User::factory()->create());

        Livewire::test(ManageMailSettings::class)
            ->fillForm([
                'from_name' => 'Changed Name',
                'smtp_password' => '', // left blank on purpose
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(MailSettings::class)->refresh();
        $this->assertSame('Changed Name', $settings->from_name);
        $this->assertSame('original-secret', $settings->smtp_password);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run:
```bash
ddev artisan test --filter=ManageMailSettingsTest
```
Expected: FAIL — `Class "App\Filament\App\Clusters\Settings\Pages\ManageMailSettings" not found`.

- [ ] **Step 3: Write the page**

```php
<?php

namespace App\Filament\App\Clusters\Settings\Pages;

use App\Filament\App\Clusters\Settings;
use App\Settings\MailSettings;
use BackedEnum;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageMailSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Mail';

    protected static ?string $title = 'Mail settings';

    protected static ?string $cluster = Settings::class;

    protected static string $settings = MailSettings::class;

    /**
     * Secret fields are never sent to the browser; a blank submit keeps the stored value.
     */
    protected array $secretFields = [
        'smtp_password',
        'ses_secret',
        'postmark_token',
        'resend_key',
        'postal_key',
    ];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('From')
                    ->schema([
                        TextInput::make('from_address')
                            ->label('From address')
                            ->email()
                            ->required(),
                        TextInput::make('from_name')
                            ->label('From name')
                            ->required(),
                    ])
                    ->columns(2),

                Select::make('default_mailer')
                    ->label('Mail driver')
                    ->options([
                        'smtp' => 'SMTP',
                        'postal' => 'Postal',
                        'ses' => 'Amazon SES',
                        'postmark' => 'Postmark',
                        'resend' => 'Resend',
                        'sendmail' => 'Sendmail',
                        'log' => 'Log (no delivery)',
                    ])
                    ->required()
                    ->live(),

                Section::make('SMTP')
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'smtp')
                    ->schema([
                        TextInput::make('smtp_host')->label('Host')->required(),
                        TextInput::make('smtp_port')->label('Port')->numeric()->required(),
                        Select::make('smtp_scheme')
                            ->label('Encryption')
                            ->options(['tls' => 'TLS', 'ssl' => 'SSL'])
                            ->placeholder('None'),
                        TextInput::make('smtp_username')->label('Username'),
                        TextInput::make('smtp_password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ])
                    ->columns(2),

                Section::make('Amazon SES')
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'ses')
                    ->schema([
                        TextInput::make('ses_key')->label('Access key ID'),
                        TextInput::make('ses_secret')
                            ->label('Secret access key')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('ses_region')->label('Region')->default('us-east-1'),
                    ])
                    ->columns(2),

                Section::make('Postmark')
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'postmark')
                    ->schema([
                        TextInput::make('postmark_token')
                            ->label('Server token')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('postmark_message_stream_id')->label('Message stream ID'),
                    ])
                    ->columns(2),

                Section::make('Resend')
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'resend')
                    ->schema([
                        TextInput::make('resend_key')
                            ->label('API key')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ]),

                Section::make('Postal')
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'postal')
                    ->schema([
                        TextInput::make('postal_domain')
                            ->label('Server URL')
                            ->helperText('The HTTPS URL of your Postal server.')
                            ->url(),
                        TextInput::make('postal_key')
                            ->label('API key')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ])
                    ->columns(2),
            ]);
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

> Signature note (same as Task 9): if the installed v5 `SettingsPage` uses `form(Form $form): Form`, adapt the method signature and the `Get` import (`Filament\Forms\Get`) to match the base class. The component definitions are unchanged.

- [ ] **Step 4: Register the page on the panel**

In `app/Providers/Filament/AppPanelProvider.php`, add the import:

```php
use App\Filament\App\Clusters\Settings\Pages\ManageMailSettings;
```

Add the page to the `->pages([...])` array (alongside `ManageGeneralSettings`):

```php
            ->pages([
                Dashboard::class,
                ManageGeneralSettings::class,
                ManageMailSettings::class,
            ])
```

- [ ] **Step 5: Run the test to verify it passes**

Run:
```bash
ddev artisan test --filter=ManageMailSettingsTest
```
Expected: PASS (2 tests). The blank-secret test confirms `dehydrated(fn ($state) => filled($state))` preserves the stored value.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/App/Clusters/Settings/Pages/ManageMailSettings.php app/Providers/Filament/AppPanelProvider.php tests/Feature/Filament/ManageMailSettingsTest.php
git commit -m "feat: add Mail settings page with driver picker and masked secrets"
```

---

## Task 11: Test-email mailable + action (TDD)

**Files:**
- Create: `app/Mail/TestMail.php`
- Create: `resources/views/mail/test.blade.php`
- Modify: `app/Filament/App/Clusters/Settings/Pages/ManageMailSettings.php` (add header action)
- Test: extend `tests/Feature/Filament/ManageMailSettingsTest.php`

- [ ] **Step 1: Write the failing test (add to `ManageMailSettingsTest`)**

Add these imports at the top of the existing test file:

```php
use App\Mail\TestMail;
use Illuminate\Support\Facades\Mail as MailFacade;
```

Add this method to the class:

```php
    public function test_send_test_email_action_dispatches_a_test_mail(): void
    {
        MailFacade::fake();

        $user = User::factory()->create(['email' => 'admin@example.test']);
        $this->actingAs($user);

        Livewire::test(ManageMailSettings::class)
            ->fillForm([
                'default_mailer' => 'log',
                'from_address' => 'noreply@example.test',
                'from_name' => 'Inventorix',
            ])
            ->call('save')
            ->callAction('sendTest', data: ['email' => 'admin@example.test']);

        MailFacade::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('admin@example.test'));
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run:
```bash
ddev artisan test --filter=test_send_test_email_action_dispatches_a_test_mail
```
Expected: FAIL — `Class "App\Mail\TestMail" not found` (or unknown action `sendTest`).

- [ ] **Step 3: Write the mailable**

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Inventorix test email',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.test',
        );
    }
}
```

- [ ] **Step 4: Write the email view**

Create `resources/views/mail/test.blade.php`:

```blade
<x-mail::message>
# Test email

If you are reading this, your Inventorix mail settings are working.

This message was sent from the **Mail settings** page.
</x-mail::message>
```

- [ ] **Step 5: Add the header action to `ManageMailSettings`**

Add these imports to `app/Filament/App/Clusters/Settings/Pages/ManageMailSettings.php`:

```php
use App\Mail\TestMail;
use App\Support\ApplySettings;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput as ActionTextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Throwable;
```

Add this method to the class body:

```php
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send test email')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->schema([
                    ActionTextInput::make('email')
                        ->label('Send to')
                        ->email()
                        ->required()
                        ->default(fn (): ?string => Auth::user()?->email),
                ])
                ->action(function (array $data): void {
                    // Persist current form state, then apply it so the test uses what is on screen.
                    $this->save();
                    app(ApplySettings::class)();

                    try {
                        Mail::to($data['email'])->send(new TestMail);

                        Notification::make()
                            ->title('Test email sent')
                            ->body('Sent to '.$data['email'].'.')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Test email failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
```

> If the installed v5 `Action` uses `->form([...])` rather than `->schema([...])` for modal fields, use `->form([...])`. Check an existing action in the codebase (e.g. the `new_handover` action referenced in `tests/Feature/Filament/HandoverWizardTest.php`) for the signature this project uses.

- [ ] **Step 6: Run the test to verify it passes**

Run:
```bash
ddev artisan test --filter=ManageMailSettingsTest
```
Expected: PASS (3 tests total in the file).

- [ ] **Step 7: Commit**

```bash
git add app/Mail/TestMail.php resources/views/mail/test.blade.php app/Filament/App/Clusters/Settings/Pages/ManageMailSettings.php tests/Feature/Filament/ManageMailSettingsTest.php
git commit -m "feat: add send-test-email action to Mail settings page"
```

---

## Task 12: Full suite + Pint, final verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run:
```bash
ddev artisan test
```
Expected: all tests pass, including the new `ApplySettingsTest`, `ManageGeneralSettingsTest`, and `ManageMailSettingsTest`.

- [ ] **Step 2: Run Pint (the project uses `laravel/pint`)**

Run:
```bash
ddev exec ./vendor/bin/pint
```
Expected: files formatted; no errors.

- [ ] **Step 3: Manual smoke test in the panel**

Log into `/app`, open **Settings → Mail**, change the driver, save, and use **Send test email**. Then open **Settings → General**, change the application name, save, and confirm the brand name updates on the next page load.

- [ ] **Step 4: Commit any Pint formatting changes**

```bash
git add -A
git commit -m "chore: pint cleanup" || echo "nothing to format"
```

---

## Self-review notes (covered against spec)

- **Storage (spatie):** Tasks 2–4. **Driver picker (full):** Task 10 select + Task 1 transport deps. **Env seeds defaults / DB overrides:** Task 3 & 4 migrations seed from env; `ApplySettings` overrides config. **Encrypted secrets + masked + blank-keeps:** Task 2 `encrypted()`, Task 10 `mutateFormDataBeforeFill` + `dehydrated(filled())` (verified by a dedicated test). **Runtime application per request + per job (Octane/Horizon):** Tasks 5–7, with `->refresh()` for freshness. **Cluster + sub-navigation + General example:** Tasks 8–9. **Test email surfacing transport errors:** Task 11. **No permission gating:** intentionally omitted per spec.
- **Postal field naming:** uses `postal_domain`/`postal_key` to match `config('postal.domain'|'key')`, which is how `synergitech/laravel-postal` reads credentials (corrected from the spec's `postal_host`).
- **Filament v5 signature caveats:** Tasks 9–11 note where `Schema` vs `Form` and `->schema()` vs `->form()` may differ in the installed plugin version, with explicit instructions to match the installed base class.

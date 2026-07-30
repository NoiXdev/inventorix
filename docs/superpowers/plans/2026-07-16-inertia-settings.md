# Inertia Migration — Settings (Spec 7) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the 5 Filament settings pages (General/Mail/Storage/Warranty/Auth) to a single tabbed `/app/settings` page — with validation, secret-safe handling, immediate runtime application, and the mail/storage/warranty test actions.

**Architecture:** A new `SettingsController` reads all 5 spatie settings groups (secrets stripped) into one Inertia page of shadcn tabs; per-group `PUT` endpoints validate + persist (secrets only overwritten when non-blank) + `app(ApplySettings::class)()`; three `POST` test endpoints reuse the same persist helpers then exercise mail/storage/warranty. The settings classes, runtime-application plumbing, and lang file are reused verbatim.

**Tech Stack:** Laravel 13/PHP 8.4, spatie/laravel-settings, Inertia v2 + React 19 + TS, shadcn/ui, PHPUnit, Vitest. ddev; pnpm.

## Global Constraints

- ddev for all commands; **PHP tests are PHPUnit** (`./vendor/bin/phpunit`); JS tests are **Vitest** (`pnpm exec vitest run`); if `pnpm` missing run `ddev exec corepack enable`.
- **Reuse verbatim, do NOT modify:** `app/Settings/*.php` (the 5 classes), `app/Support/ApplySettings.php`, `app/Http/Middleware/ApplyRuntimeSettings.php`, `app/Console/Commands/ScanWarrantyExpiries.php`, `app/Services/WarrantyScanner.php`, `app/Mail/TestMail.php`, `app/Mail/WarrantyExpiryDigest.php`, `lang/de/settings.php`, `database/settings/*`, and everything under `app/Filament/` (`/app-old`). No DB/schema changes.
- **Secrets never sent to the browser**; a **blank submit keeps** the stored secret (only a non-blank value overwrites). Encrypted fields: mail `smtp_password, ses_secret, postmark_token, resend_key, postal_key`; storage `secret`; auth `microsoft_client_secret`.
- German content stays in `lang/de/settings.php`, passed to React as one `t` prop (`trans('settings')`).
- Route names `app.settings.*`, authenticated `/app` group. Auth-only gate (no policy).
- Pint is a CI gate — plain `<?php` + blank + `namespace` headers; run `./vendor/bin/pint` on new files before committing. `@/` → `resources/js/*`. Commit trailer:
  `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`

## Field reference (from the settings classes)

- **General**: `app_name` (string).
- **Mail**: `default_mailer` (smtp|postal|ses|postmark|resend|sendmail|log), `from_address`, `from_name`; SMTP `smtp_host, smtp_port(int), smtp_scheme(smtp|smtps|null), smtp_username, smtp_password*`; SES `ses_key, ses_secret*, ses_region`; Postmark `postmark_token*, postmark_message_stream_id`; Resend `resend_key*`; Postal `postal_domain(url), postal_key*`. (`*` = secret.)
- **Storage**: `key, secret*, region, bucket, endpoint(url), use_path_style_endpoint(bool), url(url)`.
- **Warranty**: `enabled(bool), recipients(string[] emails), lead_days(int[])`.
- **Auth**: `multi_factor_enabled/force/recoverable(bool)`; `microsoft_enabled(bool), microsoft_client_id, microsoft_client_secret*, microsoft_redirect(url), microsoft_tenant`.

---

### Task 1: Settings backend — index + General/Mail/Storage saves

**Files:**
- Create: `app/Http/Controllers/App/SettingsController.php`, `app/Http/Requests/App/GeneralSettingsRequest.php`, `app/Http/Requests/App/MailSettingsRequest.php`, `app/Http/Requests/App/StorageSettingsRequest.php`, `resources/js/pages/settings/index.tsx` (placeholder)
- Modify: `routes/web.php`
- Test: `tests/Feature/App/SettingsControllerTest.php`

**Interfaces:** Produces routes `app.settings.index` + `app.settings.general|mail|storage.update`; the private `persistGeneral/persistMail/persistStorage` helpers (reused by Task 2's test endpoints); the `settings/index` prop contract (Task 3 consumes).

- [ ] **Step 1: Write the FormRequests**

```php
<?php // app/Http/Requests/App/GeneralSettingsRequest.php
namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class GeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['app_name' => ['required', 'string', 'max:255']];
    }
}
```

```php
<?php // app/Http/Requests/App/MailSettingsRequest.php
namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'default_mailer' => ['required', Rule::in(['smtp', 'postal', 'ses', 'postmark', 'resend', 'sendmail', 'log'])],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_scheme' => ['nullable', Rule::in(['smtp', 'smtps'])],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string'],
            'ses_key' => ['nullable', 'string', 'max:255'],
            'ses_secret' => ['nullable', 'string'],
            'ses_region' => ['nullable', 'string', 'max:255'],
            'postmark_token' => ['nullable', 'string'],
            'postmark_message_stream_id' => ['nullable', 'string', 'max:255'],
            'resend_key' => ['nullable', 'string'],
            'postal_domain' => ['nullable', 'url', 'max:255'],
            'postal_key' => ['nullable', 'string'],
        ];
    }
}
```

```php
<?php // app/Http/Requests/App/StorageSettingsRequest.php
namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class StorageSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'key' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string'],
            'region' => ['nullable', 'string', 'max:255'],
            'bucket' => ['nullable', 'string', 'max:255'],
            'endpoint' => ['nullable', 'url', 'max:255'],
            'use_path_style_endpoint' => ['required', 'boolean'],
            'url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
```

- [ ] **Step 2: Write `SettingsController` (index + General/Mail/Storage)**

```php
<?php // app/Http/Controllers/App/SettingsController.php
namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\GeneralSettingsRequest;
use App\Http\Requests\App\MailSettingsRequest;
use App\Http\Requests\App\StorageSettingsRequest;
use App\Settings\AuthSettings;
use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use App\Settings\StorageSettings;
use App\Settings\WarrantySettings;
use App\Support\ApplySettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /** Encrypted/secret fields per group — never sent to the browser; only overwritten when non-blank. */
    private const SECRETS = [
        'mail' => ['smtp_password', 'ses_secret', 'postmark_token', 'resend_key', 'postal_key'],
        'storage' => ['secret'],
        'auth' => ['microsoft_client_secret'],
    ];

    public function index(GeneralSettings $general, MailSettings $mail, StorageSettings $storage, WarrantySettings $warranty, AuthSettings $auth): Response
    {
        return Inertia::render('settings/index', [
            't' => trans('settings'),
            'options' => [
                'mailDrivers' => $this->mapOptions([
                    'smtp', 'postal', 'ses', 'postmark', 'resend', 'sendmail', 'log',
                ], 'settings.mail.driver'),
                'mailSchemes' => [
                    ['value' => 'smtp', 'label' => trans('settings.mail.scheme.starttls')],
                    ['value' => 'smtps', 'label' => trans('settings.mail.scheme.ssl')],
                ],
            ],
            'settings' => [
                'general' => ['app_name' => $general->app_name],
                'mail' => $this->withoutSecrets([
                    'default_mailer' => $mail->default_mailer,
                    'from_address' => $mail->from_address,
                    'from_name' => $mail->from_name,
                    'smtp_host' => $mail->smtp_host,
                    'smtp_port' => $mail->smtp_port,
                    'smtp_scheme' => $mail->smtp_scheme,
                    'smtp_username' => $mail->smtp_username,
                    'ses_key' => $mail->ses_key,
                    'ses_region' => $mail->ses_region,
                    'postmark_message_stream_id' => $mail->postmark_message_stream_id,
                    'postal_domain' => $mail->postal_domain,
                ], 'mail'),
                'storage' => $this->withoutSecrets([
                    'key' => $storage->key,
                    'region' => $storage->region,
                    'bucket' => $storage->bucket,
                    'endpoint' => $storage->endpoint,
                    'use_path_style_endpoint' => $storage->use_path_style_endpoint,
                    'url' => $storage->url,
                ], 'storage'),
                'warranty' => [
                    'enabled' => $warranty->enabled,
                    'recipients' => $warranty->recipients,
                    'lead_days' => array_map('strval', $warranty->lead_days),
                ],
                'auth' => $this->withoutSecrets([
                    'multi_factor_enabled' => $auth->multi_factor_enabled,
                    'multi_factor_force' => $auth->multi_factor_force,
                    'multi_factor_recoverable' => $auth->multi_factor_recoverable,
                    'microsoft_enabled' => $auth->microsoft_enabled,
                    'microsoft_client_id' => $auth->microsoft_client_id,
                    'microsoft_redirect' => $auth->microsoft_redirect,
                    'microsoft_tenant' => $auth->microsoft_tenant,
                ], 'auth'),
            ],
        ]);
    }

    public function updateGeneral(GeneralSettingsRequest $request): RedirectResponse
    {
        $this->persistGeneral($request->validated());

        return back()->with('success', trans('settings.general.title'));
    }

    public function updateMail(MailSettingsRequest $request): RedirectResponse
    {
        $this->persistMail($request->validated());

        return back()->with('success', trans('settings.mail.title'));
    }

    public function updateStorage(StorageSettingsRequest $request): RedirectResponse
    {
        $this->persistStorage($request->validated());

        return back()->with('success', trans('settings.storage.title'));
    }

    // ---- persist helpers (reused by test endpoints in Task 2) ----

    /** @param array<string, mixed> $data */
    private function persistGeneral(array $data): void
    {
        $general = app(GeneralSettings::class);
        $general->app_name = $data['app_name'];
        $general->save();
        app(ApplySettings::class)();
    }

    /** @param array<string, mixed> $data */
    private function persistMail(array $data): void
    {
        $mail = app(MailSettings::class);
        foreach (['default_mailer', 'from_address', 'from_name', 'smtp_host', 'smtp_port', 'smtp_scheme', 'smtp_username', 'ses_key', 'ses_region', 'postmark_message_stream_id', 'postal_domain'] as $field) {
            $mail->{$field} = $data[$field] ?? null;
        }
        $this->assignSecrets($mail, $data, self::SECRETS['mail']);
        $mail->save();
        app(ApplySettings::class)();
    }

    /** @param array<string, mixed> $data */
    private function persistStorage(array $data): void
    {
        $storage = app(StorageSettings::class);
        foreach (['key', 'region', 'bucket', 'endpoint', 'url'] as $field) {
            $storage->{$field} = $data[$field] ?? null;
        }
        $storage->use_path_style_endpoint = (bool) ($data['use_path_style_endpoint'] ?? false);
        $this->assignSecrets($storage, $data, self::SECRETS['storage']);
        $storage->save();
        app(ApplySettings::class)();
    }

    /**
     * Assign secret fields only when a non-blank value was submitted (blank keeps the stored secret).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $fields
     */
    private function assignSecrets(object $settings, array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (filled($data[$field] ?? null)) {
                $settings->{$field} = $data[$field];
            }
        }
    }

    /**
     * Strip secret fields from an outgoing settings array (defence in depth — they aren't included above).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withoutSecrets(array $values, string $group): array
    {
        foreach (self::SECRETS[$group] ?? [] as $secret) {
            unset($values[$secret]);
        }

        return $values;
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, array{value: string, label: string}>
     */
    private function mapOptions(array $values, string $prefix): array
    {
        return array_map(
            static fn (string $v): array => ['value' => $v, 'label' => trans("{$prefix}.{$v}")],
            $values,
        );
    }
}
```

- [ ] **Step 3: Register routes** — in `routes/web.php`, inside the authenticated `/app` group, import `SettingsController` and add:

```php
Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
Route::put('settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general.update');
Route::put('settings/mail', [SettingsController::class, 'updateMail'])->name('settings.mail.update');
Route::put('settings/storage', [SettingsController::class, 'updateStorage'])->name('settings.storage.update');
```

- [ ] **Step 4: Placeholder page** — create `resources/js/pages/settings/index.tsx` (minimal, so Inertia's `assertInertia()->component('settings/index')` passes; Task 3 replaces the body):

```tsx
import AppLayout from '@/layouts/app-layout';

export default function Settings() {
    return (
        <AppLayout title="Settings" breadcrumbs={[{ label: 'Settings' }]}>
            <h1 className="text-2xl font-semibold">Settings</h1>
        </AppLayout>
    );
}
```

- [ ] **Step 5: Write the tests**

```php
<?php // tests/Feature/App/SettingsControllerTest.php
namespace Tests\Feature\App;

use App\Settings\MailSettings;
use App\Settings\StorageSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_index_never_exposes_secrets(): void
    {
        $mail = app(MailSettings::class);
        $mail->smtp_password = 'super-secret';
        $mail->save();

        $this->actingAs($this->actor())->get('/app/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('settings/index')
                ->has('settings.mail')
                ->where('settings.mail.smtp_password', null) // absent -> null
                ->has('t')
                ->has('options.mailDrivers')
                ->etc());
    }

    public function test_update_general_persists_and_applies(): void
    {
        $this->actingAs($this->actor())
            ->put('/app/settings/general', ['app_name' => 'Inventorix X'])
            ->assertRedirect();

        $this->assertSame('Inventorix X', app(\App\Settings\GeneralSettings::class)->refresh()->app_name);
        $this->assertSame('Inventorix X', config('app.name'));
    }

    public function test_update_mail_keeps_secret_when_blank_and_overwrites_when_present(): void
    {
        $mail = app(MailSettings::class);
        $mail->smtp_password = 'original';
        $mail->save();

        $base = [
            'default_mailer' => 'smtp', 'from_address' => 'a@b.de', 'from_name' => 'Ops',
            'use_path_style_endpoint' => false, // ignored by mail
        ];

        // blank -> keep
        $this->actingAs($this->actor())->put('/app/settings/mail', $base + ['smtp_password' => ''])->assertRedirect();
        $this->assertSame('original', app(MailSettings::class)->refresh()->smtp_password);

        // non-blank -> overwrite
        $this->actingAs($this->actor())->put('/app/settings/mail', $base + ['smtp_password' => 'changed'])->assertRedirect();
        $this->assertSame('changed', app(MailSettings::class)->refresh()->smtp_password);
    }

    public function test_update_mail_validation(): void
    {
        $this->actingAs($this->actor())
            ->put('/app/settings/mail', ['default_mailer' => 'nope', 'from_address' => 'not-email', 'from_name' => ''])
            ->assertSessionHasErrors(['default_mailer', 'from_address', 'from_name']);
    }

    public function test_update_storage_persists(): void
    {
        $this->actingAs($this->actor())->put('/app/settings/storage', [
            'key' => 'AKIA', 'region' => 'eu', 'bucket' => 'files', 'use_path_style_endpoint' => true,
        ])->assertRedirect();

        $this->assertSame('files', app(StorageSettings::class)->refresh()->bucket);
        $this->assertSame('files', config('filesystems.disks.s3.bucket'));
    }

    public function test_requires_auth(): void
    {
        $this->get('/app/settings')->assertRedirect();
    }
}
```

- [ ] **Step 6: Run + commit**

`ddev exec ./vendor/bin/phpunit tests/Feature/App/SettingsControllerTest.php` → PASS. Pint the new PHP files. Full suite green.

```bash
git add app/Http/Controllers/App/SettingsController.php app/Http/Requests/App/*SettingsRequest.php routes/web.php resources/js/pages/settings/index.tsx tests/Feature/App/SettingsControllerTest.php
git commit -m "feat(settings): settings backend index + general/mail/storage saves"
```

---

### Task 2: Auth/Warranty saves + the 3 test actions + warning flash

**Files:**
- Create: `app/Http/Requests/App/AuthSettingsRequest.php`, `app/Http/Requests/App/WarrantySettingsRequest.php`, `app/Http/Requests/App/MailTestRequest.php`
- Modify: `app/Http/Controllers/App/SettingsController.php` (add methods), `routes/web.php`, `app/Http/Middleware/HandleInertiaRequests.php` (add `warning` flash), `resources/js/layouts/app-layout.tsx` (toast warning), `resources/js/types/index.d.ts` (flash.warning), `tests/Feature/App/SettingsControllerTest.php` (add tests) — or a new test file `tests/Feature/App/SettingsTestActionsTest.php`
- Test: `tests/Feature/App/SettingsTestActionsTest.php`

**Interfaces:** Consumes Task 1's `persist*` helpers. Produces routes `app.settings.auth|warranty.update` + `app.settings.mail.test|storage.test|warranty.test`.

- [ ] **Step 1: FormRequests**

```php
<?php // app/Http/Requests/App/AuthSettingsRequest.php
namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class AuthSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'multi_factor_enabled' => ['required', 'boolean'],
            'multi_factor_force' => ['required', 'boolean'],
            'multi_factor_recoverable' => ['required', 'boolean'],
            'microsoft_enabled' => ['required', 'boolean'],
            'microsoft_client_id' => ['nullable', 'required_if:microsoft_enabled,true', 'string', 'max:255'],
            'microsoft_client_secret' => ['nullable', 'string'],
            'microsoft_redirect' => ['nullable', 'required_if:microsoft_enabled,true', 'url', 'max:255'],
            'microsoft_tenant' => ['nullable', 'required_if:microsoft_enabled,true', 'string', 'max:255'],
        ];
    }
}
```

```php
<?php // app/Http/Requests/App/WarrantySettingsRequest.php
namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class WarrantySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'recipients' => ['array'],
            'recipients.*' => ['email'],
            'lead_days' => ['array'],
            'lead_days.*' => ['integer', 'min:0'],
        ];
    }
}
```

```php
<?php // app/Http/Requests/App/MailTestRequest.php
namespace App\Http\Requests\App;

class MailTestRequest extends MailSettingsRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules() + ['email' => ['required', 'email']];
    }
}
```

- [ ] **Step 2: Add controller methods** (append to `SettingsController`; add the needed `use` imports: `App\Http\Requests\App\{AuthSettingsRequest,WarrantySettingsRequest,MailTestRequest}`, `App\Mail\TestMail`, `App\Mail\WarrantyExpiryDigest`, `App\Services\WarrantyScanner`, `Illuminate\Support\Facades\{Mail,Storage}`, `Illuminate\Support\Str`, `Throwable`):

```php
    public function updateAuth(AuthSettingsRequest $request): RedirectResponse
    {
        $this->persistAuth($request->validated());

        return back()->with('success', trans('settings.auth.title'));
    }

    public function updateWarranty(WarrantySettingsRequest $request): RedirectResponse
    {
        $this->persistWarranty($request->validated());

        return back()->with('success', trans('settings.warranty.title'));
    }

    public function testMail(MailTestRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->persistMail($data);

        try {
            Mail::to($data['email'])->send(new TestMail);

            return back()->with('success', trans('settings.mail.test.success_body', ['email' => $data['email']]));
        } catch (Throwable $e) {
            return back()->with('error', trans('settings.mail.test.failure_title').': '.$e->getMessage());
        }
    }

    public function testStorage(StorageSettingsRequest $request): RedirectResponse
    {
        $this->persistStorage($request->validated());

        try {
            $disk = Storage::disk('s3');
            $probe = 'inventorix-connection-test-'.Str::random(16).'.txt';
            $written = $disk->put($probe, 'ok');
            $contents = $written ? $disk->get($probe) : null;
            $disk->delete($probe);

            if ($written === false || $contents !== 'ok') {
                throw new \RuntimeException(trans('settings.storage.test.probe_failed'));
            }

            return back()->with('success', trans('settings.storage.test.success_body'));
        } catch (Throwable $e) {
            return back()->with('error', trans('settings.storage.test.failure_title').': '.$e->getMessage());
        }
    }

    public function testWarranty(WarrantySettingsRequest $request): RedirectResponse
    {
        $this->persistWarranty($request->validated());
        $settings = app(WarrantySettings::class)->refresh();
        $results = (new WarrantyScanner($settings->lead_days))->scan();

        if ($results->isEmpty()) {
            return back()->with('warning', trans('settings.warranty.test.empty_body'));
        }

        try {
            Mail::to($settings->recipients)->send(new WarrantyExpiryDigest($results));

            return back()->with('success', trans('settings.warranty.test.success_body', ['count' => count($settings->recipients)]));
        } catch (Throwable $e) {
            return back()->with('error', trans('settings.warranty.test.failure_title').': '.$e->getMessage());
        }
    }

    /** @param array<string, mixed> $data */
    private function persistAuth(array $data): void
    {
        $auth = app(AuthSettings::class);
        foreach (['multi_factor_enabled', 'multi_factor_force', 'multi_factor_recoverable', 'microsoft_enabled'] as $flag) {
            $auth->{$flag} = (bool) ($data[$flag] ?? false);
        }
        foreach (['microsoft_client_id', 'microsoft_redirect', 'microsoft_tenant'] as $field) {
            $auth->{$field} = $data[$field] ?? null;
        }
        $this->assignSecrets($auth, $data, self::SECRETS['auth']);
        $auth->save();
        app(ApplySettings::class)();
    }

    /** @param array<string, mixed> $data */
    private function persistWarranty(array $data): void
    {
        $warranty = app(WarrantySettings::class);
        $warranty->enabled = (bool) ($data['enabled'] ?? false);
        $warranty->recipients = array_values($data['recipients'] ?? []);
        $warranty->lead_days = collect($data['lead_days'] ?? [])
            ->map(fn ($d): int => (int) $d)
            ->filter(fn (int $d): bool => $d >= 0)
            ->unique()->sortDesc()->values()->all();
        $warranty->save();
        app(ApplySettings::class)();
    }
```

- [ ] **Step 3: Routes** — add to the `/app` group:

```php
Route::put('settings/auth', [SettingsController::class, 'updateAuth'])->name('settings.auth.update');
Route::put('settings/warranty', [SettingsController::class, 'updateWarranty'])->name('settings.warranty.update');
Route::post('settings/mail/test', [SettingsController::class, 'testMail'])->name('settings.mail.test');
Route::post('settings/storage/test', [SettingsController::class, 'testStorage'])->name('settings.storage.test');
Route::post('settings/warranty/test', [SettingsController::class, 'testWarranty'])->name('settings.warranty.test');
```

- [ ] **Step 4: Add `warning` to the flash pipeline**

In `app/Http/Middleware/HandleInertiaRequests.php`, inside the `'flash' => [...]` array, add:
```php
'warning' => fn () => $request->session()->get('warning'),
```
In `resources/js/types/index.d.ts`, change the flash type to:
```ts
flash: { success: string | null; error: string | null; warning: string | null };
```
In `resources/js/layouts/app-layout.tsx`, in the flash effect, add `if (flash?.warning) toast.warning(flash.warning);` and add `flash?.warning` to the effect dependency array.

- [ ] **Step 5: Tests**

```php
<?php // tests/Feature/App/SettingsTestActionsTest.php
namespace Tests\Feature\App;

use App\Mail\TestMail;
use App\Mail\WarrantyExpiryDigest;
use App\Models\Asset;
use App\Models\User;
use App\Settings\AuthSettings;
use App\Settings\WarrantySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTestActionsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    private function mailPayload(array $overrides = []): array
    {
        return array_merge([
            'default_mailer' => 'log', 'from_address' => 'a@b.de', 'from_name' => 'Ops',
        ], $overrides);
    }

    public function test_update_auth_required_if_microsoft_enabled(): void
    {
        $this->actingAs($this->actor())->put('/app/settings/auth', [
            'multi_factor_enabled' => false, 'multi_factor_force' => false, 'multi_factor_recoverable' => false,
            'microsoft_enabled' => true,
        ])->assertSessionHasErrors(['microsoft_client_id', 'microsoft_redirect', 'microsoft_tenant']);
    }

    public function test_update_warranty_normalizes_lead_days(): void
    {
        $this->actingAs($this->actor())->put('/app/settings/warranty', [
            'enabled' => true, 'recipients' => ['ops@x.de'], 'lead_days' => ['7', '30', '7', '90'],
        ])->assertRedirect();

        $this->assertSame([90, 30, 7], app(WarrantySettings::class)->refresh()->lead_days);
    }

    public function test_mail_test_sends(): void
    {
        Mail::fake();
        $this->actingAs($this->actor())
            ->post('/app/settings/mail/test', $this->mailPayload(['email' => 'to@x.de']))
            ->assertRedirect();
        Mail::assertSent(TestMail::class);
    }

    public function test_storage_test_uses_probe(): void
    {
        Storage::fake('s3');
        $this->actingAs($this->actor())->post('/app/settings/storage/test', [
            'key' => 'k', 'secret' => 's', 'bucket' => 'b', 'use_path_style_endpoint' => false,
        ])->assertRedirect()->assertSessionHas('success');
    }

    public function test_warranty_test_warns_when_nothing_due(): void
    {
        $this->actingAs($this->actor())->post('/app/settings/warranty/test', [
            'enabled' => true, 'recipients' => ['ops@x.de'], 'lead_days' => [30],
        ])->assertRedirect()->assertSessionHas('warning');
    }

    public function test_warranty_test_sends_digest_when_due(): void
    {
        Mail::fake();
        // an asset whose guarantee ends in ~30 days -> due for the 30-day lead
        Asset::factory()->create(['guarantee_end' => now()->addDays(30)->format('Y-m-d')]);

        $this->actingAs($this->actor())->post('/app/settings/warranty/test', [
            'enabled' => true, 'recipients' => ['ops@x.de'], 'lead_days' => [30],
        ])->assertRedirect();
        Mail::assertSent(WarrantyExpiryDigest::class);
    }
}
```

Note: if `test_warranty_test_sends_digest_when_due` doesn't trigger (the scanner's due-window logic may key on exact lead-day offsets), read `app/Services/WarrantyScanner.php::scan()` and set `guarantee_end` to the exact date the scanner treats as due for a 30-day lead; keep the assertion (digest sent) intact.

- [ ] **Step 6: Run + commit**

`ddev exec ./vendor/bin/phpunit tests/Feature/App/SettingsTestActionsTest.php tests/Feature/App/SettingsControllerTest.php` → PASS. Pint. Full PHP suite + `ddev exec pnpm run test` (the app-layout/type change must keep JS green) + `ddev exec pnpm run build`.

```bash
git add app/Http/Requests/App/AuthSettingsRequest.php app/Http/Requests/App/WarrantySettingsRequest.php app/Http/Requests/App/MailTestRequest.php app/Http/Controllers/App/SettingsController.php routes/web.php app/Http/Middleware/HandleInertiaRequests.php resources/js/layouts/app-layout.tsx resources/js/types/index.d.ts tests/Feature/App/SettingsTestActionsTest.php
git commit -m "feat(settings): auth/warranty saves + mail/storage/warranty test actions"
```

---

### Task 3: Frontend — settings shell + General & Mail tabs + nav

**Files:**
- Modify: `resources/js/pages/settings/index.tsx`, `resources/js/config/nav.ts`
- Create: `resources/js/pages/settings/tabs/general-tab.tsx`, `resources/js/pages/settings/tabs/mail-tab.tsx`
- Test: `resources/js/pages/settings/__tests__/index.test.tsx`

**Interfaces:** Consumes the `settings/index` props from Task 1. Produces the tab-component pattern Task 4 follows.

- [ ] **Step 1: Write the failing test**

```tsx
// resources/js/pages/settings/__tests__/index.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const put = vi.fn();
const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
    useForm: (initial: Record<string, unknown>) => {
        let data = { ...initial };
        return {
            data,
            setData: (k: string, v: unknown) => { data[k] = v; },
            put: (...a: unknown[]) => put(...a),
            post: (...a: unknown[]) => post(...a),
            processing: false,
            errors: {},
        };
    },
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import Settings from '../index';

const t = {
    general: { title: 'Allgemein', field: { app_name: 'Name' } },
    mail: {
        title: 'E-Mail', section: { from: 'Absender', smtp: 'SMTP' },
        field: { from_address: 'Von', from_name: 'Name', driver: 'Treiber', smtp_host: 'Host', smtp_port: 'Port', smtp_username: 'User', smtp_password: 'Pass' },
        test: { action: 'Test', recipient: 'An' },
    },
    storage: { title: 'Speicher', section: { s3: 'S3' }, field: {}, test: {} },
    warranty: { title: 'Garantie', field: {}, test: {} },
    auth: { title: 'Auth', multi_factor: { section: 'MFA', field: {} }, microsoft: { section: 'MS', field: {} } },
    nav: { cluster: 'Einstellungen' },
};
const props = {
    t,
    options: { mailDrivers: [{ value: 'smtp', label: 'SMTP' }, { value: 'log', label: 'Log' }], mailSchemes: [] },
    settings: {
        general: { app_name: 'Inventorix' },
        mail: { default_mailer: 'smtp', from_address: 'a@b.de', from_name: 'Ops', smtp_host: 'h', smtp_port: 25, smtp_scheme: null, smtp_username: 'u', ses_key: null, ses_region: null, postmark_message_stream_id: null, postal_domain: null },
        storage: { key: null, region: null, bucket: null, endpoint: null, use_path_style_endpoint: false, url: null },
        warranty: { enabled: false, recipients: [], lead_days: [] },
        auth: { multi_factor_enabled: false, multi_factor_force: false, multi_factor_recoverable: false, microsoft_enabled: false, microsoft_client_id: null, microsoft_redirect: null, microsoft_tenant: null },
    },
};

describe('Settings', () => {
    it('renders the General tab with the app name', () => {
        render(<Settings {...(props as never)} />);
        expect(screen.getByDisplayValue('Inventorix')).toBeInTheDocument();
    });
});
```

- [ ] **Step 2: `general-tab.tsx`**

```tsx
// resources/js/pages/settings/tabs/general-tab.tsx
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';

interface Props { t: any; values: { app_name: string } }

export function GeneralTab({ t, values }: Props) {
    const form = useForm({ app_name: values.app_name ?? '' });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.put('/app/settings/general', { preserveScroll: true }); };

    return (
        <form onSubmit={submit} className="max-w-xl space-y-4">
            <TextField id="app_name" label={t.general.field.app_name} value={form.data.app_name} onChange={(v) => form.setData('app_name', v)} error={form.errors.app_name} required />
            <Button type="submit" disabled={form.processing}>Save</Button>
        </form>
    );
}
```

- [ ] **Step 3: `mail-tab.tsx`** (driver-conditional sections + secret fields + test dialog)

```tsx
// resources/js/pages/settings/tabs/mail-tab.tsx
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { TextField } from '@/components/form/text-field';
import { SelectField } from '@/components/form/select-field';
import { NumberField } from '@/components/form/number-field';
import { PasswordField } from '@/components/form/password-field';

interface Opt { value: string; label: string }
interface Props {
    t: any;
    values: Record<string, string | number | null>;
    drivers: Opt[];
    schemes: Opt[];
}

const KeepHint = ({ show }: { show: boolean }) =>
    show ? <p className="-mt-2 text-xs text-muted-foreground">Leer lassen, um den gespeicherten Wert zu behalten.</p> : null;

export function MailTab({ t, values, drivers, schemes }: Props) {
    const form = useForm({
        default_mailer: (values.default_mailer as string) ?? 'smtp',
        from_address: (values.from_address as string) ?? '',
        from_name: (values.from_name as string) ?? '',
        smtp_host: (values.smtp_host as string) ?? '',
        smtp_port: values.smtp_port != null ? String(values.smtp_port) : '',
        smtp_scheme: (values.smtp_scheme as string) ?? '',
        smtp_username: (values.smtp_username as string) ?? '',
        smtp_password: '',
        ses_key: (values.ses_key as string) ?? '',
        ses_secret: '',
        ses_region: (values.ses_region as string) ?? '',
        postmark_token: '',
        postmark_message_stream_id: (values.postmark_message_stream_id as string) ?? '',
        resend_key: '',
        postal_domain: (values.postal_domain as string) ?? '',
        postal_key: '',
    });
    const [testEmail, setTestEmail] = useState('');
    const [open, setOpen] = useState(false);

    const set = (k: string) => (v: string) => form.setData(k as never, v as never);
    const driver = form.data.default_mailer;
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.put('/app/settings/mail', { preserveScroll: true }); };
    const sendTest = () => form.transform((d) => ({ ...d, email: testEmail })).post('/app/settings/mail/test', {
        preserveScroll: true,
        onFinish: () => { form.transform((d) => d); setOpen(false); },
    });

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField id="from_address" label={t.mail.field.from_address} value={form.data.from_address} onChange={set('from_address')} error={form.errors.from_address} required />
                <TextField id="from_name" label={t.mail.field.from_name} value={form.data.from_name} onChange={set('from_name')} error={form.errors.from_name} required />
            </div>
            <SelectField id="default_mailer" label={t.mail.field.driver} value={driver} onChange={set('default_mailer')} options={drivers} error={form.errors.default_mailer} />

            {driver === 'smtp' && (
                <fieldset className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                    <legend className="px-1 text-sm font-medium">{t.mail.section.smtp}</legend>
                    <TextField id="smtp_host" label={t.mail.field.smtp_host} value={form.data.smtp_host} onChange={set('smtp_host')} error={form.errors.smtp_host} />
                    <NumberField id="smtp_port" label={t.mail.field.smtp_port} step="1" value={form.data.smtp_port} onChange={set('smtp_port')} error={form.errors.smtp_port} />
                    <SelectField id="smtp_scheme" label={t.mail.field.smtp_scheme ?? 'Scheme'} value={form.data.smtp_scheme} onChange={set('smtp_scheme')} options={schemes} nullable />
                    <TextField id="smtp_username" label={t.mail.field.smtp_username} value={form.data.smtp_username} onChange={set('smtp_username')} error={form.errors.smtp_username} />
                    <div className="sm:col-span-2">
                        <PasswordField id="smtp_password" label={t.mail.field.smtp_password} value={form.data.smtp_password} onChange={set('smtp_password')} error={form.errors.smtp_password} />
                        <KeepHint show />
                    </div>
                </fieldset>
            )}
            {driver === 'ses' && (
                <fieldset className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                    <legend className="px-1 text-sm font-medium">{t.mail.section.ses}</legend>
                    <TextField id="ses_key" label={t.mail.field.ses_key} value={form.data.ses_key} onChange={set('ses_key')} error={form.errors.ses_key} />
                    <div><PasswordField id="ses_secret" label={t.mail.field.ses_secret} value={form.data.ses_secret} onChange={set('ses_secret')} error={form.errors.ses_secret} /><KeepHint show /></div>
                    <TextField id="ses_region" label={t.mail.field.ses_region} value={form.data.ses_region} onChange={set('ses_region')} error={form.errors.ses_region} />
                </fieldset>
            )}
            {driver === 'postmark' && (
                <fieldset className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                    <legend className="px-1 text-sm font-medium">{t.mail.section.postmark}</legend>
                    <div><PasswordField id="postmark_token" label={t.mail.field.postmark_token} value={form.data.postmark_token} onChange={set('postmark_token')} error={form.errors.postmark_token} /><KeepHint show /></div>
                    <TextField id="postmark_message_stream_id" label={t.mail.field.postmark_message_stream_id} value={form.data.postmark_message_stream_id} onChange={set('postmark_message_stream_id')} error={form.errors.postmark_message_stream_id} />
                </fieldset>
            )}
            {driver === 'resend' && (
                <fieldset className="rounded-md border p-4">
                    <legend className="px-1 text-sm font-medium">{t.mail.section.resend}</legend>
                    <div><PasswordField id="resend_key" label={t.mail.field.resend_key} value={form.data.resend_key} onChange={set('resend_key')} error={form.errors.resend_key} /><KeepHint show /></div>
                </fieldset>
            )}
            {driver === 'postal' && (
                <fieldset className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                    <legend className="px-1 text-sm font-medium">{t.mail.section.postal}</legend>
                    <TextField id="postal_domain" label={t.mail.field.postal_domain} value={form.data.postal_domain} onChange={set('postal_domain')} error={form.errors.postal_domain} />
                    <div><PasswordField id="postal_key" label={t.mail.field.postal_key} value={form.data.postal_key} onChange={set('postal_key')} error={form.errors.postal_key} /><KeepHint show /></div>
                </fieldset>
            )}

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild><Button type="button" variant="outline">{t.mail.test.action}</Button></DialogTrigger>
                    <DialogContent>
                        <DialogHeader><DialogTitle>{t.mail.test.action}</DialogTitle></DialogHeader>
                        <TextField id="test_email" label={t.mail.test.recipient} value={testEmail} onChange={setTestEmail} />
                        <DialogFooter><Button type="button" onClick={sendTest} disabled={form.processing}>{t.mail.test.action}</Button></DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </form>
    );
}
```

(If `form.transform` typing is awkward, an equivalent is `router.post('/app/settings/mail/test', { ...form.data, email: testEmail }, { preserveScroll: true })` importing `router` — either is fine; keep secrets blank-by-default behaviour.)

- [ ] **Step 4: `index.tsx` shell**

```tsx
// resources/js/pages/settings/index.tsx
import AppLayout from '@/layouts/app-layout';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { GeneralTab } from './tabs/general-tab';
import { MailTab } from './tabs/mail-tab';
// Task 4 adds: StorageTab, WarrantyTab, AuthTab

interface Props {
    t: any;
    options: { mailDrivers: { value: string; label: string }[]; mailSchemes: { value: string; label: string }[] };
    settings: {
        general: { app_name: string };
        mail: Record<string, string | number | null>;
        storage: Record<string, string | boolean | null>;
        warranty: { enabled: boolean; recipients: string[]; lead_days: string[] };
        auth: Record<string, string | boolean | null>;
    };
}

export default function Settings({ t, options, settings }: Props) {
    return (
        <AppLayout title="Settings" breadcrumbs={[{ label: 'Settings' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Settings</h1>
            <Tabs defaultValue="general">
                <TabsList>
                    <TabsTrigger value="general">{t.general.title}</TabsTrigger>
                    <TabsTrigger value="mail">{t.mail.title}</TabsTrigger>
                    <TabsTrigger value="storage">{t.storage.title}</TabsTrigger>
                    <TabsTrigger value="warranty">{t.warranty.title}</TabsTrigger>
                    <TabsTrigger value="auth">{t.auth.title}</TabsTrigger>
                </TabsList>
                <TabsContent value="general" className="pt-4"><GeneralTab t={t} values={settings.general} /></TabsContent>
                <TabsContent value="mail" className="pt-4"><MailTab t={t} values={settings.mail} drivers={options.mailDrivers} schemes={options.mailSchemes} /></TabsContent>
                <TabsContent value="storage" className="pt-4">{/* Task 4: <StorageTab .../> */}</TabsContent>
                <TabsContent value="warranty" className="pt-4">{/* Task 4: <WarrantyTab .../> */}</TabsContent>
                <TabsContent value="auth" className="pt-4">{/* Task 4: <AuthTab .../> */}</TabsContent>
            </Tabs>
        </AppLayout>
    );
}
```

- [ ] **Step 5: Nav** — in `resources/js/config/nav.ts`, import `Settings as SettingsIcon` from lucide-react (alias to avoid a name clash with the page), append a group after the last one:

```ts
{
    label: 'Settings',
    items: [
        { label: 'Settings', href: '/app/settings', icon: SettingsIcon, match: (p) => p.startsWith('/app/settings') },
    ],
},
```

- [ ] **Step 6: Run + commit**

`ddev exec pnpm exec vitest run resources/js/pages/settings/__tests__/index.test.tsx` → PASS; full JS suite + `ddev exec pnpm run build` clean.

```bash
git add resources/js/pages/settings resources/js/config/nav.ts
git commit -m "feat(settings): settings page shell + general/mail tabs + nav"
```

---

### Task 4: Frontend — Storage, Warranty, Auth tabs

**Files:**
- Create: `resources/js/pages/settings/tabs/storage-tab.tsx`, `resources/js/pages/settings/tabs/warranty-tab.tsx`, `resources/js/pages/settings/tabs/auth-tab.tsx`
- Modify: `resources/js/pages/settings/index.tsx` (wire the three tabs), `resources/js/pages/settings/__tests__/index.test.tsx` (add assertions)

- [ ] **Step 1: `storage-tab.tsx`**

```tsx
// resources/js/pages/settings/tabs/storage-tab.tsx
import { router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { SwitchField } from '@/components/form/switch-field';
import { PasswordField } from '@/components/form/password-field';

interface Props { t: any; values: Record<string, string | boolean | null> }

export function StorageTab({ t, values }: Props) {
    const form = useForm({
        key: (values.key as string) ?? '',
        secret: '',
        region: (values.region as string) ?? '',
        bucket: (values.bucket as string) ?? '',
        endpoint: (values.endpoint as string) ?? '',
        use_path_style_endpoint: Boolean(values.use_path_style_endpoint),
        url: (values.url as string) ?? '',
    });
    const set = (k: string) => (v: string) => form.setData(k as never, v as never);
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.put('/app/settings/storage', { preserveScroll: true }); };
    const test = () => router.post('/app/settings/storage/test', { ...form.data }, { preserveScroll: true });

    return (
        <form onSubmit={submit} className="grid max-w-2xl gap-4 sm:grid-cols-2">
            <TextField id="key" label={t.storage.field.key} value={form.data.key} onChange={set('key')} error={form.errors.key} />
            <div><PasswordField id="secret" label={t.storage.field.secret} value={form.data.secret} onChange={set('secret')} error={form.errors.secret} /><p className="-mt-2 text-xs text-muted-foreground">Leer lassen, um den gespeicherten Wert zu behalten.</p></div>
            <TextField id="region" label={t.storage.field.region} value={form.data.region} onChange={set('region')} error={form.errors.region} />
            <TextField id="bucket" label={t.storage.field.bucket} value={form.data.bucket} onChange={set('bucket')} error={form.errors.bucket} />
            <TextField id="endpoint" label={t.storage.field.endpoint} value={form.data.endpoint} onChange={set('endpoint')} error={form.errors.endpoint} />
            <TextField id="url" label={t.storage.field.url} value={form.data.url} onChange={set('url')} error={form.errors.url} />
            <div className="sm:col-span-2"><SwitchField id="use_path_style_endpoint" label={t.storage.field.use_path_style_endpoint} checked={form.data.use_path_style_endpoint} onChange={(v) => form.setData('use_path_style_endpoint', v)} /></div>
            <div className="flex gap-2 sm:col-span-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="outline" onClick={test}>{t.storage.test.action}</Button>
            </div>
        </form>
    );
}
```

- [ ] **Step 2: `warranty-tab.tsx`**

```tsx
// resources/js/pages/settings/tabs/warranty-tab.tsx
import { router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { SwitchField } from '@/components/form/switch-field';
import { TagsInput } from '@/components/form/tags-input';

interface Props { t: any; values: { enabled: boolean; recipients: string[]; lead_days: string[] } }

export function WarrantyTab({ t, values }: Props) {
    const form = useForm({
        enabled: Boolean(values.enabled),
        recipients: values.recipients ?? [],
        lead_days: values.lead_days ?? [],
    });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.put('/app/settings/warranty', { preserveScroll: true }); };
    const test = () => router.post('/app/settings/warranty/test', { ...form.data }, { preserveScroll: true });

    return (
        <form onSubmit={submit} className="max-w-xl space-y-4">
            <SwitchField id="enabled" label={t.warranty.field.enabled} checked={form.data.enabled} onChange={(v) => form.setData('enabled', v)} />
            <TagsInput id="recipients" label={t.warranty.field.recipients} value={form.data.recipients} onChange={(v) => form.setData('recipients', v)} error={form.errors.recipients} />
            <TagsInput id="lead_days" label={t.warranty.field.lead_days} value={form.data.lead_days} onChange={(v) => form.setData('lead_days', v)} error={form.errors.lead_days} />
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="outline" onClick={test}>{t.warranty.test.action}</Button>
            </div>
        </form>
    );
}
```

- [ ] **Step 3: `auth-tab.tsx`**

```tsx
// resources/js/pages/settings/tabs/auth-tab.tsx
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { SwitchField } from '@/components/form/switch-field';
import { PasswordField } from '@/components/form/password-field';

interface Props { t: any; values: Record<string, string | boolean | null> }

export function AuthTab({ t, values }: Props) {
    const form = useForm({
        multi_factor_enabled: Boolean(values.multi_factor_enabled),
        multi_factor_force: Boolean(values.multi_factor_force),
        multi_factor_recoverable: Boolean(values.multi_factor_recoverable),
        microsoft_enabled: Boolean(values.microsoft_enabled),
        microsoft_client_id: (values.microsoft_client_id as string) ?? '',
        microsoft_client_secret: '',
        microsoft_redirect: (values.microsoft_redirect as string) ?? '',
        microsoft_tenant: (values.microsoft_tenant as string) ?? '',
    });
    const set = (k: string) => (v: string) => form.setData(k as never, v as never);
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.put('/app/settings/auth', { preserveScroll: true }); };
    const ms = form.data.microsoft_enabled;

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <fieldset className="space-y-3 rounded-md border p-4">
                <legend className="px-1 text-sm font-medium">{t.auth.multi_factor.section}</legend>
                <p className="text-xs text-muted-foreground">MFA-Login wird in einem späteren Schritt umgesetzt; diese Einstellungen werden bereits gespeichert.</p>
                <SwitchField id="mfa_enabled" label={t.auth.multi_factor.field.enabled} checked={form.data.multi_factor_enabled} onChange={(v) => form.setData('multi_factor_enabled', v)} />
                <SwitchField id="mfa_force" label={t.auth.multi_factor.field.force} checked={form.data.multi_factor_force} onChange={(v) => form.setData('multi_factor_force', v)} />
                <SwitchField id="mfa_recoverable" label={t.auth.multi_factor.field.recoverable} checked={form.data.multi_factor_recoverable} onChange={(v) => form.setData('multi_factor_recoverable', v)} />
            </fieldset>
            <fieldset className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                <legend className="px-1 text-sm font-medium">{t.auth.microsoft.section}</legend>
                <div className="sm:col-span-2"><SwitchField id="ms_enabled" label={t.auth.microsoft.field.enabled} checked={ms} onChange={(v) => form.setData('microsoft_enabled', v)} /></div>
                <TextField id="ms_client_id" label={t.auth.microsoft.field.client_id} value={form.data.microsoft_client_id} onChange={set('microsoft_client_id')} error={form.errors.microsoft_client_id} required={ms} />
                <div><PasswordField id="ms_client_secret" label={t.auth.microsoft.field.client_secret} value={form.data.microsoft_client_secret} onChange={set('microsoft_client_secret')} error={form.errors.microsoft_client_secret} /><p className="-mt-2 text-xs text-muted-foreground">Leer lassen, um den gespeicherten Wert zu behalten.</p></div>
                <TextField id="ms_redirect" label={t.auth.microsoft.field.redirect} value={form.data.microsoft_redirect} onChange={set('microsoft_redirect')} error={form.errors.microsoft_redirect} required={ms} />
                <TextField id="ms_tenant" label={t.auth.microsoft.field.tenant} value={form.data.microsoft_tenant} onChange={set('microsoft_tenant')} error={form.errors.microsoft_tenant} required={ms} />
            </fieldset>
            <Button type="submit" disabled={form.processing}>Save</Button>
        </form>
    );
}
```

- [ ] **Step 4: Wire the three tabs into `index.tsx`** — import `StorageTab`/`WarrantyTab`/`AuthTab` and replace the three placeholder `TabsContent` bodies with `<StorageTab t={t} values={settings.storage} />`, `<WarrantyTab t={t} values={settings.warranty} />`, `<AuthTab t={t} values={settings.auth} />`.

- [ ] **Step 5: Extend the test** — add to `index.test.tsx`: switching to the Warranty tab and asserting the enabled switch renders; add the missing `t.storage.field`/`t.warranty.field`/`t.auth.*.field` keys to the sample `t` so the tabs render. Example:

```tsx
it('renders the Warranty tab controls', () => {
    render(<Settings {...(props as never)} />);
    fireEvent.click(screen.getByRole('tab', { name: 'Garantie' }));
    expect(screen.getByLabelText(/aktiviert/i)).toBeInTheDocument();
});
```
(Flesh out the `t` fixture with the warranty/storage/auth field labels used by the tabs; keep the Task-3 assertion intact.)

- [ ] **Step 6: Run + commit**

`ddev exec pnpm exec vitest run resources/js/pages/settings/__tests__/index.test.tsx` → PASS; full JS suite + build clean.

```bash
git add resources/js/pages/settings
git commit -m "feat(settings): storage/warranty/auth tabs"
```

---

### Task 5: Final verification

- [ ] **Step 1:** `ddev exec ./vendor/bin/phpunit` → green.
- [ ] **Step 2:** `ddev exec pnpm run test` → green.
- [ ] **Step 3:** `ddev exec pnpm run build` → succeeds.
- [ ] **Step 4:** `ddev exec ./vendor/bin/pint --test app/Http/Controllers/App/SettingsController.php app/Http/Requests/App` → clean (the new files; the pre-existing repo-wide pint debt is a separate follow-up).
- [ ] **Step 5: Manual verify** — `/app/settings` shows 5 tabs; General save changes the app name; Mail driver select swaps sections and the test dialog sends (log driver); secrets submit blank without wiping; Storage/Warranty test buttons flash results; Auth SSO fields require when enabled; `/app-old` settings cluster still works.
- [ ] **Step 6:** Commit anything outstanding.

---

## Self-Review Notes

- **Spec coverage:** index+secret-strip+General/Mail/Storage saves → Task 1; Auth/Warranty saves + 3 test actions + warning flash → Task 2; shell + General/Mail tabs + nav → Task 3; Storage/Warranty/Auth tabs → Task 4; tests throughout + Task 5.
- **Secret safety:** `index` never includes secret keys; `assignSecrets` only writes when `filled()`; React secret fields start blank with a keep hint. Covered by `test_update_mail_keeps_secret_when_blank_and_overwrites_when_present`.
- **Runtime apply:** every `persist*` ends with `app(ApplySettings::class)()`; asserted via `config()` in the save tests.
- **Reuse:** settings classes, ApplySettings, middleware, command, WarrantyScanner, mails, lang file, migrations all untouched.
- **Consistency:** controller prop shape (`t`, `options`, `settings.{group}`) matches `index.tsx` `Props`; route names `app.settings.*`; warranty `lead_days` normalized server-side and sent back as strings for the TagsInput.
- **Deferred:** MFA login; role-gated settings access; tighter per-driver required validation. Filament settings cluster removed at cutover (Spec 9).

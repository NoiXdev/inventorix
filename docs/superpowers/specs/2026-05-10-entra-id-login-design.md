# Microsoft Entra ID Login for Filament Panel

## Goal

Add **optional** Microsoft Entra ID (single-tenant) sign-in to the Filament
panel as a secondary authentication method alongside the existing
email/password form. The feature is toggled via `.env`; when disabled, no
button is shown, no routes are registered, and behavior is identical to today.

## Decisions (from brainstorming)

| Area | Decision |
|---|---|
| Feature toggle | Optional, controlled by `MS_LOGIN_ENABLED` in `.env` (default `false`). |
| Coexistence | Default Filament login page, "Login via Entra ID" button injected above the form. Email/password remains the primary fallback. |
| Tenant scope | Single tenant — only the configured Entra tenant. |
| Provisioning | Pre-provisioned only. SSO never creates users. |
| Matching | Entra Object ID (`oid`) primary, email fallback when `entra_id` is `NULL` (auto-link on first SSO login). |
| Login UI | Default Filament login page + render hook injecting a single "Login via Entra ID" button. No custom Login class, no custom Blade view. |
| Attribute sync | Sync `firstname`, `lastname`, `name`, `email` from Microsoft Graph claims on every SSO login. |

## Library

- `laravel/socialite`
- `socialiteproviders/microsoft-azure`

The provider drives `https://login.microsoftonline.com/{tenant}/oauth2/v2.0/...`
with the configured single tenant ID, so non-tenant accounts are rejected at
Microsoft's authorize endpoint before they ever reach our callback.

All composer / artisan commands during implementation run through ddev (e.g.
`ddev composer require socialiteproviders/microsoft-azure`,
`ddev artisan migrate`).

## Architecture

```
routes/web.php
  // Both routes registered ONLY when config('services.microsoft-azure.enabled') is true.
  GET /auth/microsoft/redirect   → MicrosoftAuthController@redirect    (name: auth.microsoft.redirect)
  GET /auth/microsoft/callback   → MicrosoftAuthController@callback    (name: auth.microsoft.callback)

app/Http/Controllers/Auth/MicrosoftAuthController.php
app/Services/Auth/EntraIdAuthService.php
app/Exceptions/Auth/EntraAuthException.php       (abstract base, getUserMessage())
app/Exceptions/Auth/EntraTenantMismatchException.php
app/Exceptions/Auth/EntraUserNotProvisionedException.php
app/Exceptions/Auth/EntraLoginDisabledException.php

resources/views/filament/auth/entra-button.blade.php
  // Renders the "Login via Entra ID" button + optional flashed error alert.
  // Returns nothing if the feature flag is off (defense-in-depth, alongside route guard).

app/Providers/AppServiceProvider.php              (SocialiteWasCalled listener — only when enabled)
app/Providers/Filament/AppPanelProvider.php       (renderHook injecting the Blade partial)

config/services.php                               (microsoft-azure block, includes 'enabled')

database/migrations/<ts>_add_entra_id_to_users_table.php
```

**No custom `App\Filament\Pages\Auth\Login` class. No override of the Filament
login Blade view.** The button is added via Filament's `renderHook` system.

## Components

### Feature flag

Single source of truth: `config('services.microsoft-azure.enabled')`, derived
from `MS_LOGIN_ENABLED` (boolean, default `false`).

When `false`:
- The two `/auth/microsoft/*` routes are not registered.
- The render hook returns an empty string (the button is not shown).
- The `SocialiteWasCalled` listener is not registered.
- The migration still runs (`entra_id` column is harmless when unused).

The toggle is read at boot. Flipping it requires clearing config cache
(`ddev artisan config:clear`).

### `MicrosoftAuthController`

Two methods, ~50 LOC. Identical to the prior design.

```php
public function redirect(): RedirectResponse
{
    return Socialite::driver('microsoft-azure')
        ->scopes(['openid', 'profile', 'email'])
        ->redirect();
}

public function callback(EntraIdAuthService $auth): RedirectResponse
{
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
    } catch (\Throwable $e) {
        report($e);
        return redirect()->route('filament.app.auth.login')
            ->with('entra_error', __('Microsoft sign-in failed. Please try again.'));
    }
}
```

### `EntraIdAuthService`

Pure logic, no HTTP. Three public methods:

- `assertTenantMatches(SocialiteUser $msUser): void`
  Reads `tid` from `$msUser->user` (raw Graph/id-token payload). Compares to
  `config('services.microsoft-azure.tenant')`. Throws `EntraTenantMismatchException`
  on mismatch.

- `resolveUser(SocialiteUser $msUser): User`
  Lookup chain:
  1. `User::where('entra_id', $msUser->id)->first()` — if found, return.
  2. Else `User::whereRaw('LOWER(email) = ?', [strtolower($msUser->email ?? $msUser->user['userPrincipalName'] ?? '')])->whereNull('entra_id')->first()`
     — if found, set `entra_id = $msUser->id` and return (link on first SSO).
  3. Else throw `EntraUserNotProvisionedException`.

  After lookup, if `! $user->login_enabled`, throw `EntraLoginDisabledException`.

- `syncAttributes(User $user, SocialiteUser $msUser): void`
  Updates `firstname` from `givenName`, `lastname` from `surname`, `name` from
  `displayName`, `email` from `mail ?? userPrincipalName`. Persists `entra_id`
  if newly linked. Single `save()`.

### Custom exceptions

```
EntraAuthException (abstract)
  abstract public function getUserMessage(): string;

EntraTenantMismatchException        — "This Microsoft account is not from the authorized tenant."
EntraUserNotProvisionedException    — "Your Microsoft account is not authorized for this app. Contact an administrator."
EntraLoginDisabledException         — "Your account is disabled. Contact an administrator."
```

All translatable via Laravel's `__()`.

### Login button injection (no custom page)

`AppPanelProvider::panel()` adds a render hook:

```php
->renderHook(
    'panels::auth.login.form.before',
    fn () => view('filament.auth.entra-button'),
)
```

`resources/views/filament/auth/entra-button.blade.php`:

```blade
@if (config('services.microsoft-azure.enabled'))
    @if (session('entra_error'))
        <div class="fi-fo-field-wrp-error mb-4 text-danger-600 text-sm">
            {{ session('entra_error') }}
        </div>
    @endif

    <a
        href="{{ route('auth.microsoft.redirect') }}"
        class="fi-btn fi-btn-color-gray fi-btn-outlined fi-btn-size-md fi-btn-fullwidth mb-4"
    >
        {{ __('Login via Entra ID') }}
    </a>

    <div class="fi-fo-field-wrp-label mb-4 text-center text-sm text-gray-500">
        {{ __('or sign in with email') }}
    </div>
@endif
```

The render hook constant is
`\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE`
(`panels::auth.login.form.before`), confirmed present in Filament 3.3.

### Socialite provider registration

In `AppServiceProvider::boot()`, gated by the feature flag:

```php
if (config('services.microsoft-azure.enabled')) {
    Event::listen(SocialiteWasCalled::class, function ($event) {
        $event->extendSocialite('microsoft-azure', \SocialiteProviders\Azure\Provider::class);
    });
}
```

### Route registration

In `routes/web.php`, gated by the feature flag:

```php
if (config('services.microsoft-azure.enabled')) {
    Route::get('/auth/microsoft/redirect', [MicrosoftAuthController::class, 'redirect'])
        ->name('auth.microsoft.redirect');
    Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback'])
        ->name('auth.microsoft.callback');
}
```

### Configuration

`config/services.php`:

```php
'microsoft-azure' => [
    'enabled'       => filter_var(env('MS_LOGIN_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'client_id'     => env('MS_CLIENT_ID'),
    'client_secret' => env('MS_CLIENT_SECRET'),
    'redirect'      => env('MS_REDIRECT_URI'),
    'tenant'        => env('MS_TENANT_ID'),
],
```

`.env.example` additions:

```
MS_LOGIN_ENABLED=false
MS_CLIENT_ID=
MS_CLIENT_SECRET=
MS_TENANT_ID=
MS_REDIRECT_URI="${APP_URL}/auth/microsoft/callback"
```

### Migration

`add_entra_id_to_users_table`:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('entra_id')->nullable()->unique()->after('email');
});
```

The column is added regardless of the feature flag — it's harmless when unused
and avoids a second migration if the flag is later flipped on.

## Data flow

### Happy path (existing user, first SSO login)

1. User clicks "Login via Entra ID" → `GET /auth/microsoft/redirect`.
2. Controller redirects to Microsoft authorize endpoint with `state`, `nonce`,
   scopes `openid profile email`.
3. User authenticates and consents at Microsoft.
4. Microsoft redirects to `GET /auth/microsoft/callback?code=...&state=...`.
5. Controller calls `Socialite::driver('microsoft-azure')->user()` — Socialite
   exchanges the code, validates `state`, fetches the Graph `/me` payload.
6. `assertTenantMatches()` verifies `tid` matches config (defense-in-depth).
7. `resolveUser()` runs the lookup chain; if matched by email, sets `entra_id`.
8. `loginEnabled` gate — disabled users are rejected.
9. `syncAttributes()` updates names + email + `entra_id` and saves.
10. `Auth::guard('web')->login()`, `session()->regenerate()`, redirect to
    `Filament::getUrl()` (or `intended()` URL if any).

### Rejection paths

| Cause | User-facing message |
|---|---|
| `tid` mismatch | "This Microsoft account is not from the authorized tenant." |
| OAuth state invalid / Socialite throws | "Microsoft sign-in failed. Please try again." |
| User not in DB | "Your Microsoft account is not authorized for this app. Contact an administrator." |
| `login_enabled = false` | "Your account is disabled. Contact an administrator." |
| Anything else (network, etc.) | "Microsoft sign-in failed. Please try again." (logged via `report()`) |

All rejections redirect to `filament.app.auth.login` and flash `entra_error`.
The render-hook view reads the flash value and shows it above the button.

### Disabled-flag flow

When `MS_LOGIN_ENABLED=false`:
- `/auth/microsoft/redirect` and `/auth/microsoft/callback` return 404.
- The login page renders identically to today (no button, no alert area).
- No Socialite driver is registered.

## Security

- **Tenant pinning** at the authorize URL is the primary defense.
- **`tid` re-check** on the callback is defense-in-depth.
- **OAuth `state`** verified by Socialite (we do not call `stateless()`).
- **Session fixation**: `session()->regenerate()` after login.
- **No privilege grant via SSO**: `entra_id` linking never sets `login_enabled`.
- **All exceptions reported** via `report()`; only user-safe messages flashed.
- **Case-insensitive email match** via `LOWER(email)` to avoid driver
  inconsistencies between SQLite and others.
- **Feature flag** acts as a kill switch — flipping `MS_LOGIN_ENABLED=false`
  and clearing config cache disables every entry point in one place.

## Edge cases

- **Concurrent first-time link**: unique index on `entra_id` causes the second
  request to fail; the user retries successfully. Acceptable.
- **Email changed in Entra after linking**: subsequent logins still match by
  `entra_id` and the new email overwrites the local copy via attribute sync.
- **User removed from Entra tenant**: rejected upstream by Microsoft; local DB
  unaffected (admin-controlled).
- **`mail` is null in Graph response**: falls back to `userPrincipalName`. If
  both are null, `resolveUser()` throws `EntraUserNotProvisionedException`.
- **Two local users with same email**: prevented by the existing unique
  constraint on `users.email`.
- **Break-glass password login**: untouched; same form on the same page.
- **Feature flag flipped at runtime**: requires `ddev artisan config:clear`
  for routes and the listener to re-evaluate. Documented.

## Testing

`tests/Feature/Auth/MicrosoftLoginTest.php` (feature flag forced on per-test
via `config()->set('services.microsoft-azure.enabled', true)` and route
re-registration helper or `RefreshApplication`):

1. `callback_logs_in_existing_user_matched_by_entra_id`
2. `callback_links_existing_user_by_email_when_entra_id_is_null`
3. `callback_rejects_unknown_user`
4. `callback_rejects_disabled_user`
5. `callback_rejects_wrong_tenant`
6. `callback_redirects_with_generic_error_on_unexpected_failure`
7. `routes_return_404_when_feature_disabled` — with
   `MS_LOGIN_ENABLED=false` ensure both routes are 404.
8. `login_page_does_not_render_button_when_feature_disabled` — render the
   login page, assert "Login via Entra ID" is absent.
9. `login_page_renders_button_when_feature_enabled` — assert button is present.

`tests/Unit/Auth/EntraIdAuthServiceTest.php`:

1. `sync_attributes_overwrites_firstname_lastname_name_email`

Tests use `Socialite::shouldReceive('driver->user')->andReturn(...)` with a
fabricated `SocialiteUser` (id, email, name, raw payload including `tid`).
Config for `services.microsoft-azure.tenant` is set per-test via `config()->set()`.

Test runner: `ddev artisan test`.

No browser/E2E test — login page rendering is covered by Filament; the only
custom interaction (clicking the Microsoft button) is a plain `<a>` link.

## Manual verification checklist

Performed once after implementation:

- [ ] App registration created in Entra portal (single-tenant), redirect URI
      `<APP_URL>/auth/microsoft/callback` registered.
- [ ] `.env` populated with `MS_LOGIN_ENABLED=true`, `MS_CLIENT_ID`,
      `MS_CLIENT_SECRET`, `MS_TENANT_ID`, `MS_REDIRECT_URI`.
- [ ] `ddev artisan config:clear` then load login page → button is visible.
- [ ] Sign in via Microsoft button → redirected → consent → land on panel.
- [ ] Sign out, sign in with a non-tenant Microsoft account → rejected with
      tenant-mismatch message.
- [ ] Disable a user (`login_enabled = false`), attempt SSO → rejected with
      disabled-account message.
- [ ] Email/password fallback still works.
- [ ] Set `MS_LOGIN_ENABLED=false`, `ddev artisan config:clear`, load login
      page → button is gone, `/auth/microsoft/redirect` returns 404.

## Out of scope

- Auto-provisioning users from Entra.
- Group/role mapping from Entra to local roles.
- Microsoft Graph access beyond `/me` (no calendar, files, etc.).
- Single sign-out / back-channel logout.
- Multiple Filament panels (only `AppPanelProvider` exists).

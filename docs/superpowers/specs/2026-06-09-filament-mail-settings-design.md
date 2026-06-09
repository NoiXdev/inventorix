# Filament Mail Settings (admin-managed) — Design

**Date:** 2026-06-09
**Status:** Approved, ready for implementation plan

## Goal

Let admins configure mail delivery (and other app settings) through the Filament
**App** panel instead of editing `.env`. Settings are stored in the database,
applied to Laravel's runtime config, and take effect without restarting workers —
which matters because this stack runs **Octane** (long-lived web workers) and
**Horizon** (long-lived queue workers).

## Scope

- Full mail **driver picker**: `smtp`, `postal`, `ses`, `postmark`, `resend`,
  plus `log`/`sendmail`.
- A reusable **Settings cluster** in the App panel with sub-navigation, hosting a
  **Mail** page now and a **General** page (example: `app.name`) for the future.
- **No permission gating yet** — every authenticated panel user can edit. Access
  control will be layered on later.

## Decisions

| Topic | Decision |
|-------|----------|
| Storage | `spatie/laravel-settings` — typed settings classes backed by the `settings` table |
| Drivers | Full picker (SMTP, Postal, SES, Postmark, Resend, log, sendmail) |
| Env relationship | **DB overrides env; env seeds the defaults.** Existing deployments keep working untouched until an admin saves. |
| Secrets | Encrypted at rest (`encrypted` cast); masked/`password()` fields; blank submit keeps existing value |
| Runtime application | **Per-request + per-job from cached settings** (approach A), so it stays current under Octane + Horizon |
| Test email | Yes — header action that sends using current form state and surfaces transport errors |
| UI grouping | Filament **Cluster** with sub-navigation |

## Components

### 1. Storage — settings classes

**`App\Settings\MailSettings`** (group `mail`):

- `default_mailer` — `string` (selected driver)
- From: `from_address`, `from_name`
- SMTP: `smtp_host`, `smtp_port`, `smtp_scheme` (tls/ssl/null), `smtp_username`, `smtp_password` *(encrypted)*
- SES: `ses_key`, `ses_secret` *(encrypted)*, `ses_region`
- Postmark: `postmark_token` *(encrypted)*, `postmark_message_stream_id`
- Resend: `resend_key` *(encrypted)*
- Postal: `postal_host`, `postal_key` *(encrypted)* (per `synergitech/laravel-postal`)

**`App\Settings\GeneralSettings`** (group `general`):

- `app_name` — `string`, seeded from `config('app.name')`. Room to grow
  (timezone, pagination, etc.) without new wiring.

Secrets use spatie's `encrypted` cast → ciphertext at rest. Settings migrations
under `database/settings/` create the rows and **seed defaults from the current
`env`/`config('mail')` and `config('app.name')`** values.

### 2. Runtime application — `App\Support\ApplySettings`

A single invokable action that pulls `MailSettings` and `GeneralSettings` from
spatie's cache (cheap cache hit) and writes them into runtime config:

- `config('mail.default')` = `default_mailer`
- Fills the matching block: SMTP → `mail.mailers.smtp.*`; SES → `services.ses.*`;
  Postmark → `services.postmark.*`; Resend → `services.resend.*`; Postal → its
  config keys
- `config('mail.from.address'|'name')`
- `config('app.name')` from `GeneralSettings`
- Decrypts secrets (cast handles it) and injects plaintext into config in-memory only
- Calls `Mail::purge($mailer)` (and forgets `mail.manager` if needed) so the next
  send rebuilds transport from fresh config

**Wiring (works under Octane + Horizon):**

- **Web:** `App\Http\Middleware\ApplyRuntimeSettings` appended to the App panel —
  applies current settings on every panel request.
- **Queue:** `App\Listeners\ApplySettingsToJob` on the `JobProcessing` event —
  applies before each Horizon job, so queued mail uses current settings.
- **Cache busting:** the settings form's save handler refreshes/clears the spatie
  settings cache so all long-lived workers pick up changes on their next
  request/job — no worker restart required.

### 3. Filament Cluster — `Settings`

`App\Filament\App\Clusters\Settings` — one nav item ("Settings") with
sub-navigation across its child pages. Pages live under
`App\Filament\App\Clusters\Settings\Pages\` and bind to the cluster via
`protected static ?string $cluster = Settings::class;`. Both pages extend the
`filament/spatie-laravel-settings-plugin` `SettingsPage` base.

**`ManageMailSettings`** (bound to `MailSettings`):

- **From section:** `from_address` (email-validated), `from_name`.
- **Driver select:** `default_mailer` — `Select` with supported transports,
  `->live()` so the form reacts.
- **Driver-specific section:** each driver's fields wrapped in a `Section`/`Group`
  with `->visible(fn (Get $get) => $get('default_mailer') === '<driver>')`.
- **Secret fields:** `TextInput->password()->revealable()`, rendered empty (not
  pre-filled with ciphertext). Blank submit keeps the stored secret; a new value
  replaces it (`dehydrated(fn ($state) => filled($state))`).
- **Validation:** `from_address` email; SMTP host/port required-when-driver; port
  numeric; secrets optional.

**`ManageGeneralSettings`** (bound to `GeneralSettings`):

- `app_name` — seeded from `config('app.name')`, drives the panel brand name and
  default mail "from name". Example page to demonstrate the cluster grows cleanly.

### 4. Test-email action

Header action **"Send test email"** on `ManageMailSettings`:

- Modal with one field: recipient address, **prefilled with the logged-in user's
  email** (editable).
- On submit: persist current form state → run `ApplySettings` → send a small
  `App\Mail\TestMail`.
- try/catch: success → green `Notification`; failure → red `Notification` whose
  body surfaces the transport exception message (the actual SMTP/API error).

## Testing

- **Unit:** `ApplySettings` maps each driver's `MailSettings` → correct `config()`
  keys; `GeneralSettings` → `config('app.name')`; secrets decrypt correctly; blank
  secret submit preserves the stored value.
- **Feature:** settings migration seeds defaults from env; saving the Mail page
  persists + busts cache; test-email action sends via `Mail::fake()` and surfaces
  failures as notifications; the cluster + sub-navigation render and both pages are
  reachable.

## New / changed files (approx.)

```
app/Settings/MailSettings.php
app/Settings/GeneralSettings.php
app/Support/ApplySettings.php
app/Mail/TestMail.php
app/Http/Middleware/ApplyRuntimeSettings.php
app/Listeners/ApplySettingsToJob.php            (JobProcessing)
app/Filament/App/Clusters/Settings.php
app/Filament/App/Clusters/Settings/Pages/ManageMailSettings.php
app/Filament/App/Clusters/Settings/Pages/ManageGeneralSettings.php
database/settings/xxxx_create_mail_settings.php
database/settings/xxxx_create_general_settings.php
config/settings.php                              (spatie publish)
```

Plus: register the `JobProcessing` listener, append the middleware to the App
panel, and add `filament/spatie-laravel-settings-plugin` + `spatie/laravel-settings`
to composer.

## Out of scope (for now)

- Permission/role gating of the Settings cluster (planned next).
- Per-tenant settings (panel is single-tenant).
- Additional General settings beyond `app_name`.

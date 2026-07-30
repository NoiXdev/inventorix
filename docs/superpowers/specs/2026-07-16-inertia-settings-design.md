# Inertia Migration — Spec 7: Settings

**Date:** 2026-07-16
**Status:** Approved (design)
**Part of:** FilamentPHP → React/Inertia migration (Spec 7 of 9)
**Depends on:** Specs 1–6 (kit, auth, form kit, nav). Filament settings cluster remains at `/app-old`.

## Background

Filament exposes 5 settings pages under a "Settings" cluster (`app/Filament/App/Clusters/Settings/Pages/Manage*Settings.php`), each backed by a spatie/laravel-settings class in `app/Settings/`:

| group | fields | encrypted | test action |
|---|---|---|---|
| **General** | `app_name` | — | — |
| **Mail** | `default_mailer`, `from_address`, `from_name`, + per-driver SMTP/SES/Postmark/Resend/Postal fields | `smtp_password, ses_secret, postmark_token, resend_key, postal_key` | send test email |
| **Storage** | S3 `key, secret, region, bucket, endpoint, use_path_style_endpoint, url` | `secret` | test connection (write/read/delete probe) |
| **Warranty** | `enabled`, `recipients[]` (emails), `lead_days[]` (ints) | — | send test digest |
| **Auth** | MFA `multi_factor_enabled/force/recoverable`; Microsoft SSO `microsoft_enabled/client_id/client_secret/redirect/tenant` | `microsoft_client_secret` | — |

**Runtime application is already framework-agnostic and live** — `App\Support\ApplySettings` pushes General/Mail/Auth/Storage into `config()` and is invoked on every `/app` request by `App\Http\Middleware\ApplyRuntimeSettings` (already in the `/app` group); `App\Console\Commands\ScanWarrantyExpiries` consumes Warranty. So this spec ports **only the editing UI + save/validation/secret handling**.

**Microsoft SSO is functional in `/app`** (`MicrosoftAuthController` + routes; login shows an Entra button via `config('services.microsoft-azure.enabled')`, driven by AuthSettings). **MFA is not yet implemented in `/app` login** (deferred) — its toggles are ported for parity and persist, taking effect once MFA login is built.

The Filament secret pattern to replicate exactly: `mutateFormDataBeforeFill` nulls secret fields (never sent to the browser), and each secret input is `dehydrated(fn ($s) => filled($s))` (a blank submit keeps the stored value).

## Locked decisions

- **All 5 groups, full parity** — including the 3 test actions (mail/storage/warranty) and the Auth MFA toggles.
- **One tabbed `/app/settings` page** (shadcn `Tabs`: General · Mail · Storage · Warranty · Auth), one **Settings** nav entry.
- **German content, server-resolved.** Reuse `lang/de/settings.php` untouched; the controller passes `trans('settings')` (the whole label tree) as one prop.
- **Secrets never leave the server** (nulled on read); a **blank submit keeps** the stored secret; only a non-blank value overwrites.
- **Auth-only gate** (no role system exists). PHPUnit; Vitest; ddev; pnpm.

## Goals

- All 5 settings groups editable at `/app/settings` with validation, secret-safe handling, immediate runtime application, and the mail/storage/warranty test actions. `/app-old` unchanged.

## Non-goals

- MFA login itself (later spec); role-based access to settings (**follow-up**: settings expose infra secrets to any authed user); new settings; changes to the `App\Settings\*` classes, `ApplySettings`, `ApplyRuntimeSettings`, `ScanWarrantyExpiries`, `WarrantyScanner`, `TestMail`, `WarrantyExpiryDigest`, or the settings migrations. No DB/schema changes.

## Design

### Backend — `App\Http\Controllers\App\SettingsController` (authenticated `/app`, names `app.settings.*`)

- `GET settings` → `settings/index` with:
  - `settings`: `{ general:{app_name}, mail:{…non-secret fields, secrets omitted}, storage:{…secret omitted}, warranty:{enabled, recipients, lead_days}, auth:{…client_secret omitted} }` — secret fields never included.
  - `t`: `trans('settings')` (full German label tree).
  - `options`: `{ mailDrivers:[{value,label}], mailSchemes:[{value,label}] }` (labels via `trans`).
- `PUT settings/general|mail|storage|warranty|auth` → each via a dedicated FormRequest; a private `persist*` helper loads the spatie Settings object, assigns non-secret fields, assigns each secret field **only when `filled()`**, normalizes warranty `lead_days` (int, `>=0`, unique, `sortDesc`), `->save()`, then `app(ApplySettings::class)()`; redirect back with a success flash.
- `POST settings/mail/test` (`{email}` + mail payload) → `persistMail` + apply + `Mail::to($email)->send(new TestMail)`; flash success or the exception message.
- `POST settings/storage/test` (storage payload) → `persistStorage` + apply + write/read/delete a random probe on `Storage::disk('s3')`; flash success/failure (mirrors Filament).
- `POST settings/warranty/test` (warranty payload) → `persistWarranty` + apply + `(new WarrantyScanner($settings->lead_days))->scan()`; if empty flash a "nothing to send" warning, else `Mail::to($recipients)->send(new WarrantyExpiryDigest($results))`; flash success/failure.

The test endpoints reuse the same `persist*` helpers as the PUT saves (save-then-test, matching Filament).

**FormRequests:** `default_mailer` in the 7 driver keys; `from_address` required email; `from_name` required; `smtp_port` nullable integer; `postal_domain`/storage `endpoint`/`url`/auth `microsoft_redirect` nullable url; warranty `recipients.*` email, `lead_days.*` integer `min:0`; auth `microsoft_client_id`/`redirect`/`tenant` `required_if:microsoft_enabled,true`; secret fields nullable string; booleans validated. `authorize()` true.

### Frontend — `resources/js/pages/settings/`

- **`index.tsx`** — the shell: `AppLayout` + shadcn `Tabs` (General/Mail/Storage/Warranty/Auth), rendering one tab component per group and passing that group's values + `t` + `options`.
- **`tabs/{general,mail,storage,warranty,auth}-tab.tsx`** — each an Inertia `useForm` posting to its group endpoint via the form kit (`TextField`, `SelectField`, `SwitchField`, `NumberField`, `PasswordField`, `TagsInput`). **Secret fields** are empty `PasswordField`s with a muted "leave blank to keep current" helper line beneath. **Mail tab**: from fields, driver `SelectField`, conditional per-driver sections, a **Send test email** `Dialog` (email input → `POST settings/mail/test`). **Storage tab**: S3 fields + a **Test connection** button (`POST settings/storage/test`). **Warranty tab**: enabled switch, recipients + lead_days `TagsInput`, a **Send test digest** button (`POST settings/warranty/test`). **Auth tab**: MFA switches + Microsoft SSO fields (`microsoft_client_id/redirect/tenant` marked required when enabled) + a note that MFA login arrives later.
- **Nav**: a new **Settings** group with one **Settings** entry (lucide `Settings`).
- Flash success/error/warning surfaces via the app's existing flash→toast handling.

### Reuse

The 5 `App\Settings\*` classes, `ApplySettings`, `ApplyRuntimeSettings`, `ScanWarrantyExpiries`, `WarrantyScanner`, `WarrantyExpiryDigest`, `TestMail`, `lang/de/settings.php`, the settings migrations — all untouched. Form kit, `Tabs`, `Dialog`, `Button`, `Card`.

## Testing

- **PHPUnit `SettingsControllerTest`**: `index` never includes any secret value; each group save persists and applies (assert `config()` — e.g. `app.name`, `mail.default`, `filesystems.disks.s3.bucket`); secret-preserving (blank keeps the stored secret, non-blank overwrites — assert via the raw settings value); validation (bad email/port/url, `required_if` microsoft fields) rejected; warranty `lead_days` normalized; mail test (`Mail::fake`, asserts `TestMail` to the address; failure path flashes error); storage test (`Storage::fake('s3')`, success path); warranty test (empty → warning; with due assets + recipients → digest sent); guest redirected.
- **Vitest** `settings/index` + tabs: tabs render and switch; mail driver select toggles the visible section; secret fields render empty with the keep hint; the mail test dialog posts; warranty `TagsInput` add/remove; auth SSO fields show required markers when enabled.

## Open questions / follow-ups (later)

- MFA login flow (a later spec); role/permission gate for settings; per-driver "required when selected" server validation could be tightened. Cutover (Spec 9) removes the Filament settings cluster; the `App\Settings\*` classes, `ApplySettings`, middleware, and command remain.

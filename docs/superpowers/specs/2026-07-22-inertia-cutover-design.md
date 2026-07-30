# Inertia Migration — Spec 9: Cutover (remove Filament)

**Date:** 2026-07-22
**Status:** Approved (design)
**Part of:** FilamentPHP → React/Inertia migration (Spec 9 of 9 — final)
**Depends on:** Specs 1–8 (the entire new `/app` replicates the Filament panel).

## Background

The new Inertia/React app at `/app` now covers everything the Filament panel did:
auth, assets (+attachments/incidents/history/import-export), lookups, users,
handovers, dashboard, reports, settings, and QR/printing. This spec removes
Filament and its glue so `/app` is the only application.

**Locked decisions:** keep the **`/app` prefix** (root `/` redirects to `/app`; no
route/href sweep); **fold the repo-wide Pint cleanup** into this cutover as a
standalone commit so CI's lint gate goes green.

The delicate part: several **shared, framework-agnostic** pieces are coupled to
Filament and must be **decoupled before the packages are removed**, and some
underlying packages are only pulled in transitively via Filament plugins.

## Coupling findings (verified)

- **6 enums** implement Filament contracts: `AssetState`, `AttachmentCategory`,
  `BuyType`, `HandoverType`, `RecipientKind` (`HasLabel`/`HasColor`), and
  `QrCodeGeneratorType` (deleted). The new app calls **`getLabel()`** widely;
  **`getColor()`/`getIcon()` are unused** outside Filament.
- **`User`** implements `FilamentUser`, `HasAppAuthentication`,
  `HasAppAuthenticationRecovery` (+ MFA concern traits, `canAccessPanel`). The
  new auth uses `login_enabled` directly; the MFA concerns are referenced only by
  the (deleted) panel provider.
- **`spatie/laravel-tags`** is **only transitive** via
  `filament/spatie-laravel-tags-plugin` — Asset tags (`HasTags`/`syncTags`) would
  break if the plugin is removed without promoting it to a direct dep.
  `spatie/laravel-settings` is already a direct dep.
- **`MicrosoftAuthController`** redirects to `Filament::getUrl()` and
  `filament.app.auth.login` (3 spots) — dead once Filament is gone.
- Filament registers the QR JS via `FilamentAsset` in `AppPanelProvider` and the
  scanner/qr-print modal via render hooks; those JS files are Alpine glue.

## Goals

- Filament, Livewire glue, and the old QR/scanner Alpine code are gone; the app
  boots and runs entirely through `/app`; `/` redirects to `/app`; the full test
  suite + build are green; CI lint (repo-wide Pint) passes.

## Non-goals

- Moving the app off the `/app` prefix (deferred). Re-implementing MFA (still
  deferred). Any behavior change to the new app. Removing the reused domain.

## Design

### 1. Decouple shared code (must precede package removal)

- **Enums** (`AssetState`, `AttachmentCategory`, `BuyType`, `HandoverType`,
  `RecipientKind`): remove `implements HasLabel/HasColor` and all `Filament\…`
  imports; **keep `getLabel()`** as a plain public method; **delete
  `getColor()`/`getIcon()`** and any `Filament\Support\Colors\Color` usage.
- **`User`**: drop `implements FilamentUser, HasAppAuthentication,
  HasAppAuthenticationRecovery`, the `InteractsWithAppAuthentication*` traits,
  `canAccessPanel()`, and the `Filament\…` imports. Keep `Authenticatable`, casts,
  `login_enabled`, `entra_id`, relations.

### 2. Dependencies (composer)

- **Add** `spatie/laravel-tags` as a direct `require` (pin to the currently-locked
  major).
- **Remove** `filament/filament`, `filament/spatie-laravel-settings-plugin`,
  `filament/spatie-laravel-tags-plugin`, `novadaemon/filament-combobox`,
  `simplesoftwareio/simple-qrcode`. Remove the `@php artisan filament:upgrade`
  (and any `filament:assets`) composer scripts. Update the description/keywords to
  drop "Filament".
- `composer update` in ddev; confirm the app boots and Docker/CI build still work.

### 3. Delete Filament application code

- `app/Filament/**`; `app/Providers/Filament/AppPanelProvider.php` and its entry in
  `bootstrap/providers.php`.
- `app/Livewire/Scanner.php`; `resources/views/livewire/scanner.blade.php`.
- `app/Http/Controllers/QrCodeGeneratorController.php`; `app/Enums/QrCodeGeneratorType.php`;
  `app/Exceptions/QrCodeGeneratorException.php`.
- `resources/views/filament/**`; `resources/views/forms/components/qr-code.blade.php`.
- Alpine glue: `resources/js/plugins/scanner.ts`, `resources/js/plugins/qr-print/modal.ts`,
  `resources/js/plugins/qr-print/index.ts`; remove their entries from `vite.config`.
- Published Filament assets: `public/css/filament`, `public/js/filament`.

**Explicitly KEEP** (reused by the new app): all Models, Services
(`HandoverService`, `WarrantyScanner`, `AssetImport`, `ReportExportService`,
`ApplySettings`), `App\Settings\*`, `App\Reports\*` + `App\Reports\ReportColumn`,
Mails, `resources/views/pdf/**`, `App\Http\Middleware\ApplyRuntimeSettings`,
`spatie/laravel-activitylog|settings|tags`, the `qr-print` **engine**
(`controller/layout/rasterizer/brother-protocol/webusb-transport/dk-rolls/qr/types`)
and `resources/js/plugins/scanner-camera-error.ts`.

### 4. Routes

- Root: replace `Route::get('/', fn () => to_route('filament.app.pages.dashboard'))`
  with a redirect to `/app` (e.g. `Route::get('/', fn () => redirect('/app'))`).
- Remove the `qg` route (and the `QrCodeGeneratorController` import).
- `MicrosoftAuthController`: repoint the success redirect to `/app`
  (`redirect()->intended('/app')`) and the two failure redirects to
  `route('app.login')`; drop the `Filament` facade import.

### 5. Tests

- Delete Filament-coupled tests: `tests/Feature/Filament/**`,
  `tests/Feature/ScannerRedirectTest.php`, `tests/Unit/Widgets/WarrantyStatsWidgetTest.php`.
  (Re-covered by the new `SettingsControllerTest`, `ReportController*`/report tests,
  `ScanControllerTest`, `GeneratorControllerTest`, dashboard/handover tests.)
- Add a small **`RootRedirectTest`**: `GET /` → redirect to `/app`.

### 6. Repo-wide Pint (folded in)

As a **separate, final commit**: run `vendor/bin/pint` across the repo to fix the
~30 pre-existing Specs 1–5 style violations so CI's `lint` job (`pint --test`)
passes. No functional changes; verify the suite stays green afterward.

## Testing / verification

- Full PHPUnit suite green (after deleting Filament tests + adding the root test).
- `pnpm run test` + `pnpm run build` green (after removing the Alpine vite entries).
- `pint --test` clean repo-wide.
- Boot check: `/` redirects to `/app`; `/app` login → dashboard; a smoke pass over
  assets/handovers/reports/settings/QR still works; no `Filament`/`Livewire`
  class-not-found at runtime (grep the codebase for stray `Filament\\`/`Livewire\\`
  references outside deleted files).
- `composer.json`/lock no longer list Filament packages; `spatie/laravel-tags` is a
  direct dep.

## Open questions / follow-ups (later)

- Promote the app from `/app` to `/` (drop the prefix) as a follow-up.
- Re-implement MFA in the new app. The `User` MFA columns/migration can be dropped
  in a later cleanup if desired. The `Employee`/`User` split discussed separately.

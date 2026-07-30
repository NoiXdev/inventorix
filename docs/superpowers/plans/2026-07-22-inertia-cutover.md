# Inertia Migration — Cutover (Spec 9) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove FilamentPHP and all its glue so the Inertia/React app at `/app` is the only application; `/` redirects to `/app`; suite + build + repo-wide Pint all green.

**Architecture:** Decouple the shared enums + `User` from Filament contracts, delete all Filament application code/views/tests/provider, flip the root route + Microsoft auth redirects, remove the Filament composer packages (promoting `spatie/laravel-tags` to a direct dep), drop the Alpine JS glue + its vite entries, then run repo-wide Pint. Each task keeps the app bootable and the suite green.

**Tech Stack:** Laravel 13/PHP 8.4, Inertia v2 + React 19, spatie (activitylog/settings/tags), PHPUnit, Vitest. ddev; pnpm; composer.

## Global Constraints

- ddev for all commands. **PHP tests are PHPUnit** (`./vendor/bin/phpunit`); JS tests are **Vitest** (`pnpm exec vitest run`; if `pnpm` missing, `ddev exec corepack enable`). Composer via `ddev composer …`.
- Keep the **`/app` prefix** (no route/href sweep); root `/` → redirect to `/app`.
- **KEEP (the new app depends on these) — never delete:** all `app/Models`, `app/Services`, `app/Settings`, `app/Reports` (incl. `App\Reports\ReportColumn`), `app/Mail`, `app/DataObjects`, `App\Http\Middleware\ApplyRuntimeSettings`, `resources/views/pdf/**`, `spatie/laravel-activitylog|settings|tags`, the `qr-print` **engine** (`controller/layout/rasterizer/brother-protocol/webusb-transport/dk-rolls/qr/types`), and `resources/js/plugins/scanner-camera-error.ts`.
- Pint is a CI gate. Commit per task with trailer:
  `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`

## Coupling facts (verified)

- Enums implementing Filament contracts: `AssetState`+`HandoverType` (`HasColor`+`HasLabel`, use `Color::*` in `getColor()`), `AttachmentCategory`/`BuyType`/`RecipientKind` (`HasLabel` only). `getLabel()` is used everywhere; `getColor()`/`getIcon()` are unused by the new app.
- `User implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery` + traits `InteractsWithAppAuthentication[Recovery]` + `canAccessPanel()`; referenced only by the deleted panel provider.
- `spatie/laravel-tags` (locked `4.12.0`) is **only transitive** via `filament/spatie-laravel-tags-plugin`.
- `MicrosoftAuthController` redirects to `Filament::getUrl()` (success) and `route('filament.app.auth.login')` (2 failures).
- `bootstrap/providers.php` registers `AppPanelProvider`. `composer.json` has a `@php artisan filament:upgrade` script (line ~72) that will break `composer install` once Filament is gone.
- `vite.config.ts` inputs include `resources/js/plugins/scanner.ts` and `resources/js/plugins/qr-print/index.ts` (Alpine entries).

---

### Task 1: Delete Filament application code + tests; flip routes + Microsoft auth

**Files (delete):**
- `app/Filament/` (whole directory)
- `app/Providers/Filament/AppPanelProvider.php`
- `app/Livewire/Scanner.php`, `resources/views/livewire/scanner.blade.php`
- `app/Http/Controllers/QrCodeGeneratorController.php`, `app/Enums/QrCodeGeneratorType.php`, `app/Exceptions/QrCodeGeneratorException.php`
- `resources/views/filament/` (whole directory), `resources/views/forms/components/qr-code.blade.php`
- `tests/Feature/Filament/` (whole directory), `tests/Feature/ScannerRedirectTest.php`, `tests/Unit/Widgets/WarrantyStatsWidgetTest.php`

**Files (modify):** `bootstrap/providers.php`, `routes/web.php`, `app/Http/Controllers/Auth/MicrosoftAuthController.php`
**Files (create):** `tests/Feature/App/RootRedirectTest.php`

Note: Filament composer packages are still installed in this task, so the enum `implements HasLabel/HasColor` and `User implements FilamentUser` still resolve — the app stays bootable.

- [ ] **Step 1: Delete the Filament code + tests** (via `git rm -r`):
```bash
git rm -r app/Filament app/Providers/Filament resources/views/filament tests/Feature/Filament
git rm app/Livewire/Scanner.php resources/views/livewire/scanner.blade.php \
  app/Http/Controllers/QrCodeGeneratorController.php app/Enums/QrCodeGeneratorType.php \
  app/Exceptions/QrCodeGeneratorException.php resources/views/forms/components/qr-code.blade.php \
  tests/Feature/ScannerRedirectTest.php tests/Unit/Widgets/WarrantyStatsWidgetTest.php
```
(If `resources/views/livewire/` or `resources/views/forms/components/` is now empty, remove the empty dir too.)

- [ ] **Step 2: Unregister the panel provider** — edit `bootstrap/providers.php` to:
```php
<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
];
```

- [ ] **Step 3: Flip the root route + drop `qg`** — in `routes/web.php`:
  - Replace `Route::get('/', fn () => to_route('filament.app.pages.dashboard'));` with:
    ```php
    Route::get('/', fn () => redirect('/app'));
    ```
  - Delete the `qg` route line (`Route::get('/gq', [QrCodeGeneratorController::class, 'generate'])->name('qg');`) and remove the now-unused `use App\Http\Controllers\QrCodeGeneratorController;` import.

- [ ] **Step 4: Repoint Microsoft auth** — in `app/Http/Controllers/Auth/MicrosoftAuthController.php`:
  - Success: `return redirect()->intended(Filament::getUrl());` → `return redirect()->intended('/app');`
  - Both failure redirects: `return redirect()->route('filament.app.auth.login')…` → `return redirect()->route('app.login')…` (keep any `->with(...)` error payload).
  - Remove the `use Filament\Facades\Filament;` (or `use Filament\…`) import.

- [ ] **Step 5: Root redirect test**
```php
<?php // tests/Feature/App/RootRedirectTest.php
namespace Tests\Feature\App;

use Tests\TestCase;

class RootRedirectTest extends TestCase
{
    public function test_root_redirects_to_app(): void
    {
        $this->get('/')->assertRedirect('/app');
    }
}
```

- [ ] **Step 6: Verify + commit**

Run `ddev exec ./vendor/bin/phpunit` → green (Filament tests gone; new suite intact). Run `ddev exec php artisan route:list --json > /dev/null` to confirm routes resolve (no boot error). Pint the changed PHP.
```bash
git add bootstrap/providers.php routes/web.php app/Http/Controllers/Auth/MicrosoftAuthController.php tests/Feature/App/RootRedirectTest.php
git commit -m "chore(cutover): delete Filament app code + tests; root -> /app; repoint MS auth"
```

---

### Task 2: Decouple enums + `User` from Filament contracts

**Files (modify):** `app/Enums/{AssetState,HandoverType,AttachmentCategory,BuyType,RecipientKind}.php`, `app/Models/User.php`

After this task, no code references `Filament\` at all (packages still installed but unused).

- [ ] **Step 1: Enums with `HasLabel` only** (`AttachmentCategory`, `BuyType`, `RecipientKind`): remove the `use Filament\Support\Contracts\HasLabel;` import and the `implements HasLabel` on the `enum` line. Keep the `getLabel()` method verbatim (now a plain method).

- [ ] **Step 2: Enums with color** (`AssetState`, `HandoverType`): remove the three imports (`use Filament\Support\Colors\Color;`, `use Filament\Support\Contracts\HasColor;`, `use Filament\Support\Contracts\HasLabel;`) and the `implements HasColor, HasLabel`. Keep `getLabel()`. **Delete the entire `getColor()` method** (unused by the new app). Leave any other domain methods (e.g. `HandoverType::allowedStateFrom()/stateTo()/assignsRecipientAsOwner()`) untouched.

- [ ] **Step 3: `User`** — edit `app/Models/User.php`:
  - Remove imports: `Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication`, `…\InteractsWithAppAuthenticationRecovery`, `Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication`, `…\HasAppAuthenticationRecovery`, `Filament\Models\Contracts\FilamentUser`, `Filament\Panel`.
  - Class line → `class User extends Authenticatable` (drop the three `implements`).
  - `use` (traits) line → `use CausesActivity, HasFactory, HasUuids, Notifiable;` (drop the two `InteractsWithAppAuthentication*` traits).
  - Delete the `canAccessPanel(Panel $panel)` method.
  - Keep `$hidden`, `casts()`, fillable, relations, everything else.

- [ ] **Step 4: Verify + commit**

Run: `grep -rn "Filament\\\\" app/ || echo "clean"` → must print `clean` (no matches anywhere under `app/`). Then `ddev exec ./vendor/bin/phpunit` → green (esp. asset/handover tests that use `getLabel()` on these enums). Pint the 6 files.
```bash
git add app/Enums app/Models/User.php
git commit -m "refactor(cutover): decouple enums + User from Filament contracts"
```

---

### Task 3: Composer — promote spatie/laravel-tags, remove Filament packages

**Files (modify):** `composer.json` (+ `composer.lock` via composer), possibly `.github/workflows/ci.yml` (stale comments only).

- [ ] **Step 1: Edit `composer.json` `require`**
  - Add: `"spatie/laravel-tags": "^4.12",`
  - Remove: `"filament/filament"`, `"filament/spatie-laravel-settings-plugin"`, `"filament/spatie-laravel-tags-plugin"`, `"novadaemon/filament-combobox"`, `"simplesoftwareio/simple-qrcode"`.
  - In `scripts`, remove the `"@php artisan filament:upgrade"` line (and any `filament:assets`); if that leaves a trailing-comma/empty array, fix the JSON.
  - Update `keywords` to drop `"filament"` and the `description` to drop "and Filament".

- [ ] **Step 2: Resolve dependencies**
```bash
ddev composer update spatie/laravel-tags --with-all-dependencies
```
If composer still resolves Filament as required by something, run a full `ddev composer update` and inspect. Confirm `spatie/laravel-tags` remains in `composer.lock` and Filament packages are gone:
```bash
grep -c '"name": "filament/' composer.lock   # expect 0
grep -c '"name": "spatie/laravel-tags"' composer.lock   # expect 1
```

- [ ] **Step 3: Boot + tag sanity**

`ddev exec php artisan about > /dev/null` (boots cleanly). Run the asset suite that exercises tags: `ddev exec ./vendor/bin/phpunit --filter Asset` → green (confirms `HasTags`/`syncTags` still work via the now-direct `spatie/laravel-tags`).

- [ ] **Step 4: Stale CI comments (optional, cosmetic)** — `.github/workflows/ci.yml` lines ~51/97 have comments mentioning "boots Filament's panel provider". Update or delete those comment lines so they don't mislead (no functional change). Do NOT alter the build/test steps.

- [ ] **Step 5: Full verify + commit**

`ddev exec ./vendor/bin/phpunit` → green.
```bash
git add composer.json composer.lock .github/workflows/ci.yml
git commit -m "chore(cutover): drop Filament composer packages; add spatie/laravel-tags direct"
```

---

### Task 4: Remove Alpine JS glue + vite entries + published Filament assets

**Files (delete):** `resources/js/plugins/scanner.ts`, `resources/js/plugins/qr-print/modal.ts`, `resources/js/plugins/qr-print/index.ts`, `public/css/filament/`, `public/js/filament/`
**Files (modify):** `vite.config.ts`

- [ ] **Step 1: Delete the Alpine glue + published assets**
```bash
git rm resources/js/plugins/scanner.ts resources/js/plugins/qr-print/modal.ts resources/js/plugins/qr-print/index.ts
rm -rf public/css/filament public/js/filament
```
(These JS files have no Vitest tests; the qr-print engine + `scanner-camera-error.ts` remain and keep their tests.)

- [ ] **Step 2: Trim `vite.config.ts` inputs** — remove the `resources/js/plugins/scanner.ts` and `resources/js/plugins/qr-print/index.ts` entries (and the `//Scanner Plugin` / `//QR Print Plugin` / `//'resources/js/app.ts'` comment lines). Keep the Inertia entries.
  - **`resources/css/app.css`:** check whether the Inertia root blade references it — `grep -n "@vite" resources/views/app.blade.php`. If `app.blade.php` does NOT include `resources/css/app.css` (it should use `app-inertia.css` + `app.tsx`), remove the `resources/css/app.css` input from vite AND `git rm resources/css/app.css`. If it IS referenced, keep it. State which you did.
  - Resulting inputs should be exactly: `resources/css/app-inertia.css`, `resources/js/app.tsx` (plus `app.css` only if the root blade uses it).

- [ ] **Step 3: Verify + commit**

`ddev exec pnpm run build` → succeeds. `ddev exec pnpm run test` → green (JS suite unchanged: qr-print engine + scanner-camera-error tests still pass).
```bash
git add resources/js/plugins vite.config.ts
# include `git rm`'d files + (if removed) resources/css/app.css
git commit -m "chore(cutover): remove Alpine QR/scanner glue + Filament vite entries/assets"
```

---

### Task 5: Repo-wide Pint (fold-in)

**Files (modify):** whatever `pint` reformats (the ~30 pre-existing Specs 1–5 files).

- [ ] **Step 1:** `ddev exec ./vendor/bin/pint` (auto-fix across the repo).
- [ ] **Step 2:** Confirm behavior-preserving: `ddev exec ./vendor/bin/phpunit` green, `ddev exec pnpm run build` succeeds. Then `ddev exec ./vendor/bin/pint --test` → clean.
- [ ] **Step 3: Commit** (formatting only, no functional change):
```bash
git add -A
git commit -m "style: apply pint across repo (fix pre-existing CI lint failures)"
```

---

### Task 6: Final verification

- [ ] **Step 1:** `ddev exec ./vendor/bin/phpunit` → green.
- [ ] **Step 2:** `ddev exec pnpm run test` → green.
- [ ] **Step 3:** `ddev exec pnpm run build` → succeeds.
- [ ] **Step 4:** `ddev exec ./vendor/bin/pint --test` → clean (repo-wide).
- [ ] **Step 5: No stray Filament/Livewire refs** —
```bash
grep -rn "Filament\\\\\|Livewire\\\\\|filament\\." app resources routes config bootstrap tests || echo "clean"
```
must print `clean` (ignore matches inside `vendor/`). Also `grep -c '"name": "filament/' composer.lock` → 0.
- [ ] **Step 6: Boot smoke** — `ddev launch` (or curl): `/` → 302 to `/app`; `/app` (guest) → login; log in → dashboard; spot-check assets list (row click + print modal), handovers, a report (filters + PDF), settings (a save), the topbar scan dialog, and the QR generator. Confirm no 500s / class-not-found.
- [ ] **Step 7:** Commit anything outstanding.

---

## Self-Review Notes

- **Ordering keeps green:** delete Filament code first (packages still present so enum/User `implements` still resolve) → decouple enums/User (now nothing references Filament) → remove packages (safe) → drop JS glue → pint → verify. Each task boots + passes.
- **Tags risk handled:** `spatie/laravel-tags` promoted to a direct dep in the same task the Filament tags plugin is removed; Task 3 Step 3 asserts tags still work.
- **Kept vs removed:** the reused domain (models/services/settings/reports/mails/pdf blades/qr-print engine/scanner-camera-error) is never touched; only Filament/Alpine/Livewire glue + the old QR generator are deleted.
- **Route flip:** `/` → `/app`; `qg` removed; Microsoft auth repointed to `/app` + `app.login`; `RootRedirectTest` covers the root.
- **CI:** the `filament:upgrade` composer script is removed (else `composer install` breaks); repo-wide Pint makes the `lint` gate pass; stale CI comments cleaned.
- **Deferred:** promoting `/app` → `/`; MFA; dropping unused User MFA columns; the Employee/User split.

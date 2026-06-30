# Storage (S3) Settings Page — Design

**Date:** 2026-06-30
**Branch:** new-features2
**Status:** Approved for planning

## Goal

Add a "Storage" settings page under the existing Settings cluster that lets an
administrator configure S3 (or any S3-compatible provider) as the application's
file storage backend, with a connection test and a safe fallback for fresh
installs.

## Decisions

- **Scope:** S3 is intended as the storage for **all** app file storage. When a
  valid S3 configuration is saved, the default filesystem disk (`filesystems.default`)
  is switched to `s3`.
- **Providers:** Support **S3-compatible** providers (MinIO, Cloudflare R2,
  DigitalOcean Spaces, Hetzner, …), not only genuine AWS. This requires a custom
  `endpoint` and a `use_path_style_endpoint` toggle.
- **Test action:** Provide a "Test connection" header action that round-trips a
  temp object (put → get → delete) and reports success/failure.
- **Fallback:** "Fall back to local until set." If the S3 configuration is
  incomplete, the default disk stays `local`; it switches to `s3` only when the
  config is complete. This keeps fresh installs working despite the S3-only intent.

## Architecture

This feature follows the established settings pattern in the codebase:

- Spatie `Settings` class (cf. `App\Settings\MailSettings`)
- Filament `SettingsPage` in the Settings cluster (cf. `ManageMailSettings`)
- Runtime application via `App\Support\ApplySettings`, already invoked per-request
  by the `App\Http\Middleware\ApplyRuntimeSettings` middleware
- Encrypted secret handling and a "test" header action, mirroring the Mail page
- German translations in `lang/de/settings.php` (only `de` locale exists today)

The `s3` disk already exists in `config/filesystems.php` with `key`, `secret`,
`region`, `bucket`, `url`, `endpoint`, and `use_path_style_endpoint` keys, so
`ApplySettings` only needs to overwrite those config values at runtime.

## Components

### 1. `App\Settings\StorageSettings`

Spatie settings class, `group()` returns `'storage'`.

| Field | Type | Notes |
|---|---|---|
| `key` | `?string` | Access key ID |
| `secret` | `?string` | **encrypted** |
| `region` | `?string` | default `us-east-1` |
| `bucket` | `?string` | |
| `endpoint` | `?string` | S3-compatible providers only; blank = real AWS |
| `use_path_style_endpoint` | `bool` | default `false` |
| `url` | `?string` | optional public/CDN base URL |

- `encrypted()` returns `['secret']`.
- Add a helper `isConfigured(): bool` returning `filled($key) && filled($secret) && filled($bucket)`.
  This is the single source of truth for the fallback rule.

### 2. Settings migration — `database/settings/<ts>_create_storage_settings.php`

Seeds defaults from existing env so current config carries over:

- `storage.key` ← `AWS_ACCESS_KEY_ID`
- `storage.secret` ← `addEncrypted(AWS_SECRET_ACCESS_KEY)`
- `storage.region` ← `AWS_DEFAULT_REGION` (default `us-east-1`)
- `storage.bucket` ← `AWS_BUCKET`
- `storage.endpoint` ← `AWS_ENDPOINT`
- `storage.use_path_style_endpoint` ← `(bool) AWS_USE_PATH_STYLE_ENDPOINT` (default `false`)
- `storage.url` ← `AWS_URL`

### 3. `App\Filament\App\Clusters\Settings\Pages\ManageStorageSettings`

A `SettingsPage` modeled on `ManageMailSettings`:

- `protected static string $settings = StorageSettings::class;`
- `protected static ?string $cluster = Settings::class;`
- Navigation icon `Heroicon::OutlinedCircleStack`; label/title via `settings.storage.nav` / `settings.storage.title`; a `navigationSort` next to the other settings pages.
- One Section with the fields above.
  - `secret`: `->password()->revealable()->dehydrated(fn (?string $s): bool => filled($s))`.
  - `endpoint` + `use_path_style_endpoint`: shown with helper text noting they apply only to S3-compatible providers.
  - `url`: helper text noting it's an optional public/CDN base URL.
- `mutateFormDataBeforeFill()` nulls `secret` so the stored secret never reaches the browser (matches the Mail page's `$secretFields` approach).
- `getSavedNotification()` with the `suppressSavedNotification` flag, copied from the Mail page so the test action can save quietly.

### 4. Test connection header action

Mirrors the Mail page's `sendTest`:

1. Set `suppressSavedNotification = true`, call `$this->save()`, reset the flag in a `finally`.
2. `app(ApplySettings::class)()` to apply the just-saved config.
3. Round-trip on the `s3` disk:
   `Storage::disk('s3')->put($probeKey, $bytes)` → `get($probeKey)` (assert it matches) → `delete($probeKey)`.
   Use a unique probe key (e.g. `inventorix-connection-test-<random>.txt`).
4. Success → success `Notification`; any `Throwable` → persistent danger `Notification` with `$e->getMessage()`.

### 5. `App\Support\ApplySettings::applyStorage(StorageSettings $s)`

Add to `__invoke()` after the existing appliers.

- `$s->refresh()` (stale-worker safety, like the other appliers).
- Write the `s3` disk config:
  ```php
  config([
      'filesystems.disks.s3.key' => $s->key,
      'filesystems.disks.s3.secret' => $s->secret,
      'filesystems.disks.s3.region' => $s->region,
      'filesystems.disks.s3.bucket' => $s->bucket,
      'filesystems.disks.s3.url' => $s->url,
      'filesystems.disks.s3.endpoint' => $s->endpoint,
      'filesystems.disks.s3.use_path_style_endpoint' => $s->use_path_style_endpoint,
  ]);
  ```
- **Fallback rule:** `if ($s->isConfigured()) { config(['filesystems.default' => 's3']); }`
  Otherwise leave the default untouched (stays `local`).

### 6. Translations

Add a `storage` block to `lang/de/settings.php`: `nav`, `title`, `section.*`,
`field.*` (+ helper texts), and `test.*` keys, consistent with the existing
`mail` block.

## Testing

Feature tests:

- Settings persist through the page save.
- `secret` is stored encrypted and is **never** present in the form data sent to
  the browser (nulled on fill).
- A blank `secret` submit keeps the previously stored secret (dehydration guard).
- `ApplySettings` sets `filesystems.default = 's3'` when the config is complete.
- `ApplySettings` leaves `filesystems.default = 'local'` when the config is
  incomplete (e.g. missing bucket).
- `ApplySettings` writes the `s3` disk config values (endpoint/path-style included).

## Out of Scope (follow-up)

- **Migrating existing local files to S3.** Switching the default disk does not
  move already-stored attachments; old files would 404 until moved. Treated as a
  separate follow-up (dev/fresh data assumed for now).
- Per-disk visibility/ACL configuration beyond Laravel's `s3` disk defaults.

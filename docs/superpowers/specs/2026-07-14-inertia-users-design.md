# Inertia Migration — Spec 3: Users

**Date:** 2026-07-14
**Status:** Approved (design)
**Part of:** FilamentPHP → React/Inertia migration (Spec 3 of 9)
**Depends on:** Spec 1 (foundation: shell, `TableQuery`, `DataTable`, form kit, session auth) and Spec 2 (`SelectField`, `filterable`). Filament remains at `/app-old`.

## Background

The Users admin is next. Investigating the code corrected a roadmap assumption:
**this app has no roles or permissions** — no `spatie/permission`, no role column,
no `UserPolicy`. The only authorization is the `login_enabled` gate (panel
access). So Spec 3 is Users CRUD + the login/identity fields + a
password-setup/reset flow, not a roles system.

User columns: `id` (uuid), `name` (display name), `firstname`, `lastname`,
`email` (nullable, unique), `password` (nullable), `login_enabled` (bool,
default false), `entra_id` (nullable, unique — SSO, system-managed),
`remember_token`, Filament MFA fields (deferred), timestamps. `User` has
`HasUuids`, the `login_enabled` gate, and `assets(): hasMany(Asset, 'owner_id')`.

Filament's form quirks being intentionally changed: `password` was a create-only
plaintext field; the "Assets" tab (marked `required()`) assigned owned assets
inline. The rebuild replaces manual passwords with a reset-email flow and makes
assets read-only here (assignment moves to the Asset `owner` select in Spec 4).

## Locked decisions

- **No manual passwords.** Create with `login_enabled` → set a random password
  (auto-hashed) + send a reset/setup email. Edit → a "Send password reset email"
  action. Build the `/app` reset-password page (the emailed link's target) + rely
  on Laravel's default `ResetPassword` notification (URL repointed to `/app`).
  **No** self-service "forgot password" link on the login screen (deferred).
- **Assets read-only** on the user (count + list); assignment via the Asset
  `owner` select in Spec 4.
- **Self-guards** (new): cannot delete your own account; cannot disable your own
  `login_enabled`.
- **`name`** display value auto-derived server-side (`firstname.' '.lastname`) on
  create; editable on edit with a "regenerate from names" button.
- Index adds a `login_enabled` badge + `assets_count` beyond Filament's columns.
- Reset email uses Laravel's default notification (branded theming = later polish).
- No policy (gate on `auth` + self-guards); no roles. PHPUnit; ddev; pnpm.
- **Authorization:** any `login_enabled` user can manage all users (including
  triggering resets for others) — intentional, since the app has no roles;
  `UserRequest::authorize()` is a blanket `true`.

## Goals

- Users fully usable at `/app` (list/create/edit/delete), added to the sidebar,
  with `/app-old` Filament intact.
- Password-setup/reset flow working end-to-end: create/edit trigger a reset
  email whose link opens a working `/app/reset-password/{token}` page that sets
  the password.
- Self-guards prevent self-lockout / self-deletion.
- Form kit gains `SwitchField` + `PasswordField`.

## Non-goals

- MFA management; self-service forgot-password on login; assets *assignment* from
  the user side; roles/permissions (none exist); in-session password change
  (distinct from the reset flow); branded reset-email template.
- Any change to Filament, other resources, or DB schema.

## Design

### Password-setup / reset flow

- **URL repointing:** in `AppServiceProvider::boot()`,
  `ResetPassword::createUrlUsing(fn (User $user, string $token) => url('/app/reset-password/'.$token.'?email='.urlencode($user->email)))`
  so the default notification links into the new app.
- **Guest routes** (inside the `/app` group's `guest` section, next to login):
  - `GET /app/reset-password/{token}` → `PasswordResetController@edit` → Inertia
    page `auth/reset-password` with `{ token, email }` (email from query).
  - `POST /app/reset-password` → `PasswordResetController@update`: validates
    `token`, `email`, `password` (`required|confirmed|min:8`); calls
    `Password::reset(...)` setting the new password (+ `setRememberToken`); on
    success redirects to `/app/login` with a success flash; on failure returns a
    validation error on `email`.
- **Admin-triggered send:** `POST /app/users/{user}/send-reset` (auth), named
  `app.users.send-reset` → `Password::sendResetLink(['email' => $user->email])`,
  guarded so it only fires for users that have an `email`; flashes success.
- **On create** (see controller): if `login_enabled`, set
  `password = Str::password()` (model cast hashes it) then call the same
  send-reset logic.
- Uses the existing `password_reset_tokens` table and the app's configured
  mailer (Resend/Postmark). Default `ResetPassword` notification, no custom
  template.

### `UserController` (App)

`Route::resource('users', UserController::class)->except('show')` inside the
authenticated `/app` group (names `app.users.*`), plus the `send-reset` POST.

- **index:** `TableQuery::for(User::query()->withCount('assets'), $request)`
  `->searchable(['name','firstname','lastname','email'])`
  `->sortable(['name','firstname','lastname','email','login_enabled','assets_count'])`
  `->paginate()`; rows transformed to
  `{ id, name, firstname, lastname, email, login_enabled, assets_count }`.
- **create/store:** `UserRequest` validated data; derive
  `name = trim(firstname.' '.lastname)`; if `login_enabled`, set a random hashed
  password and send the reset link. Redirect to index with success.
- **edit:** returns `user: { id, firstname, lastname, name, email, login_enabled }`,
  `isSelf: bool` (=== auth id), and `assets: [{ id, label }]` (read-only,
  label `"(Manufacturer) Model"`), + `assetsCount`.
- **update:** `UserRequest`; re-derive `name` only if not explicitly provided;
  apply self-guards (below). Redirect with success.
- **destroy:** self-guard, then delete. Redirect with success.
- **sendReset(User $user):** send reset link; flash.

### Validation (`UserRequest`)

- `firstname` = `required|string|max:255`
- `lastname` = `required|string|max:255`
- `name` = `nullable|string|max:255` (create derives it; edit may submit it)
- `login_enabled` = `boolean`
- `email`: `required_if:login_enabled,true`, `nullable`, `email`, `max:255`, and
  a uniqueness rule built with `Rule::unique('users', 'email')->ignore($this->route('user'))`
  so it ignores the current user on update (and ignores nothing on create).
- `authorize()` returns `true`.

### Self-guards

- **destroy:** `abort_if($user->is(request()->user()), 403)` (or a flashed error
  redirect) — cannot delete self.
- **update:** if `$user->is(request()->user())` and the request sets
  `login_enabled` to false while it is currently true, reject with a validation
  error on `login_enabled` ("You cannot disable your own login."). Enforced in the
  controller (it has both the target user and the auth user).
- The React edit page also disables the `login_enabled` switch + hides the delete
  action when `isSelf`, but the server is the source of truth.

### Frontend

- **Pages** under `resources/js/pages/users/`:
  - `index.tsx` — `DataTable` (columns: Name, First, Last, Email, Login [Badge
    yes/no], Assets [Badge count], row actions), `sortable` on the qualified
    columns; "New user" button.
  - `user-form.tsx` — shared: `TextField` firstname/lastname; on edit a `name`
    `TextField` + a "Regenerate" button (sets name = first+last client-side);
    `SwitchField` `login_enabled`; `email` `TextField` shown only when
    `login_enabled` (validation still server-authoritative). No password field.
  - `create.tsx`, `edit.tsx` — wrap the form; `edit.tsx` also renders the
    read-only assets list and the "Send password reset email" button
    (`router.post('/app/users/{id}/send-reset')`) and respects `isSelf`
    (disabled self-login switch, no self-delete).
  - `auth/reset-password.tsx` — guest page: hidden `token`/`email`, `PasswordField`
    (password) + `PasswordField` (password_confirmation), submit to
    `/app/reset-password`.
- **Nav:** add Users to the sidebar. Since it is not "Inventory", introduce an
  **"Administration"** group in `navGroups` with a Users entry (icon `Users`).
- **Form kit additions:**
  - `SwitchField` — `{ id, label, checked, onChange, error?, description? }` using
    shadcn `Switch` + `Label` + `FormError`.
  - `PasswordField` — `{ id, label, value, onChange, error?, required?, autoFocus? }`
    using an `Input type="password"` + `FormError` (mirrors `TextField`).

### Testing

- **PHPUnit** (`UserControllerTest`, `PasswordResetTest`), with `Notification::fake()`:
  - index lists users with `assets_count` + `login_enabled`; search/sort.
  - store: `name` derived from first+last; with `login_enabled` → password set
    (not null) **and** a reset notification sent to the user; without
    `login_enabled` → email may be null, no notification.
  - validation: `email` `required_if` login_enabled; `unique` (and ignores self
    on update); firstname/lastname required.
  - update: name editable; **self-guard** — disabling own login rejected
    (`assertSessionHasErrors('login_enabled')`, still enabled in DB); editing
    another user's login is fine.
  - destroy: **self-guard** — deleting self blocked (403/redirect, user still
    exists); deleting another user works.
  - send-reset: dispatches the reset notification for a user with an email.
  - reset flow: a valid token + matching email sets the new password (login works
    after); an invalid/expired token is rejected with an error and leaves the
    password unchanged.
  - auth: unauthenticated `/app/users` redirects; `/app/reset-password/{token}`
    is reachable while unauthenticated (guest).
- **Vitest:** `SwitchField` (toggles, shows error), `PasswordField` (renders,
  masks, shows error).

## Open questions / follow-ups (not this spec)

- Branded reset-email template; self-service forgot-password link on login.
- MFA management in the new UI.
- Assets assignment from the user side (handled from the Asset `owner` select in
  Spec 4).

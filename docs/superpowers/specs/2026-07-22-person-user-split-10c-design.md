# Person / User Separation — Spec 10c: Users Login-Only

**Date:** 2026-07-22
**Status:** Approved (design)
**Part of:** Person/User separation (sub-spec **10c of 10a–10c**, final)
**Depends on:** 10a (Person + `users.person_id`), 10b (People directory).

## Background

10a/10b moved identity + ownership to `Person`. `users` still carries the now-redundant
`firstname/lastname/name` columns and the Users UI still edits them. 10c finishes the
split: `users` becomes a **login account** (email/password/login_enabled/entra_id/MFA)
**linked to a Person** via the existing nullable `person_id`; the name columns are dropped
and the display name is derived from the linked person.

**Locked decisions:** person is attached by **selecting an existing Person** (nullable
dropdown; login-only accounts leave it unset); the **owned-assets list is removed** from
the user edit page (People detail covers it).

## Goals

- Users UI manages login accounts only (email, login_enabled, linked person); the `users`
  name columns are gone; the topbar display name resolves from the person (fallback email);
  suite green on SQLite + MariaDB. Person/User separation complete.

## Non-goals

- Inline person-create from the user form; searchable-async person picker (a plain nullable
  select is fine for now); removing the deferred User MFA columns; any People changes.

## Design

### Migration

Drop `firstname`, `lastname`, `name` from `users` (keep `email`, `password`,
`login_enabled`, `person_id`, `entra_id`, MFA cols, `remember_token`). SQLite rebuilds the
table; verify on SQLite (tests) + ddev MariaDB. `down()` re-adds the three columns as
nullable strings (no backfill needed for a dev rollback).

### `UserRequest`

- Remove `firstname`, `lastname`, `name`.
- Add `person_id`: `['nullable','uuid','exists:people,id']`.
- Keep `login_enabled` (`boolean`) and `email` (`required_if:login_enabled,true`, `nullable`,
  `email`, `max:255`, `Rule::unique('users','email')->ignore($this->route('user'))`).

### `UserController`

- **index:** `User::query()->leftJoin('people','people.id','=','users.person_id')->select('users.*','people.name as person_name')->addSelect(['assets_count' => Asset::selectRaw('count(*)')->whereColumn('owner_id','users.person_id')])`, through `TableQuery::searchable(['person_name','users.email'])->sortable(['person_name','users.email','users.login_enabled','assets_count'])`. Rows: `{ id, name: person_name, email, login_enabled, assets_count }`.
- **create:** render `users/create` with `personOptions` (`Person::orderBy('name')` → `{value,label}`).
- **store:** `$data = validated()`; if `login_enabled` → `$data['password'] = Str::password()`; `User::create($data)` (no name derivation); if login_enabled && email → `Password::sendResetLink`.
- **edit:** render `users/edit` with `user {id, email, login_enabled, person_id}`, `personOptions`, `isSelf`. **No** owned-assets list.
- **update:** self-guard (can't disable own login) unchanged; no name derivation; `$user->update($data)`.
- **destroy / sendReset:** unchanged.

### `HandleInertiaRequests`

`auth.user` → build explicitly: `['id' => $u->id, 'name' => $u->person?->name ?? $u->email, 'email' => $u->email]` (drop firstname/lastname; access `$u->person` — lazy-loads).

### `UserFactory`

Drop `firstname/lastname/name`; keep `email`, `password`, `login_enabled`, `remember_token`;
`person_id` defaults to **null** (login-only) so people-count assertions stay clean — tests
that need a linked person set `person_id` explicitly.

### Frontend

- **`users/index.tsx`:** columns **Person** (name) / **Email** / **Login enabled** / **Assets**
  (drop first/last-name columns); clickable rows → edit; delete confirm uses the person/email
  label. `Row = { id, name, email, login_enabled, assets_count }`.
- **`users/user-form.tsx`:** a nullable **`SelectField`** person picker (from `personOptions`) +
  `email` `TextField` + `login_enabled` `SwitchField`; keep the self-guard note. No name fields.
- **`users/create.tsx` / `edit.tsx`:** pass `personOptions`; edit drops the owned-assets block.
- **`types/index.d.ts`:** drop `firstname/lastname` from the shared auth-user type (keep `id`,
  `name`, `email`); `UserMenu` already uses `user.name` (now person-or-email) — unchanged.

## Testing

- **PHPUnit `UserControllerTest`** (rewritten): index lists login accounts with the linked
  person name + assets_count; store creates a login (email required when login_enabled;
  password + reset link sent; `person_id` persisted; login-disabled account needs no email);
  duplicate email rejected; `person_id` must exist; update changes email/login_enabled/person
  without any name field; **self-guard** (can't disable/delete own login); `sendReset`.
- **PHPUnit auth-prop test:** an authed request's shared `auth.user.name` = the linked
  person's name, and falls back to the email when `person_id` is null.
- **`UserFactorySmokeTest`:** updated to the login-only factory shape (no name fields).
- **Vitest:** `users/index` renders a row (person name + email + assets); `user-form` submits
  `{person_id, email, login_enabled}` (no name fields).
- Migration verified on SQLite + MariaDB; `pint --test` clean.

## Open questions / follow-ups (later)

- Inline "create person" from the user form; a searchable/async person picker if the
  directory grows large; dropping the unused User MFA columns; re-implementing MFA.
- **Person/User separation is complete after this sub-spec.**

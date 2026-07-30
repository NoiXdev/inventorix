# Person / User Separation — Spec 10a: Backend & Data

**Date:** 2026-07-22
**Status:** Approved (design)
**Part of:** Person/User separation (sub-spec **10a of 10a–10c**)
**Depends on:** the completed Inertia migration (Specs 1–9). Follows: 10b (People UI + pickers), 10c (Users login-only UI).

## Background

`User` currently fuses a **login account** with a **person's identity**:
`{id, name, firstname, lastname, email, password, login_enabled, entra_id, MFA…}`,
and `asset.owner_id` / handover recipient / `handover_asset.owner_from/to` / the
reports' "employee" concept all point at `users`. This spec extracts the identity
into a new **`Person`** model and slims `User` to login only.

**Locked decisions:** entity is **`Person`** (table `people`); a nullable
`users.person_id` link; audit FKs stay on `User`; **same-UUID backfill** so the
ownership FKs re-point with zero value migration. Login (`users.email`) is
untouched. This sub-spec is **backend + data only** — the UIs come in 10b/10c.

**Verified anchors:**
- Test DB is **SQLite `:memory:`** (prod/dev is MariaDB) — migrations that
  re-point FKs / drop / rename columns must run on **both**.
- FKs are `foreignUuid(...)->constrained('users')->nullOnDelete()`:
  `assets.owner_id`, `handovers.recipient_user_id`, `handover_asset.owner_from_id`,
  `handover_asset.owner_to_id`. Audit: `handovers.created_by`,
  `attachments.uploaded_by` (stay → users).
- `HandoverData.recipientUserId`; `HandoverService` writes `recipient_user_id`,
  `owner_from_id = asset.owner_id`, `owner_to_id = recipientUserId` (issue/lend),
  `assets.owner_id = ownerTo`.
- `HandleInertiaRequests` shares `user->only('id','name','firstname','lastname','email')`.
- Factories: `AssetFactory.owner_id = User::factory()`; `HandoverFactory.recipient_user_id = User::factory()`, `created_by = User::factory()`; `UserFactory` sets `firstname/lastname/name`.

## Goals

- A `Person` model + `people` table holds identity; `asset` owner, handover
  recipient, the handover pivot, and reports reference `Person`; `User` is
  login-only with a nullable `person_id`; existing data is backfilled losslessly;
  the full suite is green on SQLite. No UI yet (10b/10c).

## Non-goals

- People/Users UI (10b/10c). Removing the deferred User MFA columns. Changing
  login/auth. Any new person attributes beyond the moved ones (department etc. later).

## Design

### Data model + migrations (ordered)

1. **`people`**: `id (uuid pk)`, `firstname`, `lastname`, `name`, `email (nullable)`,
   `timestamps`. **Backfill**: `INSERT INTO people (id, firstname, lastname, name, email, created_at, updated_at) SELECT id, firstname, lastname, name, email, created_at, updated_at FROM users` (same UUID). On a fresh test DB this is a harmless no-op (no users yet).
2. **`users.person_id`**: add `foreignUuid('person_id')->nullable()->constrained('people')->nullOnDelete()`; **backfill** `UPDATE users SET person_id = id`; then **drop** `firstname`, `lastname`, `name` from `users` (keep `email`, `password`, `login_enabled`, `entra_id`, MFA cols, `remember_token`).
3. **Re-point `assets.owner_id`** from `users` → `people`: `dropForeign(['owner_id'])` then `foreign('owner_id')->references('id')->on('people')->nullOnDelete()`. Values already valid (people.id = old user.id).
4. **Handover**: rename `handovers.recipient_user_id` → `recipient_person_id` and re-point its FK → `people`; re-point `handover_asset.owner_from_id` and `owner_to_id` FKs → `people`. (`created_by` stays → users.)

Each concern is its own migration file. **SQLite caveat:** dropForeign / rename /
drop-column trigger a table rebuild on SQLite — the implementer must run
`migrate:fresh` on the SQLite test DB **and** on the live ddev MariaDB and confirm
both succeed (this is the project's known driver-mismatch risk; see the
uuid-migrations gotcha). Each migration needs a working `down()`.

### Models

- **`Person`** (`App\Models\Person`, `HasUuids`, `HasFactory`): `#[Fillable(['firstname','lastname','name','email'])]`; `assets(): HasMany` (`Asset::class, 'owner_id'`).
- **`User`**: remove `firstname/lastname/name` from `#[Fillable]` (add `person_id`); **remove** `assets()`; add `person(): BelongsTo` (`Person::class, 'person_id'`). Keep casts/hidden/login.
- **`Asset`**: `owner()` → `belongsTo(Person::class, 'owner_id')` (was `User`).
- **`Handover`**: `recipientUser()` → `recipientPerson(): belongsTo(Person::class, 'recipient_person_id')`; `createdBy()` stays → `User`. Update the `#[Fillable]`/`$fillable` entry `recipient_user_id` → `recipient_person_id`.

### Domain wiring

- **`HandoverData`**: `recipientUserId` → `recipientPersonId`.
- **`HandoverService::commit`**: write `recipient_person_id`; `owner_to_id`/`assets.owner_id` = `recipientPersonId`; `owner_from_id = asset.owner_id` (now a person id) — all consistent since ids are people ids.
- **`HandoverRequest`**: `recipient_user_id` → `recipient_person_id`, rule `exists:users,id` → `exists:people,id` (keep `required_if:recipient_kind,internal`).
- **`HandoverController`**: `store()` builds `HandoverData` with `recipientPersonId`; `create()`'s `userOptions` → `personOptions` (query `Person`); `index()`/`show()` recipient display via `recipientPerson`. (The React wizard rename lands in 10b; this sub-spec keeps the controller prop key working — expose it as `personOptions` and update the create page's prop consumption minimally so tests/build pass, or keep the prop name `userOptions` if 10b will rename — pick one and be consistent; recommended: rename to `personOptions` now and update the wizard's prop name in the same change.)
- **`AssetRequest`**: `owner_id` rule `exists:users,id` → `exists:people,id`.
- **`AssetController` / `AssetTableQuery`**: the `owner_name` join and `ownerOptions` switch from `users` to `people`. `Asset::with('owner')` now loads a `Person`.
- **Reports** (`GuaranteeStatusReport`, `AssetsPerEmployeeReport`, `AssetValueReport`): the "employees" filter options + any `User` query → `Person`; grouping by `owner_id` unchanged (ids preserved); `owner?->name` now a Person. German "Mitarbeiter" labels unchanged.
- **`HandleInertiaRequests`**: shared `auth.user` → `['id' => u->id, 'name' => u->person?->name ?? u->email, 'email' => u->email]` (drop firstname/lastname; derive display name from the linked person, fallback to email).

### Factories

- **`PersonFactory`**: firstname/lastname/name/email (moved from UserFactory).
- **`AssetFactory`**: `owner_id => Person::factory()`.
- **`HandoverFactory`**: `recipient_person_id => Person::factory()`; `created_by => User::factory()`.
- **`UserFactory`**: drop firstname/lastname/name; keep email/password/login_enabled; add `person_id => Person::factory()` (so a factory user has a linked person by default; tests needing a login-only user can set `person_id => null`).

## Testing

- **Migration/backfill (PHPUnit)**: a dedicated test seeding via factories confirms the schema (people table, users.person_id, renamed handover column) and that FKs resolve. (Backfill-from-existing-users is exercised implicitly; add a focused test that inserts a legacy-shaped row set only if practical on SQLite — otherwise assert the schema + relations.)
- **Relations**: `Person::assets()`, `Asset::owner()` → Person, `User::person()`, `Handover::recipientPerson()`.
- **Handover flow** (existing `HandoverControllerTest`, adapted): issue/return still flips `asset.owner_id` to/from the person; pivot `owner_from_id`/`owner_to_id` are person ids; `recipient_person_id` set; validation `exists:people`.
- **Assets**: owner options/filter/import/export reference people; `AssetControllerTest`/import-export tests updated (owner is a Person).
- **Reports**: employee filter/grouping still works against people.
- **Auth prop**: `HandleInertiaRequests` returns the person's name (or email fallback) — a small feature test or assertion via an existing authed request.
- Update all affected factories + existing tests that created a `User` as an asset owner / handover recipient to create a `Person` instead. Full suite green on SQLite; `pint --test` clean.

## Open questions / follow-ups (later)

- 10b: People CRUD UI + wire asset-owner / handover-recipient / report pickers to Person.
- 10c: Users UI login-only + person link selector.
- Later: `department` and other person attributes; drop unused User MFA columns.

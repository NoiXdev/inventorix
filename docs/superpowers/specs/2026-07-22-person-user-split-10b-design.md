# Person / User Separation — Spec 10b: People Directory UI

**Date:** 2026-07-22
**Status:** Approved (design)
**Part of:** Person/User separation (sub-spec **10b of 10a–10c**)
**Depends on:** 10a (Person model + `people` table + ownership re-pointed). Follows: 10c (Users login-only UI + drop user name cols).

## Background

10a moved ownership to `Person` and switched the owner/recipient/report **pickers**
to Person-backed options (already wired in the UI). What's missing is a way to
**manage** people: a `/app/people` directory with per-person detail showing which
assets they own — the "who has what" view that motivated the split.

**Locked decisions:** CRUD **+ a per-person detail page** (owned assets); **block
delete** when a person still owns assets; **People under the Inventory** nav group;
`name` derived from `firstname + lastname` (no separate name input), mirroring the
old user form.

## Goals

- Manage people at `/app/people` (list/create/edit/delete) and view a person's
  owned assets on a detail page. Auth-only gate. `/app-old` is gone (post-cutover).

## Non-goals

- Users UI / dropping `users` name columns / auth-prop (10c). Department or other
  person attributes. Bulk actions. Changing the already-Person-backed pickers.

## Design

### Backend — `App\Http\Controllers\App\PersonController` (resource, names `app.people.*`)

- **index:** `TableQuery::for(Person::query()->withCount('assets'), $request)`
  `->searchable(['name','firstname','lastname','email'])->sortable(['name','email','assets_count'])->paginate()`;
  rows `{ id, name, firstname, lastname, email, assets_count }`.
- **create / store, edit / update:** validated by `PersonRequest`; set
  `name = trim($firstname.' '.$lastname)` before create/update (name is not a form field).
- **show:** `$person->loadCount('assets'); $person->load('assets.model.manufacturer','assets.place')`;
  render `people/show` with the person + an `assets` array
  (`{ id, model_name, serial_number, state, state_label, place_name }`), each row linking to the asset detail.
- **destroy:** if `$person->assets()->exists()` → redirect back with a validation
  error (`throw ValidationException::withMessages(['person' => "Reassign this person's assets before deleting."])` or `back()->with('error', …)`); else delete (a linked `users.person_id` nulls via the schema's `nullOnDelete`).

### Validation — `PersonRequest`

- `firstname`: required, string, max 255.
- `lastname`: required, string, max 255.
- `email`: nullable, email, max 255.
- `authorize()` true (auth middleware; no per-resource policy).

### Routes

`Route::resource('people', PersonController::class)` inside the authenticated `/app`
group (includes `show`, since we have a detail page).

### Frontend — `resources/js/pages/people/`

- **`index.tsx`** — `DataTable` columns: Name, Email, Assets (count badge) + row
  actions (view/edit/delete); `rowHref` → `/app/people/{id}` (detail); clickable rows
  (reuse the shared DataTable behavior). "New person" button.
- **`create.tsx`** + **`edit.tsx`** + **`person-form.tsx`** — form kit `TextField`s for
  firstname, lastname, email (email optional); Save/Cancel, mirroring `manufacturer-form`.
- **`show.tsx`** — header (name + email) + Edit/Delete buttons + an **owned-assets**
  `Table` (Model, Serial, State via `StateBadge`, Place), each row linking to
  `/app/assets/{id}`; empty state when the person owns nothing.
- **Nav** — add **People** to the Inventory group (lucide `Contact`), `match: p => p.startsWith('/app/people')`, placed after Assets.

### Reuse

`TableQuery`, `DataTable` (+ `rowHref`), form kit, `StateBadge`, `Card`, `Table`,
`Button`; the `manufacturers` CRUD as the structural model.

## Testing

- **PHPUnit `PersonControllerTest`**: index lists people with `assets_count`;
  store/update derive `name` from firstname+lastname and persist; validation
  (missing firstname/lastname, bad email) rejected; **destroy blocked** when the
  person owns assets (asset remains, error flashed) and **allowed** when they own
  none; `show` returns the person + their owned assets; guest redirected.
- **Vitest**: `people/index` renders rows + row link to detail; `person-form`
  submits firstname/lastname/email; `people/show` renders the owned-assets table +
  an empty state.

## Open questions / follow-ups (later)

- 10c: Users UI login-only (email/password/login_enabled + person link) + drop the
  `users` name columns + auth-prop display change.
- Later: department/other person fields; reassign-assets UI to ease deletion.

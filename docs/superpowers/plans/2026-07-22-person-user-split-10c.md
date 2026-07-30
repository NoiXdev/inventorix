# Person / User Separation — 10c (Users Login-Only) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `users` a login account linked to a `Person`: drop the `firstname/lastname/name` columns, edit only email/login_enabled/person on the Users UI, and derive the display name from the person. Finishes the Person/User split.

**Architecture:** One coordinated backend change (migration + request + controller + middleware + factory + test rewrite must land together — dropping the columns breaks the old name-based code/tests), then the frontend, then verification.

**Tech Stack:** Laravel 13/PHP 8.4, Inertia v2 + React 19 + TS, PHPUnit (SQLite tests, MariaDB dev/prod), Vitest. ddev; pnpm.

## Global Constraints

- ddev for all commands; **PHPUnit**; **Vitest** (`pnpm exec vitest run`; if `pnpm` missing `ddev exec corepack enable`). Migrations verified on **SQLite + MariaDB**.
- From 10a/10b (do NOT change): `Person`, `people`, `users.person_id` (nullable FK), `User::person()`. Audit FKs stay on users.
- `email` is the login identifier (auth unchanged). Auth-only gate. Pint is a CI gate. `@/` → `resources/js/*`. Commit trailer:
  `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`

---

### Task 1: Backend — drop user name columns, login-only UserController/Request/middleware/factory + tests

**Files:** create `database/migrations/2026_07_22_000005_drop_name_columns_from_users.php`; modify `app/Http/Requests/App/UserRequest.php`, `app/Http/Controllers/App/UserController.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Models/User.php` (fillable), `database/factories/UserFactory.php`; rewrite `tests/Feature/App/UserControllerTest.php`, fix `tests/Unit/UserFactorySmokeTest.php`; create placeholder-safe `users/create|edit` prop changes are Task 2 (keep the existing pages working — they still render name fields until Task 2, but the controller no longer sends them; that's fine, React tolerates missing props → treat as empty. To avoid a broken edit page mid-task, Task 1 may leave the React pages untouched; they'll show blank name fields until Task 2 replaces them — acceptable since the suite/build must still pass. If the build breaks on a missing prop type, Task 1 also trims the affected `Initial`/`Row` types minimally; prefer doing the full React rewrite in Task 2.)

This is one coordinated change — the migration + all name-reading code + the affected tests land together.

- [ ] **Step 1: Migration**

```php
<?php // database/migrations/2026_07_22_000005_drop_name_columns_from_users.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['firstname', 'lastname', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('name')->nullable();
        });
    }
};
```

- [ ] **Step 2: `User` model** — remove `firstname`, `lastname`, `name` from the `#[Fillable(...)]` attribute (keep `email`, `password`, `login_enabled`, `remember_token`, `entra_id`, `person_id`). Leave `person()`/casts/hidden.

- [ ] **Step 3: `UserRequest`**

```php
public function rules(): array
{
    return [
        'person_id' => ['nullable', 'uuid', 'exists:people,id'],
        'login_enabled' => ['boolean'],
        'email' => [
            'required_if:login_enabled,true', 'nullable', 'email', 'max:255',
            \Illuminate\Validation\Rule::unique('users', 'email')->ignore($this->route('user')),
        ],
    ];
}
```
(Drop the `firstname`/`lastname`/`name` rules. Keep `authorize(): true`.)

- [ ] **Step 4: `UserController`**

```php
public function index(Request $request): Response
{
    $query = User::query()
        ->leftJoin('people', 'people.id', '=', 'users.person_id')
        ->select('users.*', 'people.name as person_name')
        ->addSelect(['assets_count' => Asset::query()->selectRaw('count(*)')->whereColumn('owner_id', 'users.person_id')]);

    $users = TableQuery::for($query, $request)
        ->searchable(['person_name', 'users.email'])
        ->sortable(['person_name', 'users.email', 'users.login_enabled', 'assets_count'])
        ->paginate();

    $users->getCollection()->transform(fn (User $u) => [
        'id' => $u->id,
        'name' => $u->person_name,
        'email' => $u->email,
        'login_enabled' => $u->login_enabled,
        'assets_count' => (int) $u->assets_count,
    ]);

    return Inertia::render('users/index', [
        'users' => [
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ],
    ]);
}

public function create(): Response
{
    return Inertia::render('users/create', ['personOptions' => $this->personOptions()]);
}

public function store(UserRequest $request): RedirectResponse
{
    $data = $request->validated();
    $loginEnabled = (bool) ($data['login_enabled'] ?? false);
    if ($loginEnabled) {
        $data['password'] = Str::password();
    }

    $user = User::create($data);

    if ($loginEnabled && $user->email) {
        Password::sendResetLink(['email' => $user->email]);
    }

    return to_route('app.users.index')->with('success', 'User created.');
}

public function edit(Request $request, User $user): Response
{
    return Inertia::render('users/edit', [
        'user' => [
            'id' => $user->id,
            'email' => $user->email,
            'login_enabled' => $user->login_enabled,
            'person_id' => $user->person_id,
        ],
        'personOptions' => $this->personOptions(),
        'isSelf' => $user->is($request->user()),
    ]);
}

public function update(UserRequest $request, User $user): RedirectResponse
{
    $data = $request->validated();

    if ($user->is($request->user()) && ! (bool) ($data['login_enabled'] ?? false)) {
        throw ValidationException::withMessages(['login_enabled' => 'You cannot disable your own login.']);
    }

    $user->update($data);

    return to_route('app.users.index')->with('success', 'User updated.');
}

/** @return array<int, array{value: string, label: string}> */
private function personOptions(): array
{
    return \App\Models\Person::query()->orderBy('name')->get()
        ->map(fn (\App\Models\Person $p) => ['value' => $p->id, 'label' => $p->name])->all();
}
```
Keep `destroy()` and `sendReset()` as-is. Remove the now-unused owned-assets logic from `edit()` (and any `use` that becomes unused). Ensure `Asset`, `Str`, `Password`, `ValidationException`, `TableQuery` imports remain as needed.

- [ ] **Step 5: `HandleInertiaRequests`** — replace the `'user' => $request->user()?->only(...)` with:
```php
'user' => ($u = $request->user())
    ? ['id' => $u->id, 'name' => $u->person?->name ?? $u->email, 'email' => $u->email]
    : null,
```

- [ ] **Step 6: `UserFactory`** — drop `firstname/lastname/name`; keep email/password/login_enabled/remember_token; do NOT set `person_id` (defaults null). e.g.:
```php
public function definition(): array
{
    return [
        'email' => fake()->unique()->safeEmail(),
        'password' => static::$password ??= Hash::make('password'),
        'login_enabled' => true,
        'remember_token' => Str::random(10),
    ];
}
```

- [ ] **Step 7: Rewrite `UserControllerTest`** for login-only + person link. Cover: index lists a user with its linked person's name + assets_count (create a `Person`, a `User` with `person_id` = that person, and assets owned by the person); store with `login_enabled=true` requires email, persists `person_id`, sends a reset link (`Password::shouldReceive`/`Notification::fake` or assert the user exists + password set); store with `login_enabled=false` allows null email; duplicate email rejected; `person_id` must exist (`exists:people`); update changes email/login_enabled/person (no name); self-guard can't disable own login; can't delete self; `sendReset`. Remove all firstname/lastname/name assertions. Use `User::factory()->create(['login_enabled'=>true])` for the actor (person_id null is fine).

- [ ] **Step 8: Fix `tests/Unit/UserFactorySmokeTest.php`** — drop assertions on firstname/lastname/name; assert the login-only shape (email present, login_enabled, password hashed).

- [ ] **Step 9: Auth-prop test** — add (e.g. to a middleware/inertia test or `UserControllerTest`) an assertion that an authed page's shared `auth.user.name` equals the linked person's name, and equals the email when `person_id` is null. Example via any Inertia page:
```php
$person = Person::factory()->create(['name' => 'Ada Lovelace']);
$user = User::factory()->create(['person_id' => $person->id, 'login_enabled' => true]);
$this->actingAs($user)->get('/app')
    ->assertInertia(fn (Assert $p) => $p->where('auth.user.name', 'Ada Lovelace')->etc());

$loginOnly = User::factory()->create(['person_id' => null, 'email' => 'svc@x.de', 'login_enabled' => true]);
$this->actingAs($loginOnly)->get('/app')
    ->assertInertia(fn (Assert $p) => $p->where('auth.user.name', 'svc@x.de')->etc());
```

- [ ] **Step 10: Run + commit** — `ddev exec ./vendor/bin/phpunit` full green; MariaDB `migrate:fresh` clean; Pint clean on changed files.
```bash
git add database/migrations/2026_07_22_000005_* app/Http/Requests/App/UserRequest.php app/Http/Controllers/App/UserController.php app/Http/Middleware/HandleInertiaRequests.php app/Models/User.php database/factories/UserFactory.php tests/Feature/App/UserControllerTest.php tests/Unit/UserFactorySmokeTest.php
git commit -m "refactor(user): users are login-only + linked person; drop name columns"
```

Note on React during Task 1: the existing `users/index|edit|user-form` still reference name props. The controller now omits them (index sends `name` = person name, no firstname/lastname; edit sends no name). The React pages render blank/missing without crashing, and `pnpm run build` should still pass (missing optional data, not missing modules) — but if `tsc` errors on a now-absent prop, make the minimal type adjustment here and complete the real UI in Task 2. Prefer: run `ddev exec pnpm run build` at the end of Task 1; if red, do the smallest type fix to go green, else leave the UI for Task 2.

---

### Task 2: Frontend — Users index + login-only form

**Files:** modify `resources/js/pages/users/{index,create,edit,user-form}.tsx`, `resources/js/types/index.d.ts`; test `resources/js/pages/users/__tests__/*` (add/adjust).

- [ ] **Step 1: `types/index.d.ts`** — the shared auth user type: keep `{ id: string; name: string | null; email: string | null }`; drop `firstname`/`lastname`. (`UserMenu` uses `user.name`/`user.email` — unaffected.)

- [ ] **Step 2: `user-form.tsx`** — login-only:
```tsx
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { SelectField } from '@/components/form/select-field';
import { SwitchField } from '@/components/form/switch-field';

type Option = { value: string; label: string };
interface Initial { id: string; email: string | null; login_enabled: boolean; person_id: string | null }
interface Props { initial?: Initial; personOptions: Option[]; submitUrl: string; method: 'post' | 'put'; isSelf?: boolean }

export function UserForm({ initial, personOptions, submitUrl, method, isSelf }: Props) {
    const form = useForm({
        person_id: initial?.person_id ?? '',
        email: initial?.email ?? '',
        login_enabled: initial?.login_enabled ?? false,
    });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };

    return (
        <form onSubmit={submit} className="max-w-lg space-y-6">
            <SelectField id="person_id" label="Person" nullable options={personOptions}
                value={form.data.person_id} onChange={(v) => form.setData('person_id', v)} error={form.errors.person_id} />
            <TextField id="email" label="Login email" value={form.data.email} onChange={(v) => form.setData('email', v)} error={form.errors.email} />
            <SwitchField id="login_enabled" label="Login enabled" checked={form.data.login_enabled}
                onChange={(v) => form.setData('login_enabled', v)} error={form.errors.login_enabled}
                description={isSelf ? 'You cannot disable your own login.' : undefined} />
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
```

- [ ] **Step 3: `create.tsx` / `edit.tsx`** — pass `personOptions` (and `initial`/`isSelf` for edit); drop the owned-assets block from edit. e.g. edit:
```tsx
interface Props { user: { id: string; email: string | null; login_enabled: boolean; person_id: string | null }; personOptions: { value: string; label: string }[]; isSelf: boolean }
export default function EditUser({ user, personOptions, isSelf }: Props) {
    return (
        <AppLayout title="Edit user" breadcrumbs={[{ label: 'Users', href: '/app/users' }, { label: user.email ?? 'User' }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit user</h1>
            <UserForm initial={user} personOptions={personOptions} submitUrl={`/app/users/${user.id}`} method="put" isSelf={isSelf} />
        </AppLayout>
    );
}
```
(create mirrors with `submitUrl="/app/users"` method `post`, no `initial`/`isSelf`.)

- [ ] **Step 4: `index.tsx`** — columns **Person / Email / Login enabled / Assets**, drop first/last name, clickable rows → edit:
```tsx
interface Row { id: string; name: string | null; email: string | null; login_enabled: boolean; assets_count: number }
const columns: ColumnDef<Row>[] = [
    { id: 'person_name', accessorFn: (r) => r.name, header: 'Person', cell: ({ row }) => row.original.name ?? '—' },
    { id: 'users.email', accessorFn: (r) => r.email, header: 'Email', cell: ({ row }) => row.original.email ?? '—' },
    { id: 'users.login_enabled', accessorFn: (r) => r.login_enabled, header: 'Login', cell: ({ row }) => <Badge variant={row.original.login_enabled ? 'default' : 'secondary'}>{row.original.login_enabled ? 'Enabled' : 'Disabled'}</Badge> },
    { id: 'assets_count', accessorFn: (r) => r.assets_count, header: 'Assets', cell: ({ row }) => <Badge variant="secondary">{row.original.assets_count}</Badge> },
    { id: 'actions', header: '', cell: ({ row }) => (
        <div className="flex justify-end gap-1">
            <Button asChild variant="ghost" size="icon"><Link href={`/app/users/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
            <Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name ?? row.original.email}?`)) router.delete(`/app/users/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
        </div>
    ) },
];
```
Keep the "New user" link + `<DataTable … sortable={['person_name','users.email','users.login_enabled','assets_count']} rowHref={(r) => `/app/users/${r.id}/edit`} />` (baseUrl `/app/users`). Adjust the `Row` type + imports; drop `Eye` if there's no user show page (there isn't — `->except('show')`).

- [ ] **Step 5: Vitest** — `users/index` renders a row (person name + email + assets); `user-form` submits `{person_id, email, login_enabled}` with no name fields (mock `@inertiajs/react` `useForm` + layout as in sibling tests).

- [ ] **Step 6: Run + commit** — vitest + `pnpm run test` + `pnpm run build` green.
```bash
git add resources/js/pages/users resources/js/types/index.d.ts
git commit -m "feat(user): login-only Users UI (person link + email + login toggle)"
```

---

### Task 3: Final verification

- [ ] `ddev exec ./vendor/bin/phpunit` → green.
- [ ] `ddev exec pnpm run test` + `ddev exec pnpm run build` → green.
- [ ] `ddev exec ./vendor/bin/pint --test` → clean.
- [ ] MariaDB `ddev exec php artisan migrate:fresh` clean; confirm `users` has no `firstname/lastname/name` and keeps `email/password/login_enabled/person_id/entra_id`.
- [ ] `grep -rIn "firstname\|lastname" app resources/js | grep -iv Person` → only legitimate (Person) hits; no user-name references remain.
- [ ] Manual: `/app/users` lists login accounts (person name + email + login/assets); create a user linking a person (login-enabled sends reset); edit changes person/email/login; the topbar shows the logged-in user's person name (or email if unlinked); self can't disable/delete own login.
- [ ] Commit anything outstanding.

---

## Self-Review Notes

- **Spec coverage:** migration + request + controller + middleware + factory + test rewrite → Task 1; index/form/create/edit/types/vitest → Task 2; verification → Task 3.
- **Coordinated Task 1:** dropping the columns + all name-reading code + affected tests land together (no red intermediate). The React pages are finished in Task 2; Task 1 keeps the build green (minimal type touch if needed).
- **Auth-prop:** display name = `person?->name ?? email`; tested both branches. `UserMenu` unchanged (reads `user.name`).
- **Factory:** `person_id` defaults null so people-count tests stay clean.
- **Both engines:** migration verified on SQLite (suite) + MariaDB.
- **Completes** the Person/User separation (10a data, 10b People UI, 10c Users login-only).

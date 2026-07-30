# Inertia Migration — Users Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate the Users admin to `/app` — CRUD with login/identity fields, a password-setup/reset flow (random password + reset email on create, resend action, and a new `/app/reset-password` page), and self-guards — reusing and extending the Spec 1/2 kit.

**Architecture:** Reuse `TableQuery`, `DataTable`, the CRUD form kit, and the resource-controller pattern. Passwords are never typed in: creating a login-enabled user sets a random hashed password and emails a reset link (Laravel's password broker, repointed into `/app`). Two new form-kit fields (`SwitchField`, `PasswordField`). No roles/policies exist in this app; authorization is the `auth` middleware plus controller-level self-guards.

**Tech Stack:** Laravel 13/PHP 8.4, Inertia v2 + React 19, TypeScript, Tailwind 4, shadcn/ui, PHPUnit, Vitest. All commands via `ddev exec …`; pnpm.

## Global Constraints

- All commands via ddev; **PHP tests are PHPUnit** (classes extending `Tests\TestCase`, `use RefreshDatabase`, `public function test_*(): void`); JS tests are **Vitest**. Run: `ddev exec ./vendor/bin/phpunit <path>`, `ddev exec pnpm exec vitest run <path>`, full suites `ddev exec php artisan test` / `ddev exec pnpm run test`.
- Do NOT touch Filament (`/app-old`, assets, panel) or other resources. No DB schema changes.
- New controllers under `App\Http\Controllers\App`; requests under `App\Http\Requests\App`. React pages under `resources/js/pages/users/` and `resources/js/pages/auth/`. `@/` → `resources/js/*`.
- Routes go in the EXISTING `/app` group in `routes/web.php`: reset-password routes in the **guest** sub-group; users resource + send-reset in the **authenticated** sub-group. Resource as `->except('show')`, names `app.users.*`.
- No `UserPolicy`; `authorize()` returns true; gate on `auth` + self-guards.
- User model has `HasUuids`, `password` cast `hashed` (assigning plaintext hashes it; already-bcrypt values are not re-hashed), `login_enabled` gate, `assets(): hasMany(Asset,'owner_id')`. `UserFactory` sets `login_enabled=true` + a password.
- `name` (display) is derived server-side `trim(firstname.' '.lastname)` on create; editable on edit.
- Reset uses Laravel's default `ResetPassword` notification (no custom template) with the URL repointed to `/app/reset-password/{token}?email=…`.
- TDD for all server logic; commit after every task.

---

### Task 1: `SwitchField` (boolean toggle) form component

**Files:**
- Create: `resources/js/components/ui/switch.tsx` (via shadcn CLI), `resources/js/components/form/switch-field.tsx`
- Test: `resources/js/components/form/__tests__/switch-field.test.tsx`

**Interfaces:**
- Produces: `SwitchField` with props `{ id: string; label: string; checked: boolean; onChange: (v: boolean) => void; error?: string; description?: string }` — shadcn `Switch` + `Label` + `FormError`.

- [ ] **Step 1: Add the shadcn Switch primitive**

Run: `ddev exec pnpm dlx shadcn@latest add switch`
Expected: creates `resources/js/components/ui/switch.tsx` (Radix Switch). Verify it exports `Switch`.

- [ ] **Step 2: Write the failing test**

```tsx
// resources/js/components/form/__tests__/switch-field.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { SwitchField } from '../switch-field';

describe('SwitchField', () => {
    it('renders the label and reflects checked state', () => {
        render(<SwitchField id="login_enabled" label="Login enabled" checked={true} onChange={() => {}} />);
        expect(screen.getByText('Login enabled')).toBeInTheDocument();
        expect(screen.getByRole('switch')).toHaveAttribute('aria-checked', 'true');
    });

    it('fires onChange with the new boolean when toggled', () => {
        const onChange = vi.fn();
        render(<SwitchField id="login_enabled" label="Login enabled" checked={false} onChange={onChange} />);
        fireEvent.click(screen.getByRole('switch'));
        expect(onChange).toHaveBeenCalledWith(true);
    });

    it('shows the error message when present', () => {
        render(<SwitchField id="login_enabled" label="Login" checked={false} onChange={() => {}} error="bad" />);
        expect(screen.getByText('bad')).toBeInTheDocument();
    });
});
```

- [ ] **Step 3: Run to verify it fails**

Run: `ddev exec pnpm exec vitest run resources/js/components/form/__tests__/switch-field.test.tsx`
Expected: FAIL — cannot resolve `../switch-field`.

- [ ] **Step 4: Implement `SwitchField`**

```tsx
// resources/js/components/form/switch-field.tsx
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { FormError } from './form-error';

interface Props {
    id: string;
    label: string;
    checked: boolean;
    onChange: (v: boolean) => void;
    error?: string;
    description?: string;
}

export function SwitchField({ id, label, checked, onChange, error, description }: Props) {
    return (
        <div className="space-y-2">
            <div className="flex items-center gap-3">
                <Switch id={id} checked={checked} onCheckedChange={onChange} aria-invalid={!!error} />
                <Label htmlFor={id}>{label}</Label>
            </div>
            {description && <p className="text-sm text-muted-foreground">{description}</p>}
            <FormError message={error} />
        </div>
    );
}
```

- [ ] **Step 5: Run to verify it passes**

Run: `ddev exec pnpm exec vitest run resources/js/components/form/__tests__/switch-field.test.tsx`
Expected: PASS (3). (If happy-dom reports the role attribute differently, assert via `getByRole('switch')` presence + the click behavior — keep all three assertions meaningful.)

- [ ] **Step 6: Build + commit**

Run: `ddev exec pnpm run build` → succeeds.

```bash
git add resources/js/components/ui/switch.tsx resources/js/components/form/switch-field.tsx resources/js/components/form/__tests__/switch-field.test.tsx package.json pnpm-lock.yaml
git commit -m "feat(form): add SwitchField (boolean toggle)"
```

---

### Task 2: `PasswordField` form component

**Files:**
- Create: `resources/js/components/form/password-field.tsx`
- Test: `resources/js/components/form/__tests__/password-field.test.tsx`

**Interfaces:**
- Produces: `PasswordField` with props `{ id: string; label: string; value: string; onChange: (v: string) => void; error?: string; required?: boolean; autoFocus?: boolean }` — an `Input type="password"` + `FormError`, mirroring `TextField`.

- [ ] **Step 1: Write the failing test**

```tsx
// resources/js/components/form/__tests__/password-field.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { PasswordField } from '../password-field';

describe('PasswordField', () => {
    it('renders a masked input with the label', () => {
        render(<PasswordField id="password" label="Password" value="" onChange={() => {}} />);
        expect(screen.getByLabelText('Password')).toHaveAttribute('type', 'password');
    });

    it('fires onChange with the typed value', () => {
        const onChange = vi.fn();
        render(<PasswordField id="password" label="Password" value="" onChange={onChange} />);
        fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'secret' } });
        expect(onChange).toHaveBeenCalledWith('secret');
    });

    it('shows the error message when present', () => {
        render(<PasswordField id="password" label="Password" value="" onChange={() => {}} error="too short" />);
        expect(screen.getByText('too short')).toBeInTheDocument();
    });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `ddev exec pnpm exec vitest run resources/js/components/form/__tests__/password-field.test.tsx`
Expected: FAIL — cannot resolve `../password-field`.

- [ ] **Step 3: Implement `PasswordField`**

```tsx
// resources/js/components/form/password-field.tsx
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FormError } from './form-error';

interface Props {
    id: string;
    label: string;
    value: string;
    onChange: (v: string) => void;
    error?: string;
    required?: boolean;
    autoFocus?: boolean;
}

export function PasswordField({ id, label, value, onChange, error, required, autoFocus }: Props) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}{required && <span className="text-destructive"> *</span>}</Label>
            <Input id={id} type="password" value={value} autoFocus={autoFocus} aria-invalid={!!error}
                onChange={(e) => onChange(e.target.value)} />
            <FormError message={error} />
        </div>
    );
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `ddev exec pnpm exec vitest run resources/js/components/form/__tests__/password-field.test.tsx`
Expected: PASS (3).

- [ ] **Step 5: Build + commit**

Run: `ddev exec pnpm run build` → succeeds.

```bash
git add resources/js/components/form/password-field.tsx resources/js/components/form/__tests__/password-field.test.tsx
git commit -m "feat(form): add PasswordField"
```

---

### Task 3: Password-reset backend + page

**Files:**
- Create: `app/Http/Controllers/App/PasswordResetController.php`, `resources/js/pages/auth/reset-password.tsx`
- Modify: `app/Providers/AppServiceProvider.php` (repoint the reset URL), `routes/web.php` (guest reset routes)
- Test: `tests/Feature/App/PasswordResetTest.php`

**Interfaces:**
- Consumes: Laravel `Password` broker (default `users`), `PasswordField` (Task 2), the `password_reset_tokens` table (exists).
- Produces: routes `app.password.reset` (GET `/app/reset-password/{token}`) + `app.password.update` (POST `/app/reset-password`); the emailed reset link points to `/app/reset-password/{token}?email=…`; `PasswordResetController@edit` renders Inertia `auth/reset-password` with `{ token, email }`; `@update` resets via the broker.

- [ ] **Step 1: Write the failing tests**

```php
<?php // tests/Feature/App/PasswordResetTest.php
namespace Tests\Feature\App;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_url_points_into_the_app(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        Password::sendResetLink(['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $url = $n->toMail($user)->actionUrl;
            return str_contains($url, '/app/reset-password/')
                && str_contains($url, 'email='.urlencode($user->email));
        });
    }

    public function test_reset_page_is_reachable_as_guest(): void
    {
        $this->get('/app/reset-password/some-token?email=a@b.test')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('auth/reset-password')
                ->where('token', 'some-token')->where('email', 'a@b.test'));
    }

    public function test_valid_token_resets_the_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/app/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect('/app/login');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected_and_password_unchanged(): void
    {
        $user = User::factory()->create(['password' => bcrypt('original-pw')]);

        $this->post('/app/reset-password', [
            'token' => 'wrong-token',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('original-pw', $user->fresh()->password));
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/PasswordResetTest.php`
Expected: FAIL — routes undefined / URL not repointed.

- [ ] **Step 3: Repoint the reset URL in `AppServiceProvider::boot()`**

Add the import `use Illuminate\Auth\Notifications\ResetPassword;` and, inside `boot()`, append:

```php
ResetPassword::createUrlUsing(fn (object $notifiable, string $token) => url(
    '/app/reset-password/'.$token.'?email='.urlencode($notifiable->getEmailForPasswordReset()),
));
```

- [ ] **Step 4: Create the controller**

```php
<?php // app/Http/Controllers/App/PasswordResetController.php
namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('auth/reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                // The `hashed` cast on User::password hashes the assigned plaintext.
                $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
                $user->save();
            },
        );

        return $status === Password::PasswordReset
            ? redirect('/app/login')->with('success', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
```

- [ ] **Step 5: Register the guest routes**

In `routes/web.php`, inside the `Route::middleware('guest')->group(...)` block under the `/app` prefix (next to login), add:

```php
use App\Http\Controllers\App\PasswordResetController;
// … inside the guest group:
Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.update');
```

- [ ] **Step 6: Create the reset page**

```tsx
// resources/js/pages/auth/reset-password.tsx
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PasswordField } from '@/components/form/password-field';

interface Props { token: string; email: string; }

export default function ResetPassword({ token, email }: Props) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });
    const submit = (e: React.FormEvent) => { e.preventDefault(); form.post('/app/reset-password'); };
    return (
        <div className="flex min-h-screen items-center justify-center bg-background p-4">
            <Head title="Set your password" />
            <Card className="w-full max-w-sm">
                <CardHeader><CardTitle>Set your password</CardTitle></CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-4">
                        <p className="text-sm text-muted-foreground">Setting the password for <strong>{email}</strong>.</p>
                        <PasswordField id="password" label="New password" required autoFocus
                            value={form.data.password} onChange={(v) => form.setData('password', v)} error={form.errors.password} />
                        <PasswordField id="password_confirmation" label="Confirm password"
                            value={form.data.password_confirmation} onChange={(v) => form.setData('password_confirmation', v)}
                            error={form.errors.email} />
                        <Button type="submit" className="w-full" disabled={form.processing}>Set password</Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
```

(The broker returns token/email failures keyed to `email`; that error is surfaced under the confirm field so the user sees "invalid/expired" messages.)

- [ ] **Step 7: Run tests + build**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/PasswordResetTest.php`
Expected: PASS (4).
Run: `ddev exec pnpm run build`
Expected: succeeds.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/App/PasswordResetController.php app/Providers/AppServiceProvider.php routes/web.php resources/js/pages/auth/reset-password.tsx tests/Feature/App/PasswordResetTest.php
git commit -m "feat(auth): /app password-reset page + repoint reset email URL"
```

---

### Task 4: Users backend (controller, request, self-guards, send-reset)

**Files:**
- Create: `app/Http/Controllers/App/UserController.php`, `app/Http/Requests/App/UserRequest.php`
- Modify: `routes/web.php` (users resource + send-reset, authenticated group)
- Test: `tests/Feature/App/UserControllerTest.php`

**Interfaces:**
- Consumes: `TableQuery`, `User` (+ `assets` relation), `Password` broker (URL repointed in Task 3), `Str::password()`.
- Produces: routes `app.users.*` + `app.users.send-reset` (POST `/app/users/{user}/send-reset`); index props `users: { data: [{id,name,firstname,lastname,email,login_enabled,assets_count}], meta }`; edit props `user: {id,firstname,lastname,name,email,login_enabled}`, `isSelf: bool`, `assets: [{id,label}]`, `assetsCount: number`.

- [ ] **Step 1: Write the failing tests**

```php
<?php // tests/Feature/App/UserControllerTest.php
namespace Tests\Feature\App;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User { return User::factory()->create(['login_enabled' => true]); }

    public function test_index_lists_users_with_counts_and_login_flag(): void
    {
        User::factory()->count(2)->create();
        $this->actingAs($this->actor())->get('/app/users')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('users/index')
                ->has('users.data')
                ->has('users.data.0', fn (Assert $r) => $r
                    ->has('id')->has('name')->has('firstname')->has('lastname')
                    ->has('email')->has('login_enabled')->has('assets_count')->etc()));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/users')->assertRedirect();
    }

    public function test_store_derives_name_and_sends_reset_when_login_enabled(): void
    {
        Notification::fake();
        $this->actingAs($this->actor())->post('/app/users', [
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'login_enabled' => true, 'email' => 'ada@example.test',
        ])->assertRedirect('/app/users');

        $user = User::where('email', 'ada@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertNotNull($user->password);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_store_allows_no_login_and_no_email(): void
    {
        Notification::fake();
        $this->actingAs($this->actor())->post('/app/users', [
            'firstname' => 'No', 'lastname' => 'Login', 'login_enabled' => false,
        ])->assertRedirect('/app/users');

        $user = User::where('firstname', 'No')->first();
        $this->assertNull($user->email);
        Notification::assertNothingSent();
    }

    public function test_email_required_when_login_enabled(): void
    {
        $this->actingAs($this->actor())->post('/app/users', [
            'firstname' => 'X', 'lastname' => 'Y', 'login_enabled' => true,
        ])->assertSessionHasErrors('email');
    }

    public function test_email_must_be_unique_ignoring_self_on_update(): void
    {
        $a = User::factory()->create(['email' => 'taken@example.test', 'login_enabled' => true]);
        $b = User::factory()->create(['email' => 'b@example.test', 'login_enabled' => true]);

        // duplicate on create
        $this->actingAs($this->actor())->post('/app/users', [
            'firstname' => 'D', 'lastname' => 'up', 'login_enabled' => true, 'email' => 'taken@example.test',
        ])->assertSessionHasErrors('email');

        // b keeps its own email on update (no unique error against itself)
        $this->actingAs($this->actor())->put("/app/users/{$b->id}", [
            'firstname' => 'B', 'lastname' => 'B', 'name' => 'B B', 'login_enabled' => true, 'email' => 'b@example.test',
        ])->assertSessionHasNoErrors();
    }

    public function test_cannot_delete_self(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor)->delete("/app/users/{$actor->id}")->assertForbidden();
        $this->assertNotNull(User::find($actor->id));
    }

    public function test_can_delete_another_user(): void
    {
        $other = User::factory()->create();
        $this->actingAs($this->actor())->delete("/app/users/{$other->id}")->assertRedirect('/app/users');
        $this->assertNull(User::find($other->id));
    }

    public function test_cannot_disable_own_login(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor)->put("/app/users/{$actor->id}", [
            'firstname' => $actor->firstname, 'lastname' => $actor->lastname, 'name' => $actor->name,
            'login_enabled' => false, 'email' => $actor->email,
        ])->assertSessionHasErrors('login_enabled');

        $this->assertTrue($actor->fresh()->login_enabled);
    }

    public function test_send_reset_dispatches_notification(): void
    {
        Notification::fake();
        $target = User::factory()->create(['login_enabled' => true]);
        $this->actingAs($this->actor())->post("/app/users/{$target->id}/send-reset")->assertRedirect();
        Notification::assertSentTo($target, ResetPassword::class);
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/UserControllerTest.php`
Expected: FAIL — routes/controller undefined.

- [ ] **Step 3: Create the FormRequest**

```php
<?php // app/Http/Requests/App/UserRequest.php
namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'login_enabled' => ['boolean'],
            'email' => [
                'required_if:login_enabled,true', 'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

```php
<?php // app/Http/Controllers/App/UserController.php
namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\UserRequest;
use App\Models\User;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = TableQuery::for(User::query()->withCount('assets'), $request)
            ->searchable(['name', 'firstname', 'lastname', 'email'])
            ->sortable(['name', 'firstname', 'lastname', 'email', 'login_enabled', 'assets_count'])
            ->paginate();

        $users->getCollection()->transform(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'firstname' => $u->firstname,
            'lastname' => $u->lastname,
            'email' => $u->email,
            'login_enabled' => $u->login_enabled,
            'assets_count' => $u->assets_count,
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

    public function create(): Response { return Inertia::render('users/create'); }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['name'] = trim($data['firstname'].' '.$data['lastname']);
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
        $user->loadCount('assets');
        $user->load('assets.model.manufacturer');

        return Inertia::render('users/edit', [
            'user' => [
                'id' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'name' => $user->name,
                'email' => $user->email,
                'login_enabled' => $user->login_enabled,
            ],
            'isSelf' => $user->is($request->user()),
            'assets' => $user->assets->map(fn ($a) => [
                'id' => $a->id,
                'label' => '('.optional(optional($a->model)->manufacturer)->name.') '.optional($a->model)->name,
            ])->values(),
            'assetsCount' => $user->assets_count,
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        // Self-guard: cannot disable your own login.
        if ($user->is($request->user()) && ! (bool) ($data['login_enabled'] ?? false)) {
            throw ValidationException::withMessages(['login_enabled' => 'You cannot disable your own login.']);
        }

        $data['name'] = $data['name'] ?? trim($data['firstname'].' '.$data['lastname']);

        $user->update($data);

        return to_route('app.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot delete your own account.');

        $user->delete();

        return to_route('app.users.index')->with('success', 'User deleted.');
    }

    public function sendReset(User $user): RedirectResponse
    {
        abort_unless($user->email, 404);

        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', 'Password reset email sent.');
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, inside the authenticated `/app` group (alongside the other resources):

```php
use App\Http\Controllers\App\UserController;
// … inside the authenticated group:
Route::post('users/{user}/send-reset', [UserController::class, 'sendReset'])->name('users.send-reset');
Route::resource('users', UserController::class)->except('show');
```

(Declare the `send-reset` line **before** the resource so it isn't shadowed by the resource's `{user}` wildcard patterns.)

- [ ] **Step 6: Run tests to verify they pass**

Run: `ddev exec ./vendor/bin/phpunit tests/Feature/App/UserControllerTest.php`
Expected: PASS (all cases).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/App/UserController.php app/Http/Requests/App/UserRequest.php routes/web.php tests/Feature/App/UserControllerTest.php
git commit -m "feat(users): Inertia controller with reset-email flow + self-guards"
```

---

### Task 5: Users frontend (index, form, create/edit, nav)

**Files:**
- Create: `resources/js/pages/users/{index,create,edit,user-form}.tsx`
- Modify: `resources/js/config/nav.ts`

**Interfaces:**
- Consumes: `DataTable`, `TextField`, `SwitchField` (Task 1), `AppLayout`, Inertia `useForm`/`router`; props from Task 4.
- Produces: the three pages + a shared `UserForm` (props `{ initial?: { id: string; firstname: string; lastname: string; name: string; email: string | null; login_enabled: boolean }; isSelf?: boolean; submitUrl: string; method: 'post' | 'put' }`).

- [ ] **Step 1: Create the shared form**

```tsx
// resources/js/pages/users/user-form.tsx
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/text-field';
import { SwitchField } from '@/components/form/switch-field';

interface Initial { id: string; firstname: string; lastname: string; name: string; email: string | null; login_enabled: boolean; }
interface Props { initial?: Initial; isSelf?: boolean; submitUrl: string; method: 'post' | 'put'; }

export function UserForm({ initial, isSelf = false, submitUrl, method }: Props) {
    const isEdit = method === 'put';
    const form = useForm({
        firstname: initial?.firstname ?? '',
        lastname: initial?.lastname ?? '',
        name: initial?.name ?? '',
        email: initial?.email ?? '',
        login_enabled: initial?.login_enabled ?? false,
    });

    const submit = (e: React.FormEvent) => { e.preventDefault(); form.submit(method, submitUrl); };

    return (
        <form onSubmit={submit} className="max-w-lg space-y-6">
            <div className="grid grid-cols-2 gap-4">
                <TextField id="firstname" label="First name" required autoFocus
                    value={form.data.firstname} onChange={(v) => form.setData('firstname', v)} error={form.errors.firstname} />
                <TextField id="lastname" label="Last name" required
                    value={form.data.lastname} onChange={(v) => form.setData('lastname', v)} error={form.errors.lastname} />
            </div>

            {isEdit && (
                <div className="space-y-2">
                    <TextField id="name" label="Display name" required
                        value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} />
                    <Button type="button" variant="outline" size="sm"
                        onClick={() => form.setData('name', `${form.data.firstname} ${form.data.lastname}`.trim())}>
                        Regenerate from names
                    </Button>
                </div>
            )}

            <SwitchField id="login_enabled" label="Login enabled"
                checked={form.data.login_enabled}
                onChange={(v) => form.setData('login_enabled', v)}
                error={form.errors.login_enabled}
                description={isSelf ? 'You cannot disable your own login.' : undefined} />

            {form.data.login_enabled && (
                <TextField id="email" label="Email" required
                    value={form.data.email} onChange={(v) => form.setData('email', v)} error={form.errors.email} />
            )}

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="ghost" onClick={() => history.back()}>Cancel</Button>
            </div>
        </form>
    );
}
```

(Server validation is authoritative; the `isSelf` disable is a UI affordance. If you want the switch itself disabled when `isSelf`, pass `disabled` — but the server already rejects self-disable, so leaving it enabled with the description note is acceptable. Do NOT add a `disabled` prop unless `SwitchField` supports it; it does not, so rely on the server guard + description.)

- [ ] **Step 2: Create create/edit pages**

```tsx
// resources/js/pages/users/create.tsx
import AppLayout from '@/layouts/app-layout';
import { UserForm } from './user-form';

export default function CreateUser() {
    return (
        <AppLayout title="New user" breadcrumbs={[{ label: 'Users', href: '/app/users' }, { label: 'New' }]}>
            <h1 className="mb-6 text-2xl font-semibold">New user</h1>
            <p className="mb-4 max-w-lg text-sm text-muted-foreground">
                If login is enabled, the user receives an email to set their own password.
            </p>
            <UserForm submitUrl="/app/users" method="post" />
        </AppLayout>
    );
}
```

```tsx
// resources/js/pages/users/edit.tsx
import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { UserForm } from './user-form';

interface Props {
    user: { id: string; firstname: string; lastname: string; name: string; email: string | null; login_enabled: boolean };
    isSelf: boolean;
    assets: { id: string; label: string }[];
    assetsCount: number;
}

export default function EditUser({ user, isSelf, assets, assetsCount }: Props) {
    return (
        <AppLayout title="Edit user" breadcrumbs={[{ label: 'Users', href: '/app/users' }, { label: user.name }]}>
            <h1 className="mb-6 text-2xl font-semibold">Edit user</h1>
            <UserForm initial={user} isSelf={isSelf} submitUrl={`/app/users/${user.id}`} method="put" />

            {user.email && (
                <div className="mt-6 max-w-lg">
                    <Button type="button" variant="outline"
                        onClick={() => router.post(`/app/users/${user.id}/send-reset`)}>
                        Send password reset email
                    </Button>
                </div>
            )}

            <div className="mt-8 max-w-lg">
                <h2 className="mb-2 text-sm font-medium text-muted-foreground">Owned assets ({assetsCount})</h2>
                {assets.length ? (
                    <ul className="space-y-1 text-sm">
                        {assets.map((a) => <li key={a.id} className="rounded border px-3 py-1.5">{a.label}</li>)}
                    </ul>
                ) : (
                    <p className="text-sm text-muted-foreground">No assets assigned.</p>
                )}
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 3: Create the index page**

```tsx
// resources/js/pages/users/index.tsx
import { Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/data-table/data-table';
import type { PaginationMeta } from '@/components/data-table/types';

interface Row { id: string; name: string; firstname: string; lastname: string; email: string | null; login_enabled: boolean; assets_count: number; }

const columns: ColumnDef<Row>[] = [
    { accessorKey: 'name', header: 'Name' },
    { accessorKey: 'firstname', header: 'First name' },
    { accessorKey: 'lastname', header: 'Last name' },
    { accessorKey: 'email', header: 'Email', cell: ({ row }) => row.original.email ?? '—' },
    { accessorKey: 'login_enabled', header: 'Login', cell: ({ row }) => (
        <Badge variant={row.original.login_enabled ? 'default' : 'secondary'}>{row.original.login_enabled ? 'Yes' : 'No'}</Badge>
    ) },
    { accessorKey: 'assets_count', header: 'Assets', cell: ({ row }) => <Badge variant="secondary">{row.original.assets_count}</Badge> },
    {
        id: 'actions', header: '',
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button asChild variant="ghost" size="icon"><Link href={`/app/users/${row.original.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                <Button variant="ghost" size="icon" onClick={() => { if (confirm(`Delete ${row.original.name}?`)) router.delete(`/app/users/${row.original.id}`); }}><Trash2 className="h-4 w-4" /></Button>
            </div>
        ),
    },
];

export default function UsersIndex({ users }: { users: { data: Row[]; meta: PaginationMeta } }) {
    return (
        <AppLayout title="Users" breadcrumbs={[{ label: 'Users' }]}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Users</h1>
                <Button asChild><Link href="/app/users/create">New user</Link></Button>
            </div>
            <DataTable columns={columns} rows={users.data} pagination={users.meta} baseUrl="/app/users"
                sortable={['name', 'firstname', 'lastname', 'email', 'login_enabled', 'assets_count']} />
        </AppLayout>
    );
}
```

- [ ] **Step 4: Add an Administration nav group with Users**

In `resources/js/config/nav.ts`, import `Users` from `lucide-react` and append a new group after "Inventory":

```ts
{
    label: 'Administration',
    items: [
        { label: 'Users', href: '/app/users', icon: Users, match: (p) => p.startsWith('/app/users') },
    ],
},
```

- [ ] **Step 5: Build + verify**

Run: `ddev exec pnpm run build`
Expected: succeeds, no type errors.

- [ ] **Step 6: Manual verify**

Log in at `/app`, `/app/users`: list shows Login + Assets badges; create a login-enabled user (a reset email is queued); edit shows read-only owned assets + "Send password reset email"; your own row can't be deleted and your own login switch can't be turned off (server rejects); sortable columns work.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/users resources/js/config/nav.ts
git commit -m "feat(users): Inertia index + create/edit pages + Administration nav"
```

---

### Task 6: Final verification

**Files:** none (verification only).

- [ ] **Step 1: Full PHP suite** — `ddev exec php artisan test` → all green (Users + PasswordReset + all prior).
- [ ] **Step 2: Full JS suite** — `ddev exec pnpm run test` → all green (incl. SwitchField + PasswordField).
- [ ] **Step 3: Build** — `ddev exec pnpm run build` → succeeds.
- [ ] **Step 4: Coexistence + nav** — `/app` sidebar shows Inventory (Manufacturers/Places/Asset types/Asset models) + Administration (Users); user CRUD + reset flow work; `/app-old` Filament still fine.
- [ ] **Step 5:** Commit anything outstanding (releases are automated from conventional commits — skip manual CHANGELOG).

---

## Self-Review Notes

- **Spec coverage:** `SwitchField`/`PasswordField` → Tasks 1–2; password-reset flow (URL repoint, guest routes, controller, page) → Task 3; Users backend (index/CRUD, conditional validation, random-password-on-create + reset email, self-guards, send-reset) → Task 4; Users frontend (index badges, conditional email, name-regenerate, read-only assets, send-reset button, Administration nav) → Task 5; testing → per task + Task 6. All spec sections covered.
- **Type consistency:** `UserForm` `initial` shape (Task 5) matches the `edit` prop `user` (Task 4); index `Row` matches the transformed row (Task 4); `isSelf`/`assets`/`assetsCount` props match. `SwitchField`/`PasswordField` prop names match their usage.
- **Ordering:** Task 3 (URL repoint) precedes Task 4 (which sends reset links) so real reset emails resolve the `/app` URL. `send-reset` route declared before the `users` resource to avoid wildcard shadowing.
- **Deviations from Filament (per spec):** no plaintext password field (reset-email flow); assets read-only; self-guards added; `login_enabled` badge + `assets_count` on index. No roles/policy (none exist).

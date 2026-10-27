<?php

// app/Http/Controllers/App/UserController.php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\UserRequest;
use App\Models\Asset;
use App\Models\User;
use App\Support\Table\TableQuery;
use Illuminate\Http\JsonResponse;
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
            ->sortable(['name', 'email', 'login_enabled', 'assets_count'])
            ->paginate();

        $users->getCollection()->transform(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
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
        return Inertia::render('users/create');
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $this->withName($request->validated());
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

    public function show(User $user): Response
    {
        $user->loadCount('assets')->load('assets.model.manufacturer', 'assets.place');

        return Inertia::render('users/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'login_enabled' => $user->login_enabled,
                'assets_count' => $user->assets_count,
            ],
            'assets' => $user->assets->map(fn (Asset $a) => [
                'id' => $a->id,
                'model_name' => optional($a->model)->name,
                'serial_number' => $a->serial_number,
                'state' => $a->state->value,
                'state_label' => $a->state->getLabel(),
                'place_name' => optional($a->place)->name,
            ])->all(),
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        return Inertia::render('users/edit', [
            'user' => [
                'id' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'login_enabled' => $user->login_enabled,
            ],
            'isSelf' => $user->is($request->user()),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $this->withName($request->validated());

        // Self-guard: cannot disable your own login.
        if ($user->is($request->user()) && ! (bool) ($data['login_enabled'] ?? false)) {
            throw ValidationException::withMessages(['login_enabled' => 'You cannot disable your own login.']);
        }

        $user->update($data);

        return to_route('app.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot delete your own account.');

        if ($user->assets()->exists()) {
            throw ValidationException::withMessages([
                'user' => 'Reassign this user\'s assets before deleting them.',
            ]);
        }

        $user->delete();

        return to_route('app.users.index')->with('success', 'User deleted.');
    }

    public function quickStore(UserRequest $request): JsonResponse
    {
        $user = User::create($this->withName($request->validated()) + ['login_enabled' => false]);

        return response()->json(['id' => $user->id, 'name' => $user->name], 201);
    }

    public function sendReset(User $user): RedirectResponse
    {
        abort_unless($user->email, 404);

        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', 'Password reset email sent.');
    }

    /**
     * Derive the display name from first/last (name is not a form field).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withName(array $data): array
    {
        $data['name'] = trim(($data['firstname'] ?? '').' '.($data['lastname'] ?? ''));

        return $data;
    }
}

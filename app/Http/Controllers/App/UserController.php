<?php

// app/Http/Controllers/App/UserController.php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\UserRequest;
use App\Models\Asset;
use App\Models\Person;
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
        // User no longer owns assets directly (ownership moved to Person); a user's
        // asset count is the count of assets owned by the Person linked via person_id.
        $query = User::query()
            ->leftJoin('people', 'people.id', '=', 'users.person_id')
            ->select('users.*', 'people.name as person_name')
            ->addSelect(['assets_count' => Asset::query()
                ->selectRaw('count(*)')
                ->whereColumn('owner_id', 'users.person_id'),
            ]);

        $users = TableQuery::for($query, $request)
            // Search the real joined column, not the `person_name` select alias:
            // MariaDB rejects aliases in WHERE (SQLite tolerates them, so tests wouldn't catch it).
            ->searchable(['people.name', 'users.email'])
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

        $user->delete();

        return to_route('app.users.index')->with('success', 'User deleted.');
    }

    public function sendReset(User $user): RedirectResponse
    {
        abort_unless($user->email, 404);

        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', 'Password reset email sent.');
    }

    /** @return array<int, array{value: string, label: string}> */
    private function personOptions(): array
    {
        return Person::query()->orderBy('name')->get()
            ->map(fn (Person $p) => ['value' => $p->id, 'label' => $p->name])->all();
    }
}

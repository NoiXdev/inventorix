<?php

// app/Http/Controllers/App/UserController.php

namespace App\Http\Controllers\App;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\UserRequest;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\ConsumableAssignment;
use App\Models\Department;
use App\Models\Group;
use App\Models\LicenseSeat;
use App\Models\Location;
use App\Models\User;
use App\Services\AccessoryService;
use App\Services\LicenseService;
use App\Support\Assets\CheckoutOptions;
use App\Support\Table\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = TableQuery::for(User::query()->with('department:id,name')->withCount('assets'), $request)
            ->searchable(['name', 'firstname', 'lastname', 'email', 'employee_num', 'jobtitle'])
            ->sortable(['name', 'email', 'login_enabled', 'assets_count', 'employee_num', 'jobtitle'])
            ->filterable(['department_id', 'location_id', 'manager_id'])
            ->paginate();

        $paths = Location::paths(withTrashed: true);

        $users->getCollection()->transform(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'login_enabled' => $u->login_enabled,
            'assets_count' => (int) $u->assets_count,
            'employee_num' => $u->employee_num,
            'jobtitle' => $u->jobtitle,
            'department_name' => $u->department?->name,
            'location_name' => $u->location_id ? $paths->get($u->location_id) : null,
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
            'departmentOptions' => Department::options(),
            'locationOptions' => Location::options(),
            'managerOptions' => CheckoutOptions::users(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('users/create', [
            ...$this->assignmentProps($request->user(), null),
            ...$this->profileOptions(null),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $this->withName($this->profile($request));
        $loginEnabled = (bool) ($data['login_enabled'] ?? false);
        if ($loginEnabled) {
            $data['password'] = Str::password();
        }

        $user = DB::transaction(function () use ($request, $data) {
            $user = User::create($data);
            $this->applyAssignment($request, $user);

            return $user;
        });

        if ($loginEnabled && $user->email) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return to_route('app.users.index')->with('success', 'User created.');
    }

    public function show(Request $request, User $user): Response
    {
        $user->loadCount('assets')->load('assets.model.manufacturer', 'assets.location', 'assets.status', 'department', 'manager:id,name');

        return Inertia::render('users/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'login_enabled' => $user->login_enabled,
                'assets_count' => $user->assets_count,
                ...$this->profileFields($user),
                'department_id' => $user->department_id,
                'department_name' => $user->department?->name,
                'manager' => $user->manager ? ['id' => $user->manager->id, 'name' => $user->manager->name] : null,
                'location_name' => $user->location_id ? Location::paths(withTrashed: true)->get($user->location_id) : null,
            ],
            'groups' => $user->groups()->orderBy('name')->pluck('name')->all(),
            'effective' => $user->effectivePermissions(),
            'licenses' => $request->user()->can('licenses.view') ? LicenseSeat::heldBy($user) : null,
            'accessories' => $request->user()->can('accessories.view') ? AccessoryCheckout::heldBy($user) : null,
            'consumables' => $request->user()->can('consumables.view') ? ConsumableAssignment::handedTo($user) : null,
            // Edit / delete are only offered when the policy would allow them.
            'canManage' => $request->user()->can('manage', $user),
            'canOffboard' => UserOffboardingController::mayOffboard($request->user()),
            'assets' => $user->assets->map(fn (Asset $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'asset_tag' => $a->asset_tag,
                'model_name' => optional($a->model)->name,
                'serial' => $a->serial,
                'status' => $a->status ? ['id' => $a->status->id, 'name' => $a->status->name, 'type' => $a->status->type->value] : null,
                'location_name' => optional($a->location)->name,
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
                ...$this->profileFields($user),
                'department_id' => $user->department_id,
                'manager_id' => $user->manager_id,
                'location_id' => $user->location_id,
                'groups' => $user->groups->pluck('id')->all(),
                'permissions' => (object) ($user->permissions ?? []),
            ],
            'isSelf' => $user->is($request->user()),
            'effective' => $user->permissionSources(),
            ...$this->assignmentProps($request->user(), $user),
            ...$this->profileOptions($user),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $this->withName($this->profile($request));

        // An absent login_enabled leaves the current value untouched.
        $loginEnabled = (bool) ($data['login_enabled'] ?? $user->login_enabled);

        // Self-guard: cannot disable your own login.
        if ($user->is($request->user()) && ! $loginEnabled) {
            throw ValidationException::withMessages(['login_enabled' => 'You cannot disable your own login.']);
        }

        DB::transaction(function () use ($request, $user, $data, $loginEnabled) {
            [$permissions, $groupIds] = $this->assignment($request, $user);
            $this->guardSuperusers($request->user(), $user, $loginEnabled, $permissions, $groupIds);

            $user->update($data);
            $this->applyAssignment($request, $user);
        });

        return to_route('app.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'You cannot delete your own account.']);
        }

        if ($user->assets()->exists()) {
            throw ValidationException::withMessages([
                'user' => 'Reassign this user\'s assets before deleting them.',
            ]);
        }

        if ($user->isLastActiveSuperuser()) {
            throw ValidationException::withMessages(['user' => User::LAST_SUPERUSER]);
        }

        DB::transaction(function () use ($user): void {
            // License seats and accessories come back first, with a history entry each.
            app(LicenseService::class)->checkInAllFor($user, 'User deleted');
            app(AccessoryService::class)->checkInAllFor($user, 'User deleted');
            $user->delete();
        });

        return to_route('app.users.index')->with('success', 'User deleted.');
    }

    public function quickStore(UserRequest $request): JsonResponse
    {
        $user = User::create(array_merge($this->withName($this->profile($request)), ['login_enabled' => false]));

        return response()->json(['id' => $user->id, 'name' => $user->name], 201);
    }

    public function sendReset(User $user): RedirectResponse
    {
        abort_unless($user->email, 404);

        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', 'Password reset email sent.');
    }

    /**
     * The submitted map and group ids as they would be stored, or null when the
     * field is not being changed. A non-superuser cannot touch admin/superuser
     * (the request rejects changes), so keys they leave out are carried over.
     *
     * @return array{0: array<string, int>|null, 1: list<string>|null}
     */
    private function assignment(UserRequest $request, ?User $user): array
    {
        if (! $request->mayAssign()) {
            return [null, null];
        }

        $validated = $request->validated();
        $permissions = null;
        if (array_key_exists('permissions', $validated)) {
            $permissions = array_map('intval', (array) $validated['permissions']);
            if (! $request->user()->isSuperuser()) {
                foreach ([Permission::SUPERUSER, Permission::ADMIN] as $privileged) {
                    $current = ($user?->permissions ?? [])[$privileged->value] ?? null;
                    if ($current !== null && ! array_key_exists($privileged->value, $permissions)) {
                        $permissions[$privileged->value] = (int) $current;
                    }
                }
            }
            $permissions = array_filter($permissions, fn (int $state) => $state === 1 || $state === -1);
        }

        $groupIds = array_key_exists('groups', $validated) ? array_values(array_unique((array) $validated['groups'])) : null;

        return [$permissions, $groupIds];
    }

    /** Store the per-user map and sync groups, logging membership changes on the user. */
    private function applyAssignment(UserRequest $request, User $user): void
    {
        [$permissions, $groupIds] = $this->assignment($request, $user);

        if ($permissions !== null) {
            $user->permissions = $permissions === [] ? null : $permissions;
            $user->save();
        }

        if ($groupIds !== null) {
            $before = $user->groups()->orderBy('name')->pluck('name')->all();
            $user->groups()->sync($groupIds);
            $after = $user->groups()->orderBy('name')->pluck('name')->all();
            $user->unsetRelation('groups');

            if ($before !== $after) {
                activity('user')
                    ->performedOn($user)
                    ->causedBy($request->user())
                    ->event('groups_changed')
                    ->withChanges(['attributes' => ['groups' => $after], 'old' => ['groups' => $before]])
                    ->log('groups_changed');
            }
        }
    }

    /**
     * Guard rails: the last active superuser cannot lose superuser or login,
     * and nobody can remove their own superuser access.
     *
     * @param  array<string, int>|null  $permissions
     * @param  list<string>|null  $groupIds
     */
    private function guardSuperusers(User $actor, User $user, bool $loginEnabled, ?array $permissions, ?array $groupIds): void
    {
        $after = clone $user;
        $after->login_enabled = $loginEnabled;
        if ($permissions !== null) {
            $after->permissions = $permissions;
        }
        $after->setRelation('groups', $groupIds !== null ? Group::whereIn('id', $groupIds)->get() : $user->groups()->get());

        if (! $user->isSuperuser() || ($after->isSuperuser() && $after->login_enabled)) {
            return;
        }

        $field = match (true) {
            ! $after->login_enabled && $after->isSuperuser() => 'login_enabled',
            $permissions !== null && ($permissions['superuser'] ?? null) !== (($user->permissions ?? [])['superuser'] ?? null) => 'permissions',
            $groupIds !== null => 'groups',
            default => 'permissions',
        };

        if ($user->isLastActiveSuperuser()) {
            throw ValidationException::withMessages([$field => User::LAST_SUPERUSER]);
        }

        if ($user->is($actor) && ! $after->isSuperuser()) {
            throw ValidationException::withMessages([$field => 'You cannot remove your own superuser access.']);
        }
    }

    /**
     * Options and flags for the groups / permissions panel of the user form.
     *
     * @return array<string, mixed>
     */
    private function assignmentProps(User $actor, ?User $target): array
    {
        return [
            'groupOptions' => Group::orderBy('name')->get()->map(fn (Group $g) => [
                'value' => $g->id,
                'label' => $g->name,
                'privileged' => $g->isPrivileged(),
            ])->all(),
            'catalogue' => Permission::catalogue(),
            'canAssign' => $actor->hasAccess(Permission::ADMIN) && ($target === null || ! $target->is($actor) || $actor->isSuperuser()),
            'canAssignPrivileged' => $actor->isSuperuser(),
        ];
    }

    /**
     * Pickers of the profile section. On edit, the user's own department and
     * location stay offered even when deleted since, and the user is not
     * offered as their own manager.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function profileOptions(?User $user): array
    {
        $departments = Department::options();
        if ($user?->department_id && ! collect($departments)->contains('value', $user->department_id)) {
            $departments[] = ['value' => $user->department_id, 'label' => $user->department?->name];
        }

        $locations = Location::options();
        if ($user?->location_id && ! collect($locations)->contains('value', $user->location_id)) {
            $locations[] = ['value' => $user->location_id, 'label' => Location::paths(withTrashed: true)->get($user->location_id)];
        }

        return [
            'departmentOptions' => $departments,
            'locationOptions' => $locations,
            'managerOptions' => array_values(array_filter(CheckoutOptions::users(), fn (array $o) => $o['value'] !== $user?->id)),
        ];
    }

    /** @return array<string, mixed> */
    private function profileFields(User $user): array
    {
        return [
            'employee_num' => $user->employee_num,
            'jobtitle' => $user->jobtitle,
            'phone' => $user->phone,
            'mobile' => $user->mobile,
            'start_date' => $user->start_date?->toDateString(),
            'end_date' => $user->end_date?->toDateString(),
            'notes' => $user->notes,
        ];
    }

    /**
     * The profile fields only; groups and permissions are applied separately.
     *
     * @return array<string, mixed>
     */
    private function profile(UserRequest $request): array
    {
        return Arr::except($request->validated(), ['groups', 'permissions']);
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

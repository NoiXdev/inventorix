<?php

namespace App\Http\Controllers\App;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\GroupRequest;
use App\Models\Group;
use App\Models\User;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    private const LAST_SUPERUSER = 'At least one active superuser must remain.';

    public function index(Request $request): Response
    {
        $groups = TableQuery::for(Group::query()->withCount('users'), $request)
            ->searchable(['name'])->sortable(['name', 'users_count'])->paginate();

        $groups->getCollection()->transform(fn (Group $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'users_count' => (int) $g->users_count,
            'summary' => $this->summary($g),
        ]);

        return Inertia::render('groups/index', [
            'groups' => [
                'data' => $groups->items(),
                'meta' => [
                    'current_page' => $groups->currentPage(),
                    'last_page' => $groups->lastPage(),
                    'per_page' => $groups->perPage(),
                    'total' => $groups->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('groups/create', ['catalogue' => Permission::catalogue()]);
    }

    public function store(GroupRequest $request): RedirectResponse
    {
        Group::create([
            'name' => $request->validated('name'),
            'notes' => $request->validated('notes'),
            'permissions' => $request->granted(),
        ]);

        return to_route('app.groups.index')->with('success', 'Group created.');
    }

    public function edit(Group $group): Response
    {
        return Inertia::render('groups/edit', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'notes' => $group->notes,
                'permissions' => (object) ($group->permissions ?? []),
            ],
            'catalogue' => Permission::catalogue(),
        ]);
    }

    public function update(GroupRequest $request, Group $group): RedirectResponse
    {
        $permissions = $request->granted();
        $losesSuperuser = $this->grantsSuperuser($group) && ! isset($permissions['superuser']);

        DB::transaction(function () use ($request, $group, $permissions, $losesSuperuser) {
            $group->update([
                'name' => $request->validated('name'),
                'notes' => $request->validated('notes'),
                'permissions' => $permissions,
            ]);

            if ($losesSuperuser) {
                $this->ensureSuperuserRemains('permissions');
            }
        });

        return to_route('app.groups.index')->with('success', 'Group updated.');
    }

    public function destroy(Group $group): RedirectResponse
    {
        $grantsSuperuser = $this->grantsSuperuser($group);

        DB::transaction(function () use ($group, $grantsSuperuser) {
            $group->users()->detach();
            $group->delete();

            if ($grantsSuperuser) {
                $this->ensureSuperuserRemains('group');
            }
        });

        return to_route('app.groups.index')->with('success', 'Group deleted.');
    }

    private function grantsSuperuser(Group $group): bool
    {
        return (int) (($group->permissions ?? [])[Permission::SUPERUSER->value] ?? 0) === 1;
    }

    /** Throws (rolling back the surrounding transaction) when nobody active is left as superuser. */
    private function ensureSuperuserRemains(string $field): void
    {
        if (! User::activeSuperuserExists()) {
            throw ValidationException::withMessages([$field => self::LAST_SUPERUSER]);
        }
    }

    private function summary(Group $group): string
    {
        $permissions = $group->permissions ?? [];

        return match (true) {
            $this->grantsSuperuser($group) => 'Superuser',
            (int) ($permissions[Permission::ADMIN->value] ?? 0) === 1 => 'Admin',
            default => count($permissions).' permissions',
        };
    }
}

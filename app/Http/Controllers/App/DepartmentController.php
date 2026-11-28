<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\DepartmentRequest;
use App\Models\Department;
use App\Models\Location;
use App\Support\Assets\CheckoutOptions;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $departments = TableQuery::for(Department::query()->with(['manager:id,name', 'location:id'])->withCount('users'), $request)
            ->searchable(['name'])
            ->sortable(['name', 'users_count'])
            ->filterable(['location_id'])
            ->paginate();

        $paths = Location::paths(withTrashed: true);

        $departments->getCollection()->transform(fn (Department $d) => [
            'id' => $d->id,
            'name' => $d->name,
            'manager_name' => $d->manager?->name,
            'location_name' => $d->location_id ? $paths->get($d->location_id) : null,
            'users_count' => $d->users_count,
        ]);

        return Inertia::render('departments/index', [
            'departments' => [
                'data' => $departments->items(),
                'meta' => [
                    'current_page' => $departments->currentPage(),
                    'last_page' => $departments->lastPage(),
                    'per_page' => $departments->perPage(),
                    'total' => $departments->total(),
                ],
            ],
            'locationOptions' => Location::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('departments/create', $this->options());
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        Department::create($request->validated());

        return to_route('app.departments.index')->with('success', 'Department created.');
    }

    public function edit(Department $department): Response
    {
        return Inertia::render('departments/edit', [
            'department' => $department->only(['id', 'name', 'manager_id', 'location_id', 'notes']),
            ...$this->options($department),
        ]);
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return to_route('app.departments.index')->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->users()->exists()) {
            return back()->with('error', "Move this department's users first.");
        }

        $department->delete();

        return to_route('app.departments.index')->with('success', 'Department deleted.');
    }

    /**
     * The pickers' options. When editing, a location that has since been
     * deleted is still offered, so opening and saving the form doesn't fail.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function options(?Department $department = null): array
    {
        $locations = Location::options();
        if ($department?->location_id && ! collect($locations)->contains('value', $department->location_id)) {
            $locations[] = ['value' => $department->location_id, 'label' => Location::paths(withTrashed: true)->get($department->location_id)];
        }

        return [
            'managerOptions' => CheckoutOptions::users(),
            'locationOptions' => $locations,
        ];
    }
}

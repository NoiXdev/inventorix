<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\PersonRequest;
use App\Models\Asset;
use App\Models\Person;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function index(Request $request): Response
    {
        $people = TableQuery::for(Person::query()->withCount('assets'), $request)
            ->searchable(['name', 'firstname', 'lastname', 'email'])
            ->sortable(['name', 'email', 'assets_count'])
            ->paginate();

        $people->getCollection()->transform(fn (Person $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'firstname' => $p->firstname,
            'lastname' => $p->lastname,
            'email' => $p->email,
            'assets_count' => $p->assets_count,
        ]);

        return Inertia::render('people/index', [
            'people' => [
                'data' => $people->items(),
                'meta' => [
                    'current_page' => $people->currentPage(),
                    'last_page' => $people->lastPage(),
                    'per_page' => $people->perPage(),
                    'total' => $people->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('people/create');
    }

    public function store(PersonRequest $request): RedirectResponse
    {
        Person::create($this->withName($request->validated()));

        return to_route('app.people.index')->with('success', 'Person created.');
    }

    public function show(Person $person): Response
    {
        $person->loadCount('assets')->load('assets.model.manufacturer', 'assets.place');

        return Inertia::render('people/show', [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'firstname' => $person->firstname,
                'lastname' => $person->lastname,
                'email' => $person->email,
                'assets_count' => $person->assets_count,
            ],
            'assets' => $person->assets->map(fn (Asset $a) => [
                'id' => $a->id,
                'model_name' => optional($a->model)->name,
                'serial_number' => $a->serial_number,
                'state' => $a->state->value,
                'state_label' => $a->state->getLabel(),
                'place_name' => optional($a->place)->name,
            ])->all(),
        ]);
    }

    public function edit(Person $person): Response
    {
        return Inertia::render('people/edit', [
            'person' => [
                'id' => $person->id,
                'firstname' => $person->firstname,
                'lastname' => $person->lastname,
                'email' => $person->email,
            ],
        ]);
    }

    public function update(PersonRequest $request, Person $person): RedirectResponse
    {
        $person->update($this->withName($request->validated()));

        return to_route('app.people.index')->with('success', 'Person updated.');
    }

    public function destroy(Person $person): RedirectResponse
    {
        if ($person->assets()->exists()) {
            throw ValidationException::withMessages([
                'person' => 'Reassign this person\'s assets before deleting them.',
            ]);
        }

        $person->delete();

        return to_route('app.people.index')->with('success', 'Person deleted.');
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

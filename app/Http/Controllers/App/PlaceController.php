<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\PlaceRequest;
use App\Models\Place;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    public function index(Request $request): Response
    {
        $places = TableQuery::for(Place::query(), $request)
            ->searchable(['name'])->sortable(['name'])->paginate();

        $places->getCollection()->transform(fn (Place $p) => [
            'id' => $p->id,
            'name' => $p->name,
        ]);

        return Inertia::render('places/index', [
            'places' => [
                'data' => $places->items(),
                'meta' => [
                    'current_page' => $places->currentPage(),
                    'last_page' => $places->lastPage(),
                    'per_page' => $places->perPage(),
                    'total' => $places->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('places/create');
    }

    public function store(PlaceRequest $request): RedirectResponse
    {
        Place::create($request->validated());

        return to_route('app.places.index')->with('success', 'Place created.');
    }

    public function edit(Place $place): Response
    {
        return Inertia::render('places/edit', ['place' => ['id' => $place->id, 'name' => $place->name]]);
    }

    public function update(PlaceRequest $request, Place $place): RedirectResponse
    {
        $place->update($request->validated());

        return to_route('app.places.index')->with('success', 'Place updated.');
    }

    public function destroy(Place $place): RedirectResponse
    {
        $place->delete();

        return to_route('app.places.index')->with('success', 'Place deleted.');
    }
}

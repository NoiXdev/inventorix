<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\ManufacturerRequest;
use App\Models\Manufacturer;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManufacturerController extends Controller
{
    public function index(Request $request): Response
    {
        // withCount(['models', 'assets']) runs both counts as correlated
        // subqueries on the same query, avoiding a query per row (assets is a
        // hasManyThrough relation on the model, see Manufacturer::assets()).
        $manufacturers = TableQuery::for(
            Manufacturer::query()->withCount(['models', 'assets']),
            $request,
        )->searchable(['name'])->sortable(['name', 'models_count'])->paginate();

        $manufacturers->getCollection()->transform(fn (Manufacturer $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'models_count' => $m->models_count,
            'assets_count' => $m->assets_count,
        ]);

        return Inertia::render('manufacturers/index', [
            'manufacturers' => [
                'data' => $manufacturers->items(),
                'meta' => [
                    'current_page' => $manufacturers->currentPage(),
                    'last_page' => $manufacturers->lastPage(),
                    'per_page' => $manufacturers->perPage(),
                    'total' => $manufacturers->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('manufacturers/create');
    }

    public function store(ManufacturerRequest $request): RedirectResponse
    {
        Manufacturer::create($request->validated());

        return to_route('app.manufacturers.index')->with('success', 'Manufacturer created.');
    }

    public function edit(Manufacturer $manufacturer): Response
    {
        return Inertia::render('manufacturers/edit', [
            'manufacturer' => ['id' => $manufacturer->id, 'name' => $manufacturer->name],
        ]);
    }

    public function update(ManufacturerRequest $request, Manufacturer $manufacturer): RedirectResponse
    {
        $manufacturer->update($request->validated());

        return to_route('app.manufacturers.index')->with('success', 'Manufacturer updated.');
    }

    public function destroy(Manufacturer $manufacturer): RedirectResponse
    {
        $manufacturer->delete();

        return to_route('app.manufacturers.index')->with('success', 'Manufacturer deleted.');
    }
}

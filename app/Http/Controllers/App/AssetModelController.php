<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetModelRequest;
use App\Models\AssetModel;
use App\Models\Manufacturer;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetModelController extends Controller
{
    public function index(Request $request): Response
    {
        // leftJoin brings the manufacturer name into the same query so TableQuery
        // can sort/search on it without relation introspection; every sort/search
        // token is qualified/aliased to avoid the two-`name`-columns ambiguity.
        // select() must run BEFORE withCount(): Eloquent's select() overwrites the
        // entire column list, so calling it after withCount() would wipe out the
        // assets_count subselect that withCount() adds via addSelect().
        $query = AssetModel::query()
            ->leftJoin('manufacturers', 'manufacturers.id', '=', 'asset_models.manufacturer_id')
            ->select(
                'asset_models.id',
                'asset_models.name',
                'asset_models.manufacturer_id',
                'manufacturers.name as manufacturer_name',
            )
            ->withCount('assets');

        $models = TableQuery::for($query, $request)
            ->searchable(['asset_models.name', 'manufacturers.name'])
            ->sortable(['asset_models.name', 'manufacturer_name', 'assets_count'])
            ->filterable(['manufacturer_id' => 'asset_models.manufacturer_id'])
            ->paginate();

        return Inertia::render('asset-models/index', [
            'assetModels' => [
                'data' => $models->items(),
                'meta' => [
                    'current_page' => $models->currentPage(),
                    'last_page' => $models->lastPage(),
                    'per_page' => $models->perPage(),
                    'total' => $models->total(),
                ],
            ],
            'manufacturerOptions' => $this->manufacturerOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('asset-models/create', ['manufacturerOptions' => $this->manufacturerOptions()]);
    }

    public function store(AssetModelRequest $request): RedirectResponse
    {
        AssetModel::create($request->validated());

        return to_route('app.asset-models.index')->with('success', 'Asset model created.');
    }

    public function edit(AssetModel $assetModel): Response
    {
        return Inertia::render('asset-models/edit', [
            'assetModel' => [
                'id' => $assetModel->id,
                'name' => $assetModel->name,
                'manufacturer_id' => $assetModel->manufacturer_id,
            ],
            'manufacturerOptions' => $this->manufacturerOptions(),
        ]);
    }

    public function update(AssetModelRequest $request, AssetModel $assetModel): RedirectResponse
    {
        $assetModel->update($request->validated());

        return to_route('app.asset-models.index')->with('success', 'Asset model updated.');
    }

    public function destroy(AssetModel $assetModel): RedirectResponse
    {
        $assetModel->delete();

        return to_route('app.asset-models.index')->with('success', 'Asset model deleted.');
    }

    /** @return array<int, array{value: string, label: string}> */
    private function manufacturerOptions(): array
    {
        return Manufacturer::query()->orderBy('name')->get()
            ->map(fn (Manufacturer $m) => ['value' => $m->id, 'label' => $m->name])
            ->all();
    }
}

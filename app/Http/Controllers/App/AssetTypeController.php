<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetTypeRequest;
use App\Models\AssetType;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $assetTypes = TableQuery::for(AssetType::query(), $request)
            ->searchable(['name'])->sortable(['name'])->paginate();

        $assetTypes->getCollection()->transform(fn (AssetType $a) => [
            'id' => $a->id,
            'name' => $a->name,
        ]);

        return Inertia::render('asset-types/index', [
            'assetTypes' => [
                'data' => $assetTypes->items(),
                'meta' => [
                    'current_page' => $assetTypes->currentPage(),
                    'last_page' => $assetTypes->lastPage(),
                    'per_page' => $assetTypes->perPage(),
                    'total' => $assetTypes->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('asset-types/create');
    }

    public function store(AssetTypeRequest $request): RedirectResponse
    {
        AssetType::create($request->validated());

        return to_route('app.asset-types.index')->with('success', 'Asset type created.');
    }

    public function edit(AssetType $assetType): Response
    {
        return Inertia::render('asset-types/edit', ['assetType' => ['id' => $assetType->id, 'name' => $assetType->name]]);
    }

    public function update(AssetTypeRequest $request, AssetType $assetType): RedirectResponse
    {
        $assetType->update($request->validated());

        return to_route('app.asset-types.index')->with('success', 'Asset type updated.');
    }

    public function destroy(AssetType $assetType): RedirectResponse
    {
        $assetType->delete();

        return to_route('app.asset-types.index')->with('success', 'Asset type deleted.');
    }
}

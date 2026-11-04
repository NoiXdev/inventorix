<?php

namespace App\Http\Controllers\App;

use App\Enums\CategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetModelRequest;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CustomFieldset;
use App\Models\Depreciation;
use App\Models\Manufacturer;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetModelController extends Controller
{
    public function index(Request $request): Response
    {
        // leftJoin brings the manufacturer and category names into the same query
        // so TableQuery can sort/search on them without relation introspection;
        // every sort/search token is qualified/aliased to avoid the
        // several-`name`-columns ambiguity.
        // select() must run BEFORE withCount(): Eloquent's select() overwrites the
        // entire column list, so calling it after withCount() would wipe out the
        // assets_count subselect that withCount() adds via addSelect().
        $query = AssetModel::query()
            ->leftJoin('manufacturers', 'manufacturers.id', '=', 'asset_models.manufacturer_id')
            ->leftJoin('categories', 'categories.id', '=', 'asset_models.category_id')
            ->select(
                'asset_models.id',
                'asset_models.name',
                'asset_models.model_number',
                'asset_models.image',
                'asset_models.manufacturer_id',
                'asset_models.category_id',
                'manufacturers.name as manufacturer_name',
                'categories.name as category_name',
            )
            ->withCount('assets');

        $models = TableQuery::for($query, $request)
            ->searchable(['asset_models.name', 'asset_models.model_number', 'manufacturers.name'])
            ->sortable(['asset_models.name', 'asset_models.model_number', 'manufacturer_name', 'category_name', 'assets_count'])
            ->filterable([
                'manufacturer_id' => 'asset_models.manufacturer_id',
                'category_id' => 'asset_models.category_id',
            ])
            ->paginate();

        $models->getCollection()->transform(fn (AssetModel $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'model_number' => $m->model_number,
            'image_url' => $this->imageUrl($m),
            'manufacturer_id' => $m->manufacturer_id,
            'manufacturer_name' => $m->manufacturer_name,
            'category_name' => $m->category_name,
            'assets_count' => $m->assets_count,
        ]);

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
            'categoryOptions' => $this->categoryOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('asset-models/create', $this->formOptions());
    }

    public function store(AssetModelRequest $request): RedirectResponse
    {
        $model = AssetModel::create($this->attributes($request));

        if ($file = $request->file('image')) {
            $model->forceFill(['image' => $file->store('asset-models')])->save();
        }

        return to_route('app.asset-models.index')->with('success', 'Asset model created.');
    }

    public function edit(AssetModel $assetModel): Response
    {
        return Inertia::render('asset-models/edit', [
            'assetModel' => [
                'id' => $assetModel->id,
                'name' => $assetModel->name,
                'manufacturer_id' => $assetModel->manufacturer_id,
                'model_number' => $assetModel->model_number,
                'category_id' => $assetModel->category_id,
                'depreciation_id' => $assetModel->depreciation_id,
                'fieldset_id' => $assetModel->fieldset_id,
                'eol' => $assetModel->eol,
                'min_amt' => $assetModel->min_amt,
                'require_serial' => $assetModel->require_serial,
                'requestable' => $assetModel->requestable,
                'notes' => $assetModel->notes,
                'image_url' => $this->imageUrl($assetModel),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(AssetModelRequest $request, AssetModel $assetModel): RedirectResponse
    {
        $assetModel->update($this->attributes($request));

        $file = $request->file('image');
        $remove = $request->boolean('remove_image');

        if ($file || $remove) {
            $this->deleteImage($assetModel);

            $assetModel->forceFill([
                'image' => $file ? $file->store('asset-models') : null,
            ])->save();
        }

        return to_route('app.asset-models.index')->with('success', 'Asset model updated.');
    }

    public function destroy(AssetModel $assetModel): RedirectResponse
    {
        // Soft delete: assets keep pointing at the model, and the image stays
        // in place so a restored model gets it back.
        $assetModel->delete();

        return to_route('app.asset-models.index')->with('success', 'Asset model deleted.');
    }

    /**
     * Stream the image from the private disk, the way attachments are served.
     */
    public function image(AssetModel $assetModel): StreamedResponse
    {
        abort_unless($assetModel->image, 404);

        $disk = Storage::disk();
        abort_unless($disk->exists($assetModel->image), 404);

        return $disk->response($assetModel->image);
    }

    /**
     * The validated attributes that belong on the model — `image` is an upload
     * and `remove_image` a form-only flag, so neither may be mass-assigned.
     *
     * @return array<string, mixed>
     */
    private function attributes(AssetModelRequest $request): array
    {
        return collect($request->validated())
            ->except(['image', 'remove_image'])
            ->all();
    }

    private function deleteImage(AssetModel $assetModel): void
    {
        if ($assetModel->image) {
            Storage::disk()->delete($assetModel->image);
        }
    }

    private function imageUrl(AssetModel $assetModel): ?string
    {
        return $assetModel->image
            ? route('app.asset-models.image', $assetModel, absolute: false)
            : null;
    }

    /** @return array<string, list<array{value: string, label: string}>> */
    private function formOptions(): array
    {
        return [
            'manufacturerOptions' => $this->manufacturerOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'depreciationOptions' => Depreciation::query()->orderBy('name')->get()
                ->map(fn (Depreciation $d) => ['value' => $d->id, 'label' => $d->name])->all(),
            'fieldsetOptions' => CustomFieldset::query()->orderBy('name')->get()
                ->map(fn (CustomFieldset $s) => ['value' => $s->id, 'label' => $s->name])->all(),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function manufacturerOptions(): array
    {
        return Manufacturer::query()->orderBy('name')->get()
            ->map(fn (Manufacturer $m) => ['value' => $m->id, 'label' => $m->name])
            ->all();
    }

    /** @return list<array{value: string, label: string}> */
    private function categoryOptions(): array
    {
        return Category::query()->where('category_type', CategoryType::ASSET)->orderBy('name')->get()
            ->map(fn (Category $c) => ['value' => $c->id, 'label' => $c->name])
            ->all();
    }
}

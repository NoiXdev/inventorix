<?php

namespace App\Http\Controllers\App;

use App\Enums\AssetState;
use App\Enums\AttachmentCategory;
use App\Enums\BuyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetRequest;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\AssetType;
use App\Models\Attachment;
use App\Models\Incident;
use App\Models\Manufacturer;
use App\Models\Person;
use App\Models\Place;
use App\Support\Assets\AssetHistory;
use App\Support\Assets\AssetTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        // The joins + searchable/sortable/filterable whitelist live in
        // AssetTableQuery so the export controller can reuse the exact same
        // filtered/searched/sorted query. select() must run BEFORE
        // withCount(): Eloquent's select() overwrites the entire column list,
        // so calling it after withCount() would wipe out the incidents_count
        // subselect that withCount() adds via addSelect().
        $query = Asset::query()
            ->select(
                'assets.id', 'assets.state', 'assets.serial_number', 'assets.buy_price', 'assets.created_at',
                'asset_types.name as asset_type_name',
                'asset_models.name as model_name',
                'manufacturers.name as manufacturer_name',
                'people.name as owner_name',
            )
            ->withCount('incidents');

        $assets = AssetTableQuery::configure($query, $request)->paginate();

        $assets->getCollection()->transform(fn ($a) => [
            'id' => $a->id,
            'state' => $a->state->value,
            'state_label' => $a->state->getLabel(),
            'asset_type_name' => $a->asset_type_name,
            'manufacturer_name' => $a->manufacturer_name,
            'model_name' => $a->model_name,
            'owner_name' => $a->owner_name,
            'serial_number' => $a->serial_number,
            'buy_price' => $a->buy_price,
            'created_at' => optional($a->created_at)->format('d.m.Y'),
            'incidents_count' => $a->incidents_count,
        ]);

        return Inertia::render('assets/index', [
            'assets' => [
                'data' => $assets->items(),
                'meta' => [
                    'current_page' => $assets->currentPage(),
                    'last_page' => $assets->lastPage(),
                    'per_page' => $assets->perPage(),
                    'total' => $assets->total(),
                ],
            ],
            'stateOptions' => $this->stateOptions(),
            'assetTypeOptions' => $this->options(AssetType::query()),
            'manufacturerOptions' => $this->options(Manufacturer::query()),
            'importResult' => $request->session()->get('importResult'),
        ]);
    }

    public function create(Request $request): Response
    {
        $forceId = $request->query('forceId');
        if (! is_string($forceId) || ! Str::isUuid($forceId)) {
            $forceId = null;
        }

        return Inertia::render('assets/create', array_merge($this->formOptions(), [
            'forceId' => $forceId,
        ]));
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tags = $data['tags'] ?? [];
        $forcedId = $data['id'] ?? null;
        unset($data['tags'], $data['id']);

        $asset = new Asset($data);
        if (! empty($forcedId)) {
            $asset->id = $forcedId; // HasUuids only auto-generates when the key is empty
        }
        $asset->save();
        $asset->syncTags($tags);

        return to_route('app.assets.index')->with('success', 'Asset created.');
    }

    public function show(Asset $asset): Response
    {
        $asset->load('assetType', 'model.manufacturer', 'owner', 'place', 'tags', 'attachments.uploadedBy', 'incidents')
            ->loadCount('incidents');

        return Inertia::render('assets/show', [
            'asset' => $this->detail($asset),
            'history' => AssetHistory::for($asset),
            'incidents' => $asset->incidents->map(fn (Incident $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'notes' => $i->notes,
                'open_date' => optional($i->open_date)->format('Y-m-d'),
                'closed_date' => optional($i->closed_date)->format('Y-m-d'),
                'status' => $i->closed_date ? 'closed' : 'open',
                'created_at' => optional($i->created_at)->toDateTimeString(),
            ])->all(),
            'attachments' => $asset->attachments->map(fn (Attachment $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'category_label' => $a->category?->getLabel(),
                'title' => $a->title,
                'note' => $a->note,
                'original_name' => $a->original_name,
                'size' => $a->size,
                'size_label' => $this->humanSize((int) $a->size),
                'uploaded_by_name' => optional($a->uploadedBy)->name,
                'created_at' => optional($a->created_at)->toDateTimeString(),
                'url' => route('attachments.open', $a),
            ])->all(),
            'attachmentCategoryOptions' => array_map(
                fn (AttachmentCategory $c) => ['value' => $c->value, 'label' => $c->getLabel()],
                AttachmentCategory::cases(),
            ),
        ]);
    }

    public function edit(Asset $asset): Response
    {
        $asset->load('tags');

        return Inertia::render('assets/edit', array_merge($this->formOptions(), [
            'asset' => [
                'id' => $asset->id,
                'state' => $asset->state->value,
                'asset_type_id' => $asset->asset_type_id,
                'owner_id' => $asset->owner_id,
                'place_id' => $asset->place_id,
                'model_id' => $asset->model_id,
                'serial_number' => $asset->serial_number,
                'buy_date' => optional($asset->buy_date)->format('Y-m-d'),
                'guarantee_end' => optional($asset->guarantee_end)->format('Y-m-d'),
                'buy_type' => $asset->buy_type?->value,
                'buy_price' => $asset->buy_price !== null ? (string) $asset->buy_price : '',
                'invoice' => $asset->invoice,
                'tags' => $asset->tags->pluck('name')->all(),
            ],
        ]));
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $data = $request->validated();
        $tags = $data['tags'] ?? [];
        // A forced id is a create-only concern; never let it flow into an update
        // (the shared AssetForm carries an `id` field for the forced-create path).
        unset($data['tags'], $data['id']);

        $asset->update($data);
        $asset->syncTags($tags);

        return to_route('app.assets.index')->with('success', 'Asset updated.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $asset->delete();

        return to_route('app.assets.index')->with('success', 'Asset deleted.');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'stateOptions' => $this->stateOptions(),
            'buyTypeOptions' => array_map(fn (BuyType $b) => ['value' => $b->value, 'label' => $b->getLabel()], BuyType::cases()),
            'assetTypeOptions' => $this->options(AssetType::query()),
            'manufacturerOptions' => $this->options(Manufacturer::query()),
            'placeOptions' => $this->options(Place::query()),
            'ownerOptions' => Person::query()->orderBy('name')->get()
                ->map(fn (Person $p) => ['value' => $p->id, 'label' => $p->name])->all(),
            'modelOptions' => AssetModel::query()->with('manufacturer')->orderBy('name')->get()
                ->map(fn (AssetModel $m) => ['value' => $m->id, 'label' => '('.optional($m->manufacturer)->name.') '.$m->name])->all(),
        ];
    }

    /** @return array<int, array{value:string,label:string}> */
    private function stateOptions(): array
    {
        return array_map(fn (AssetState $s) => ['value' => $s->value, 'label' => $s->getLabel()], AssetState::cases());
    }

    /** @param Builder $query @return array<int, array{value:string,label:string}> */
    private function options($query): array
    {
        return $query->orderBy('name')->get()->map(fn ($m) => ['value' => $m->id, 'label' => $m->name])->all();
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        $kb = $bytes / 1024;
        if ($kb < 1024) {
            return rtrim(rtrim(number_format($kb, 1, '.', ''), '0'), '.').' KB';
        }

        return rtrim(rtrim(number_format($kb / 1024, 1, '.', ''), '0'), '.').' MB';
    }

    /** @return array<string, mixed> */
    private function detail(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'state' => $asset->state->value,
            'state_label' => $asset->state->getLabel(),
            'asset_type_name' => optional($asset->assetType)->name,
            'manufacturer_name' => optional(optional($asset->model)->manufacturer)->name,
            'model_name' => optional($asset->model)->name,
            'owner_name' => optional($asset->owner)->name,
            'place_name' => optional($asset->place)->name,
            'serial_number' => $asset->serial_number,
            'buy_date' => optional($asset->buy_date)->format('Y-m-d'),
            'guarantee_end' => optional($asset->guarantee_end)->format('Y-m-d'),
            'buy_type_label' => $asset->buy_type?->getLabel(),
            'buy_price' => $asset->buy_price,
            'invoice' => $asset->invoice,
            'tags' => $asset->tags->pluck('name')->all(),
            'incidentsCount' => $asset->incidents_count,
            'created_at' => optional($asset->created_at)->toDateTimeString(),
            'updated_at' => optional($asset->updated_at)->toDateTimeString(),
        ];
    }
}

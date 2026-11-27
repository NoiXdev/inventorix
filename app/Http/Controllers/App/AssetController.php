<?php

namespace App\Http\Controllers\App;

use App\Enums\AttachmentCategory;
use App\Enums\CategoryType;
use App\Enums\StatusLabelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\AssetRequest;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\Incident;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\StatusLabel;
use App\Models\Supplier;
use App\Services\CheckoutService;
use App\Support\Assets\AssetHistory;
use App\Support\Assets\AssetTableQuery;
use App\Support\Assets\CheckoutOptions;
use App\Support\CustomFields\AssetCustomFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $listed = AssetCustomFields::listed();

        $query = Asset::query()
            ->select(
                'assets.id', 'assets.name', 'assets.asset_tag', 'assets.status_id', 'assets.serial', 'assets.purchase_cost',
                'assets.archived', 'assets.created_at',
                'categories.name as category_name',
                'asset_models.name as model_name',
                'manufacturers.name as manufacturer_name',
                'status_labels.name as status_name',
                'status_labels.deployable as status_deployable',
                'status_labels.pending as status_pending',
                'status_labels.archived as status_archived',
                ...$listed->map(fn (CustomField $f) => 'assets.'.$f->db_column),
            )
            ->selectRaw(AssetTableQuery::assigneeNameSql().' as assignee_name')
            ->withCount('incidents');

        $assets = AssetTableQuery::configure($query, $request)->paginate();

        $seesIncidents = $request->user()->can('incidents.view');

        $assets->getCollection()->transform(fn ($a) => [
            'id' => $a->id,
            'status' => $a->status_id ? [
                'id' => $a->status_id,
                'name' => $a->status_name,
                'type' => StatusLabelType::fromFlags((bool) $a->status_deployable, (bool) $a->status_pending, (bool) $a->status_archived)->value,
            ] : null,
            'name' => $a->name,
            'asset_tag' => $a->asset_tag,
            'category_name' => $a->category_name,
            'manufacturer_name' => $a->manufacturer_name,
            'model_name' => $a->model_name,
            'assignee_name' => $a->assignee_name,
            'serial' => $a->serial,
            'purchase_cost' => $a->purchase_cost,
            'archived' => (bool) $a->archived,
            'created_at' => optional($a->created_at)->format('d.m.Y'),
            // The incident count is only exposed with incidents.view.
            ...($seesIncidents ? ['incidents_count' => $a->incidents_count] : []),
            'custom' => $listed->mapWithKeys(fn (CustomField $f) => [$f->db_column => $a->getAttribute($f->db_column)])->all(),
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
            'statusOptions' => StatusLabel::options(),
            'categoryOptions' => $this->options(Category::query()->where('category_type', CategoryType::ASSET)),
            'manufacturerOptions' => $this->options(Manufacturer::query()),
            'locationOptions' => Location::options(),
            'customColumns' => $listed->map(fn (CustomField $f) => ['key' => $f->db_column, 'label' => $f->name])->values()->all(),
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

    public function store(AssetRequest $request, CheckoutService $checkouts): RedirectResponse
    {
        $forcedId = $request->validated('id');

        DB::transaction(function () use ($request, $forcedId, $checkouts): void {
            $asset = new Asset($this->attributes($request));
            if (! empty($forcedId)) {
                $asset->id = $forcedId; // HasUuids only auto-generates when the key is empty
            }
            AssetCustomFields::fill($asset, $request->customFields(), $request->validated('custom_fields') ?? []);
            if ($file = $request->file('image')) {
                $asset->image = $file->store('assets');
            }
            $asset->save();
            $asset->syncTags($request->validated('tags') ?? []);

            // An assignee given on creation is a regular checkout.
            if ($class = $request->assigneeClass()) {
                $checkouts->checkOut(
                    $asset,
                    $class::query()->findOrFail($request->validated('assignee_id')),
                    expectedCheckin: $asset->expected_checkin,
                );
            }
        });

        return to_route('app.assets.index')->with('success', 'Asset created.');
    }

    public function show(Request $request, Asset $asset, CheckoutService $checkouts): Response
    {
        $asset->load('model.manufacturer', 'model.category', 'assignee', 'location', 'rtdLocation', 'supplier', 'status', 'tags', 'attachments.uploadedBy', 'incidents')
            ->loadCount('incidents');

        $props = [
            'asset' => $this->detail($asset, $request->user()->can('incidents.view')),
            'history' => AssetHistory::for($asset),
            // Loaded when a check-out or check-in dialog opens.
            'checkoutOptions' => Inertia::optional(fn () => CheckoutOptions::for($asset)),
            'checkoutBlocker' => $asset->assignee_id === null ? $checkouts->checkoutBlocker($asset) : null,
            'customFields' => AssetCustomFields::forModel($asset->model_id)
                ->map(fn (CustomField $f) => [
                    'name' => $f->name,
                    'value' => AssetCustomFields::plain($asset, $f),
                    'encrypted' => $f->field_encrypted,
                ])->values()->all(),
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
        ];

        // Incidents are a section of their own; only shown with incidents.view.
        if ($request->user()->can('incidents.view')) {
            $props['incidents'] = $asset->incidents->map(fn (Incident $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'notes' => $i->notes,
                'open_date' => optional($i->open_date)->format('Y-m-d'),
                'closed_date' => optional($i->closed_date)->format('Y-m-d'),
                'status' => $i->closed_date ? 'closed' : 'open',
                'created_at' => optional($i->created_at)->toDateTimeString(),
            ])->all();
        }

        return Inertia::render('assets/show', $props);
    }

    public function edit(Asset $asset): Response
    {
        $asset->load('tags');

        return Inertia::render('assets/edit', array_merge($this->formOptions($asset), [
            'asset' => [
                ...$asset->only([
                    'id', 'name', 'asset_tag', 'status_id', 'model_id', 'serial', 'assignee_id', 'location_id', 'rtd_location_id',
                    'supplier_id', 'order_number', 'warranty_months', 'eol_explicit', 'depreciate', 'physical',
                    'requestable', 'byod', 'archived', 'notes',
                ]),
                'assignee_type' => $asset->assigneeKey(),
                'assignee_name' => $asset->assigneeName(),
                'purchase_date' => $asset->purchase_date?->format('Y-m-d'),
                'purchase_cost' => $asset->purchase_cost !== null ? (string) $asset->purchase_cost : '',
                'asset_eol_date' => $asset->asset_eol_date?->format('Y-m-d'),
                'expected_checkin' => $asset->expected_checkin?->format('Y-m-d'),
                'last_audit_date' => $asset->last_audit_date?->format('Y-m-d'),
                'next_audit_date' => $asset->next_audit_date?->format('Y-m-d'),
                'image_url' => $this->imageUrl($asset),
                'tags' => $asset->tags->pluck('name')->all(),
                'custom_fields' => AssetCustomFields::values($asset, CustomField::query()->get()),
            ],
        ]));
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        DB::transaction(function () use ($request, $asset): void {
            // A forced id is a create-only concern; attributes() never includes it.
            // The assignee is not editable here: it changes through check-out/-in.
            $asset->fill($this->attributes($request));
            AssetCustomFields::fill($asset, $request->customFields(), $request->validated('custom_fields') ?? []);

            $file = $request->file('image');
            if ($file || $request->boolean('remove_image')) {
                $this->deleteImage($asset);
                $asset->image = $file?->store('assets');
            }

            $asset->save();
            $asset->syncTags($request->validated('tags') ?? []);
        });

        return to_route('app.assets.index')->with('success', 'Asset updated.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        // Soft delete: the image and attachments stay so the asset can be restored.
        $asset->delete();

        return to_route('app.assets.index')->with('success', 'Asset deleted.');
    }

    /**
     * Stream the image from the private disk, the way attachments are served.
     */
    public function image(Asset $asset): StreamedResponse
    {
        abort_unless($asset->image, 404);

        $disk = Storage::disk();
        abort_unless($disk->exists($asset->image), 404);

        return $disk->response($asset->image);
    }

    /**
     * The validated attributes that are mass-assignable: the assignee, image,
     * tags, custom fields and forced id are handled separately.
     *
     * @return array<string, mixed>
     */
    private function attributes(AssetRequest $request): array
    {
        return collect($request->validated())
            ->except(['id', 'assignee_type', 'assignee_id', 'image', 'remove_image', 'tags', 'custom_fields'])
            ->all();
    }

    private function deleteImage(Asset $asset): void
    {
        if ($asset->image) {
            Storage::disk()->delete($asset->image);
        }
    }

    private function imageUrl(Asset $asset): ?string
    {
        return $asset->image ? route('app.assets.image', $asset, absolute: false) : null;
    }

    /** @return array<string, mixed> */
    private function formOptions(?Asset $asset = null): array
    {
        // An asset whose status, location, supplier or assignee location was
        // deleted since still gets it offered, so the form shows it and saving
        // keeps it.
        $statuses = StatusLabel::options();
        if ($asset?->status_id && ! collect($statuses)->contains('value', $asset->status_id)) {
            $s = StatusLabel::withTrashed()->find($asset->status_id);
            $statuses[] = ['value' => $s->id, 'label' => (string) $s->name, 'type' => $s->type->value];
        }
        $liveLocations = Location::options();
        $locations = $liveLocations;
        if ($asset?->location_id && ! collect($locations)->contains('value', $asset->location_id)) {
            $locations[] = ['value' => $asset->location_id, 'label' => (string) Location::paths(withTrashed: true)->get($asset->location_id)];
        }

        $suppliers = $this->options(Supplier::query());
        if ($asset?->supplier_id && ! collect($suppliers)->contains('value', $asset->supplier_id)) {
            $suppliers[] = ['value' => $asset->supplier_id, 'label' => (string) $asset->supplier?->name];
        }
        $rtdLocations = $liveLocations;
        if ($asset?->rtd_location_id && ! collect($rtdLocations)->contains('value', $asset->rtd_location_id)) {
            $rtdLocations[] = ['value' => $asset->rtd_location_id, 'label' => (string) Location::paths(withTrashed: true)->get($asset->rtd_location_id)];
        }

        return [
            'statusOptions' => $statuses,
            'defaultStatusId' => StatusLabel::default()?->id,
            'locationOptions' => $locations,
            'rtdLocationOptions' => $rtdLocations,
            'supplierOptions' => $suppliers,
            // Only a new asset picks an assignee here.
            'assigneeOptions' => $asset ? null : CheckoutOptions::assignees(null, $liveLocations),
            'userCreateUrl' => route('app.users.quick-store'),
            'modelOptions' => AssetModel::query()->with('manufacturer')->orderBy('name')->get()
                ->map(fn (AssetModel $m) => ['value' => $m->id, 'label' => '('.optional($m->manufacturer)->name.') '.$m->name])->all(),
            // Model id => its fieldset's fields, so the form can swap the
            // custom field inputs when the model changes.
            'customFieldsByModel' => AssetModel::query()->whereNotNull('fieldset_id')->with('fieldset.fields')->get()
                ->filter(fn (AssetModel $m) => $m->fieldset !== null)
                ->mapWithKeys(fn (AssetModel $m) => [
                    $m->id => $m->fieldset->fields->map(fn (CustomField $f) => AssetCustomFields::definition($f))->values()->all(),
                ])->all(),
        ];
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
    private function detail(Asset $asset, bool $withIncidentsCount = true): array
    {
        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'asset_tag' => $asset->asset_tag,
            'status' => $asset->status ? ['id' => $asset->status->id, 'name' => $asset->status->name, 'type' => $asset->status->type->value] : null,
            'category_name' => $asset->model?->category?->name,
            'manufacturer_name' => optional(optional($asset->model)->manufacturer)->name,
            'model_name' => optional($asset->model)->name,
            'assignee' => $asset->assignee_type ? [
                'type' => $asset->assigneeKey(),
                'name' => $asset->assigneeName() ?? '(removed)',
                'url' => match ($asset->assigneeKey()) {
                    'user' => "/app/users/{$asset->assignee_id}",
                    'asset' => "/app/assets/{$asset->assignee_id}",
                    default => null,
                },
            ] : null,
            'location_name' => $asset->location ? Location::paths(withTrashed: true)->get($asset->location_id) : null,
            'rtd_location_name' => $asset->rtdLocation ? Location::paths(withTrashed: true)->get($asset->rtd_location_id) : null,
            'supplier_name' => $asset->supplier?->name,
            'serial' => $asset->serial,
            'purchase_date' => $asset->purchase_date?->format('Y-m-d'),
            'purchase_cost' => $asset->purchase_cost,
            'order_number' => $asset->order_number,
            'warranty_months' => $asset->warranty_months,
            'warranty_end' => $asset->warrantyEnd()?->format('Y-m-d'),
            'asset_eol_date' => $asset->asset_eol_date?->format('Y-m-d'),
            'eol_explicit' => $asset->eol_explicit,
            'depreciate' => $asset->depreciate,
            'physical' => $asset->physical,
            'requestable' => $asset->requestable,
            'byod' => $asset->byod,
            'archived' => (bool) $asset->archived,
            'notes' => $asset->notes,
            'image_url' => $this->imageUrl($asset),
            'last_checkout' => $asset->last_checkout?->toDateTimeString(),
            'last_checkin' => $asset->last_checkin?->toDateTimeString(),
            'expected_checkin' => $asset->expected_checkin?->format('Y-m-d'),
            'last_audit_date' => $asset->last_audit_date?->format('Y-m-d'),
            'next_audit_date' => $asset->next_audit_date?->format('Y-m-d'),
            'checkout_counter' => $asset->checkout_counter,
            'checkin_counter' => $asset->checkin_counter,
            'requests_counter' => $asset->requests_counter,
            'tags' => $asset->tags->pluck('name')->all(),
            ...($withIncidentsCount ? ['incidentsCount' => $asset->incidents_count] : []),
            'created_at' => optional($asset->created_at)->toDateTimeString(),
            'updated_at' => optional($asset->updated_at)->toDateTimeString(),
        ];
    }
}

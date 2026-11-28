<?php

namespace App\Http\Controllers\App;

use App\Enums\CategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\ComponentRequest;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Component;
use App\Models\ComponentAsset;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Supplier;
use App\Support\Components\ComponentHistory;
use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComponentController extends Controller
{
    public function index(Request $request): Response
    {
        $components = TableQuery::for(Component::query()->with(['category', 'manufacturer', 'location'])->withSum('checkouts as checked_out', 'assigned_qty'), $request)
            ->searchable(['name', 'model_number', 'serial'])
            ->sortable(['name', 'model_number', 'serial', 'qty', 'purchase_cost'])
            ->filterable(['category_id', 'manufacturer_id', 'location_id'])
            ->paginate();

        $paths = Location::paths(withTrashed: true);

        $components->getCollection()->transform(fn (Component $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'image_url' => $this->imageUrl($a),
            'category_name' => $a->category?->name,
            'manufacturer_name' => $a->manufacturer?->name,
            'model_number' => $a->model_number,
            'location_name' => $a->location_id ? $paths->get($a->location_id) : null,
            'qty' => $a->qty,
            'remaining' => $a->remaining(),
            'serial' => $a->serial,
            'min_amt' => $a->min_amt,
            'low_stock' => $a->isLowStock(),
            'purchase_cost' => $a->purchase_cost,
        ]);

        return Inertia::render('components/index', [
            'components' => [
                'data' => $components->items(),
                'meta' => [
                    'current_page' => $components->currentPage(),
                    'last_page' => $components->lastPage(),
                    'per_page' => $components->perPage(),
                    'total' => $components->total(),
                ],
            ],
            ...$this->relationOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('components/create', $this->relationOptions());
    }

    public function store(ComponentRequest $request): RedirectResponse
    {
        $component = Component::create($this->attributes($request));

        if ($file = $request->file('image')) {
            $component->forceFill(['image' => $file->store('components')])->save();
        }

        return to_route('app.components.index')->with('success', 'Component created.');
    }

    public function edit(Component $component): Response
    {
        return Inertia::render('components/edit', [
            'component' => [
                ...$component->only([
                    'id', 'name', 'category_id', 'manufacturer_id', 'model_number', 'serial', 'location_id',
                    'supplier_id', 'qty', 'min_amt', 'purchase_cost', 'order_number', 'notes',
                ]),
                'purchase_date' => $component->purchase_date?->format('Y-m-d'),
                'image_url' => $this->imageUrl($component),
            ],
            ...$this->relationOptions($component),
        ]);
    }

    public function update(ComponentRequest $request, Component $component): RedirectResponse
    {
        DB::transaction(function () use ($request, $component): void {
            // Recount under the lock check-outs take: one may have come in
            // since the request was validated.
            Component::query()->whereKey($component->id)->lockForUpdate()->first();
            $out = (int) $component->checkouts()->sum('assigned_qty');
            if ((int) $request->validated('qty') < $out) {
                throw ValidationException::withMessages(['qty' => "The quantity cannot be lower than the {$out} units installed."]);
            }
            $component->update($this->attributes($request));
        });

        $file = $request->file('image');
        $remove = $request->boolean('remove_image');

        if ($file || $remove) {
            $this->deleteImage($component);

            $component->forceFill([
                'image' => $file ? $file->store('components') : null,
            ])->save();
        }

        return to_route('app.components.index')->with('success', 'Component updated.');
    }

    public function show(Request $request, Component $component): Response
    {
        $component->load(['category', 'manufacturer', 'location', 'supplier', 'checkouts.asset'])->loadSum('checkouts as checked_out', 'assigned_qty');

        return Inertia::render('components/show', [
            'component' => [
                ...$component->only(['id', 'name', 'model_number', 'serial', 'qty', 'min_amt', 'purchase_cost', 'order_number', 'notes']),
                'purchase_date' => $component->purchase_date?->format('Y-m-d'),
                'image_url' => $this->imageUrl($component),
                'category_name' => $component->category?->name,
                'manufacturer_name' => $component->manufacturer?->name,
                'location_name' => $component->location_id ? Location::paths(withTrashed: true)->get($component->location_id) : null,
                'supplier_name' => $component->supplier?->name,
                'checked_out' => $component->checkedOutQty(),
                'remaining' => $component->remaining(),
                'low_stock' => $component->isLowStock(),
            ],
            'checkouts' => $component->checkouts->sortBy('checked_out_at')->values()->map(fn (ComponentAsset $c) => [
                'id' => $c->id,
                'qty' => $c->assigned_qty,
                'asset' => ['name' => $c->asset?->displayName(), 'url' => $c->asset ? "/app/assets/{$c->asset_id}" : null],
                'checked_out_at' => $c->checked_out_at?->format('Y-m-d'),
                'note' => $c->note,
            ])->all(),
            'history' => ComponentHistory::for($component),
            'checkoutOptions' => Inertia::optional(fn () => $request->user()->can('components.checkout') ? Asset::query()
                ->where('archived', false)->orderBy('name')->orderBy('asset_tag')->get(['id', 'name', 'asset_tag', 'serial'])
                ->map(fn (Asset $a) => ['value' => $a->id, 'label' => trim($a->displayName().($a->asset_tag && $a->name ? " ({$a->asset_tag})" : ''))])->all() : null),
        ]);
    }

    public function destroy(Component $component): RedirectResponse
    {
        $deleted = DB::transaction(function () use ($component): bool {
            Component::query()->whereKey($component->id)->lockForUpdate()->first();
            if ($component->checkouts()->exists()) {
                return false;
            }

            // The image is left in place on purpose: components are soft-deleted
            // and can be restored, so the file must outlive the row.
            return (bool) $component->delete();
        });
        if (! $deleted) {
            return back()->with('error', 'Check every unit in before deleting the component.');
        }

        return to_route('app.components.index')->with('success', 'Component deleted.');
    }

    /**
     * Stream the image from the private disk, the way attachments are served.
     */
    public function image(Component $component): StreamedResponse
    {
        abort_unless($component->image, 404);

        $disk = Storage::disk();
        abort_unless($disk->exists($component->image), 404);

        return $disk->response($component->image);
    }

    /**
     * Options for the relation pickers and filters. When editing, a related
     * record that has since been deleted (or a category whose type changed)
     * is still offered, so opening and saving the form doesn't fail on it.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function relationOptions(?Component $component = null): array
    {
        $named = fn (Builder $query, ?string $keep) => $query
            ->where(fn (Builder $q) => $q->whereNull('deleted_at')->when($keep, fn (Builder $q) => $q->orWhere('id', $keep)))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($m) => ['value' => $m->id, 'label' => $m->name])->values()->all();

        $locations = Location::options();
        if ($component?->location_id && ! collect($locations)->contains('value', $component->location_id)) {
            $locations[] = ['value' => $component->location_id, 'label' => Location::paths(withTrashed: true)->get($component->location_id)];
        }

        return [
            'categoryOptions' => $named(
                Category::withTrashed()->where(fn (Builder $q) => $q->where('category_type', CategoryType::COMPONENT->value)
                    ->when($component?->category_id, fn (Builder $q, $id) => $q->orWhere('id', $id))),
                $component?->category_id,
            ),
            'manufacturerOptions' => $named(Manufacturer::withTrashed(), $component?->manufacturer_id),
            'supplierOptions' => $named(Supplier::withTrashed(), $component?->supplier_id),
            'locationOptions' => $locations,
        ];
    }

    /**
     * The validated attributes that belong on the model — `image` is an upload
     * and `remove_image` a form-only flag, so neither may be mass-assigned.
     *
     * @return array<string, mixed>
     */
    private function attributes(ComponentRequest $request): array
    {
        return collect($request->validated())
            ->except(['image', 'remove_image'])
            ->all();
    }

    private function deleteImage(Component $component): void
    {
        if ($component->image) {
            Storage::disk()->delete($component->image);
        }
    }

    private function imageUrl(Component $component): ?string
    {
        return $component->image
            ? route('app.components.image', $component, absolute: false)
            : null;
    }
}

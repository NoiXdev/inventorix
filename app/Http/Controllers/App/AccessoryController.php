<?php

namespace App\Http\Controllers\App;

use App\Enums\CategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\AccessoryRequest;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Category;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Supplier;
use App\Support\Accessories\AccessoryHistory;
use App\Support\Assets\CheckoutOptions;
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

class AccessoryController extends Controller
{
    public function index(Request $request): Response
    {
        $accessories = TableQuery::for(Accessory::query()->with(['category', 'manufacturer', 'location'])->withCount('checkouts'), $request)
            ->searchable(['name', 'model_number'])
            ->sortable(['name', 'model_number', 'qty', 'default_purchase_cost'])
            ->filterable(['category_id', 'manufacturer_id', 'location_id', 'archived'])
            ->paginate();

        $paths = Location::paths(withTrashed: true);

        $accessories->getCollection()->transform(fn (Accessory $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'image_url' => $this->imageUrl($a),
            'category_name' => $a->category?->name,
            'manufacturer_name' => $a->manufacturer?->name,
            'model_number' => $a->model_number,
            'location_name' => $a->location_id ? $paths->get($a->location_id) : null,
            'qty' => $a->qty,
            'remaining' => $a->remaining(),
            'min_amt' => $a->min_amt,
            'low_stock' => $a->isLowStock(),
            'default_purchase_cost' => $a->default_purchase_cost,
            'requestable' => $a->requestable,
            'archived' => $a->archived,
        ]);

        return Inertia::render('accessories/index', [
            'accessories' => [
                'data' => $accessories->items(),
                'meta' => [
                    'current_page' => $accessories->currentPage(),
                    'last_page' => $accessories->lastPage(),
                    'per_page' => $accessories->perPage(),
                    'total' => $accessories->total(),
                ],
            ],
            ...$this->relationOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('accessories/create', $this->relationOptions());
    }

    public function store(AccessoryRequest $request): RedirectResponse
    {
        $accessory = Accessory::create($this->attributes($request));

        if ($file = $request->file('image')) {
            $accessory->forceFill(['image' => $file->store('accessories')])->save();
        }

        return to_route('app.accessories.index')->with('success', 'Accessory created.');
    }

    public function edit(Accessory $accessory): Response
    {
        return Inertia::render('accessories/edit', [
            'accessory' => [
                ...$accessory->only([
                    'id', 'name', 'category_id', 'manufacturer_id', 'model_number', 'location_id',
                    'default_supplier_id', 'qty', 'min_amt', 'default_purchase_cost', 'requestable', 'archived', 'notes',
                ]),
                'image_url' => $this->imageUrl($accessory),
            ],
            ...$this->relationOptions($accessory),
        ]);
    }

    public function update(AccessoryRequest $request, Accessory $accessory): RedirectResponse
    {
        DB::transaction(function () use ($request, $accessory): void {
            // Recount under the lock check-outs take: one may have come in
            // since the request was validated.
            Accessory::query()->whereKey($accessory->id)->lockForUpdate()->first();
            $out = $accessory->checkouts()->count();
            if ((int) $request->validated('qty') < $out) {
                throw ValidationException::withMessages(['qty' => "The quantity cannot be lower than the {$out} units that are checked out."]);
            }
            $accessory->update($this->attributes($request));
        });

        $file = $request->file('image');
        $remove = $request->boolean('remove_image');

        if ($file || $remove) {
            $this->deleteImage($accessory);

            $accessory->forceFill([
                'image' => $file ? $file->store('accessories') : null,
            ])->save();
        }

        return to_route('app.accessories.index')->with('success', 'Accessory updated.');
    }

    public function show(Request $request, Accessory $accessory): Response
    {
        $accessory->load(['category', 'manufacturer', 'location', 'defaultSupplier', 'checkouts.assigned'])->loadCount('checkouts');
        $canCheckout = $request->user()->can('accessories.checkout');

        return Inertia::render('accessories/show', [
            'accessory' => [
                ...$accessory->only(['id', 'name', 'model_number', 'qty', 'min_amt', 'default_purchase_cost', 'requestable', 'archived', 'notes']),
                'image_url' => $this->imageUrl($accessory),
                'category_name' => $accessory->category?->name,
                'manufacturer_name' => $accessory->manufacturer?->name,
                'location_name' => $accessory->location_id ? Location::paths(withTrashed: true)->get($accessory->location_id) : null,
                'supplier_name' => $accessory->defaultSupplier?->name,
                'remaining' => $accessory->remaining(),
                'low_stock' => $accessory->isLowStock(),
            ],
            'checkouts' => $accessory->checkouts->sortBy('checked_out_at')->values()->map(fn (AccessoryCheckout $c) => [
                'id' => $c->id,
                'holder' => [
                    'type' => $c->targetType(),
                    'name' => $c->holderName(),
                    'url' => match ($c->targetType()) {
                        'user' => "/app/users/{$c->assigned_id}",
                        'asset' => "/app/assets/{$c->assigned_id}",
                        default => null,
                    },
                ],
                'checked_out_at' => $c->checked_out_at?->format('Y-m-d'),
                'note' => $c->note,
            ])->all(),
            'history' => AccessoryHistory::for($accessory),
            'checkoutOptions' => Inertia::optional(fn () => $canCheckout ? CheckoutOptions::assignees(null) : null),
        ]);
    }

    public function destroy(Accessory $accessory): RedirectResponse
    {
        $deleted = DB::transaction(function () use ($accessory): bool {
            Accessory::query()->whereKey($accessory->id)->lockForUpdate()->first();
            if ($accessory->checkouts()->exists()) {
                return false;
            }

            // The image is left in place on purpose: accessories are soft-deleted
            // and can be restored, so the file must outlive the row.
            return (bool) $accessory->delete();
        });
        if (! $deleted) {
            return back()->with('error', 'Check every unit in before deleting the accessory.');
        }

        return to_route('app.accessories.index')->with('success', 'Accessory deleted.');
    }

    /**
     * Stream the image from the private disk, the way attachments are served.
     */
    public function image(Accessory $accessory): StreamedResponse
    {
        abort_unless($accessory->image, 404);

        $disk = Storage::disk();
        abort_unless($disk->exists($accessory->image), 404);

        return $disk->response($accessory->image);
    }

    /**
     * Options for the relation pickers and filters. When editing, a related
     * record that has since been deleted (or a category whose type changed)
     * is still offered, so opening and saving the form doesn't fail on it.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function relationOptions(?Accessory $accessory = null): array
    {
        $named = fn (Builder $query, ?string $keep) => $query
            ->where(fn (Builder $q) => $q->whereNull('deleted_at')->when($keep, fn (Builder $q) => $q->orWhere('id', $keep)))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($m) => ['value' => $m->id, 'label' => $m->name])->values()->all();

        $locations = Location::options();
        if ($accessory?->location_id && ! collect($locations)->contains('value', $accessory->location_id)) {
            $locations[] = ['value' => $accessory->location_id, 'label' => Location::paths(withTrashed: true)->get($accessory->location_id)];
        }

        return [
            'categoryOptions' => $named(
                Category::withTrashed()->where(fn (Builder $q) => $q->where('category_type', CategoryType::ACCESSORY->value)
                    ->when($accessory?->category_id, fn (Builder $q, $id) => $q->orWhere('id', $id))),
                $accessory?->category_id,
            ),
            'manufacturerOptions' => $named(Manufacturer::withTrashed(), $accessory?->manufacturer_id),
            'supplierOptions' => $named(Supplier::withTrashed(), $accessory?->default_supplier_id),
            'locationOptions' => $locations,
        ];
    }

    /**
     * The validated attributes that belong on the model — `image` is an upload
     * and `remove_image` a form-only flag, so neither may be mass-assigned.
     *
     * @return array<string, mixed>
     */
    private function attributes(AccessoryRequest $request): array
    {
        return collect($request->validated())
            ->except(['image', 'remove_image'])
            ->all();
    }

    private function deleteImage(Accessory $accessory): void
    {
        if ($accessory->image) {
            Storage::disk()->delete($accessory->image);
        }
    }

    private function imageUrl(Accessory $accessory): ?string
    {
        return $accessory->image
            ? route('app.accessories.image', $accessory, absolute: false)
            : null;
    }
}

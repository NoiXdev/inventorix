<?php

namespace App\Http\Controllers\App;

use App\Enums\CategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\ConsumableRequest;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\ConsumableAssignment;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Supplier;
use App\Support\Assets\CheckoutOptions;
use App\Support\Consumables\ConsumableHistory;
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

class ConsumableController extends Controller
{
    public function index(Request $request): Response
    {
        $consumables = TableQuery::for(Consumable::query()->with(['category', 'manufacturer', 'location'])->withSum('assignments as handed_out', 'qty'), $request)
            ->searchable(['name', 'model_number'])
            ->sortable(['name', 'model_number', 'qty', 'purchase_cost'])
            ->filterable(['category_id', 'manufacturer_id', 'location_id', 'archived'])
            ->paginate();

        $paths = Location::paths(withTrashed: true);

        $consumables->getCollection()->transform(fn (Consumable $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'image_url' => $this->imageUrl($a),
            'category_name' => $a->category?->name,
            'manufacturer_name' => $a->manufacturer?->name,
            'model_number' => $a->model_number,
            'location_name' => $a->location_id ? $paths->get($a->location_id) : null,
            'qty' => $a->qty,
            'remaining' => $a->remaining(),
            'item_no' => $a->item_no,
            'min_amt' => $a->min_amt,
            'low_stock' => $a->isLowStock(),
            'purchase_cost' => $a->purchase_cost,
            'requestable' => $a->requestable,
            'archived' => $a->archived,
        ]);

        return Inertia::render('consumables/index', [
            'consumables' => [
                'data' => $consumables->items(),
                'meta' => [
                    'current_page' => $consumables->currentPage(),
                    'last_page' => $consumables->lastPage(),
                    'per_page' => $consumables->perPage(),
                    'total' => $consumables->total(),
                ],
            ],
            ...$this->relationOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('consumables/create', $this->relationOptions());
    }

    public function store(ConsumableRequest $request): RedirectResponse
    {
        $consumable = Consumable::create($this->attributes($request));

        if ($file = $request->file('image')) {
            $consumable->forceFill(['image' => $file->store('consumables')])->save();
        }

        return to_route('app.consumables.index')->with('success', 'Consumable created.');
    }

    public function edit(Consumable $consumable): Response
    {
        return Inertia::render('consumables/edit', [
            'consumable' => [
                ...$consumable->only([
                    'id', 'name', 'category_id', 'manufacturer_id', 'model_number', 'item_no', 'location_id',
                    'supplier_id', 'qty', 'min_amt', 'purchase_cost', 'order_number', 'requestable', 'archived', 'notes',
                ]),
                'purchase_date' => $consumable->purchase_date?->format('Y-m-d'),
                'image_url' => $this->imageUrl($consumable),
            ],
            ...$this->relationOptions($consumable),
        ]);
    }

    public function update(ConsumableRequest $request, Consumable $consumable): RedirectResponse
    {
        DB::transaction(function () use ($request, $consumable): void {
            // Recount under the lock check-outs take: one may have come in
            // since the request was validated.
            Consumable::query()->whereKey($consumable->id)->lockForUpdate()->first();
            $out = (int) $consumable->assignments()->sum('qty');
            if ((int) $request->validated('qty') < $out) {
                throw ValidationException::withMessages(['qty' => "The quantity cannot be lower than the {$out} units already handed out."]);
            }
            $consumable->update($this->attributes($request));
        });

        $file = $request->file('image');
        $remove = $request->boolean('remove_image');

        if ($file || $remove) {
            $this->deleteImage($consumable);

            $consumable->forceFill([
                'image' => $file ? $file->store('consumables') : null,
            ])->save();
        }

        return to_route('app.consumables.index')->with('success', 'Consumable updated.');
    }

    public function show(Request $request, Consumable $consumable): Response
    {
        $consumable->load(['category', 'manufacturer', 'location', 'supplier'])->loadSum('assignments as handed_out', 'qty');
        $canCheckout = $request->user()->can('consumables.checkout');

        return Inertia::render('consumables/show', [
            'consumable' => [
                ...$consumable->only(['id', 'name', 'model_number', 'item_no', 'qty', 'min_amt', 'purchase_cost', 'order_number', 'requestable', 'archived', 'notes']),
                'purchase_date' => $consumable->purchase_date?->format('Y-m-d'),
                'image_url' => $this->imageUrl($consumable),
                'category_name' => $consumable->category?->name,
                'manufacturer_name' => $consumable->manufacturer?->name,
                'location_name' => $consumable->location_id ? Location::paths(withTrashed: true)->get($consumable->location_id) : null,
                'supplier_name' => $consumable->supplier?->name,
                'handed_out' => $consumable->handedOut(),
                'remaining' => $consumable->remaining(),
                'low_stock' => $consumable->isLowStock(),
            ],
            'assignments' => $consumable->assignments()->with('user')->latest('checked_out_at')->get()->map(fn (ConsumableAssignment $a) => [
                'id' => $a->id,
                'user_name' => $a->user?->name ?? $a->user?->email ?? $a->user_name,
                'user_url' => $a->user_id ? "/app/users/{$a->user_id}" : null,
                'qty' => $a->qty,
                'checked_out_at' => $a->checked_out_at?->format('Y-m-d'),
                'note' => $a->note,
            ])->all(),
            'history' => ConsumableHistory::for($consumable),
            'checkoutOptions' => Inertia::optional(fn () => $canCheckout ? ['user' => CheckoutOptions::assignees(null, [['value' => '', 'label' => '']])['user']] : null),
        ]);
    }

    public function destroy(Consumable $consumable): RedirectResponse
    {
        // Soft delete: what was handed out stays in the users' records. The
        // image is kept so a restore puts everything back.
        $consumable->delete();

        return to_route('app.consumables.index')->with('success', 'Consumable deleted.');
    }

    /**
     * Stream the image from the private disk, the way attachments are served.
     */
    public function image(Consumable $consumable): StreamedResponse
    {
        abort_unless($consumable->image, 404);

        $disk = Storage::disk();
        abort_unless($disk->exists($consumable->image), 404);

        return $disk->response($consumable->image);
    }

    /**
     * Options for the relation pickers and filters. When editing, a related
     * record that has since been deleted (or a category whose type changed)
     * is still offered, so opening and saving the form doesn't fail on it.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function relationOptions(?Consumable $consumable = null): array
    {
        $named = fn (Builder $query, ?string $keep) => $query
            ->where(fn (Builder $q) => $q->whereNull('deleted_at')->when($keep, fn (Builder $q) => $q->orWhere('id', $keep)))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($m) => ['value' => $m->id, 'label' => $m->name])->values()->all();

        $locations = Location::options();
        if ($consumable?->location_id && ! collect($locations)->contains('value', $consumable->location_id)) {
            $locations[] = ['value' => $consumable->location_id, 'label' => Location::paths(withTrashed: true)->get($consumable->location_id)];
        }

        return [
            'categoryOptions' => $named(
                Category::withTrashed()->where(fn (Builder $q) => $q->where('category_type', CategoryType::CONSUMABLE->value)
                    ->when($consumable?->category_id, fn (Builder $q, $id) => $q->orWhere('id', $id))),
                $consumable?->category_id,
            ),
            'manufacturerOptions' => $named(Manufacturer::withTrashed(), $consumable?->manufacturer_id),
            'supplierOptions' => $named(Supplier::withTrashed(), $consumable?->supplier_id),
            'locationOptions' => $locations,
        ];
    }

    /**
     * The validated attributes that belong on the model — `image` is an upload
     * and `remove_image` a form-only flag, so neither may be mass-assigned.
     *
     * @return array<string, mixed>
     */
    private function attributes(ConsumableRequest $request): array
    {
        return collect($request->validated())
            ->except(['image', 'remove_image'])
            ->all();
    }

    private function deleteImage(Consumable $consumable): void
    {
        if ($consumable->image) {
            Storage::disk()->delete($consumable->image);
        }
    }

    private function imageUrl(Consumable $consumable): ?string
    {
        return $consumable->image
            ? route('app.consumables.image', $consumable, absolute: false)
            : null;
    }
}

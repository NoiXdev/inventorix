<?php

namespace App\Http\Controllers\App;

use App\Enums\CategoryType;
use App\Exceptions\LicenseSeatException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\LicenseRequest;
use App\Models\Category;
use App\Models\Depreciation;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\Manufacturer;
use App\Models\Supplier;
use App\Services\LicenseService;
use App\Support\Assets\CheckoutOptions;
use App\Support\Licenses\LicenseHistory;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    public function __construct(private readonly LicenseService $licenses) {}

    public function index(Request $request): Response
    {
        $query = License::query()
            ->with(['category', 'manufacturer'])
            ->withCount(['freeSeats as free_seats_count']);

        $licenses = TableQuery::for($query, $request)
            ->searchable(['name', 'licensed_to_name', 'order_number'])
            ->sortable(['name', 'seats', 'expiration_date', 'purchase_date'])
            ->filterable(['category_id', 'manufacturer_id'])
            ->paginate();

        $licenses->getCollection()->transform(fn (License $l) => [
            'id' => $l->id,
            'name' => $l->name,
            'category_name' => $l->category?->name,
            'manufacturer_name' => $l->manufacturer?->name,
            'licensed_to_name' => $l->licensed_to_name,
            'seats' => $l->seats,
            'free_seats' => (int) $l->free_seats_count,
            'low' => $l->isLow((int) $l->free_seats_count),
            'expiration_date' => $l->expiration_date?->format('Y-m-d'),
            'expired' => $l->expiration_date !== null && $l->expiration_date->isPast(),
        ]);

        return Inertia::render('licenses/index', [
            'licenses' => [
                'data' => $licenses->items(),
                'meta' => [
                    'current_page' => $licenses->currentPage(), 'last_page' => $licenses->lastPage(),
                    'per_page' => $licenses->perPage(), 'total' => $licenses->total(),
                ],
            ],
            'categoryOptions' => $this->categoryOptions(),
            'manufacturerOptions' => $this->options(Manufacturer::query()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('licenses/create', $this->formOptions());
    }

    public function store(LicenseRequest $request): RedirectResponse
    {
        $license = DB::transaction(function () use ($request): License {
            $license = License::create($request->licenseAttributes());
            $this->licenses->syncSeats($license, (int) $request->validated('seats'));

            return $license;
        });

        return to_route('app.licenses.show', $license)->with('success', 'License created.');
    }

    public function show(Request $request, License $license): Response
    {
        $license->load(['category', 'manufacturer', 'supplier', 'depreciation', 'licenseSeats.user', 'licenseSeats.asset']);
        $seats = $license->licenseSeats->sortBy(fn (LicenseSeat $s) => [$s->isCheckedOut() ? 0 : ($s->retired ? 2 : 1), $s->created_at])->values();
        $free = $seats->filter(fn (LicenseSeat $s) => ! $s->isCheckedOut() && ! $s->retired)->count();
        $canCheckout = $request->user()->can('licenses.checkout');

        return Inertia::render('licenses/show', [
            'license' => [
                ...$this->payload($license),
                'product_key' => $request->user()->can('licenses.keys') ? $license->readableProductKey() : null,
                'has_product_key' => $license->getRawOriginal('product_key') !== null,
                'category_name' => $license->category?->name,
                'manufacturer_name' => $license->manufacturer?->name,
                'supplier_name' => $license->supplier?->name,
                'depreciation_name' => $license->depreciation?->name,
                'free_seats' => $free,
                'low' => $license->isLow($free),
            ],
            'seats' => $seats->map(fn (LicenseSeat $s) => [
                'id' => $s->id,
                'retired' => $s->retired,
                'holder' => $s->isCheckedOut() ? [
                    'type' => $s->assigned_user_id ? 'user' : 'asset',
                    'name' => $s->holderName(),
                    'url' => $s->assigned_user_id ? "/app/users/{$s->assigned_user_id}" : "/app/assets/{$s->assigned_asset_id}",
                ] : null,
                'checked_out_at' => $s->checked_out_at?->format('Y-m-d'),
                'notes' => $s->notes,
            ])->all(),
            'history' => LicenseHistory::for($license),
            // Users and assets for the check-out dialog, loaded when it opens.
            'checkoutOptions' => Inertia::optional(fn () => $canCheckout
                ? collect(CheckoutOptions::assignees(null, [['value' => '', 'label' => '']]))->only(['user', 'asset'])->all()
                : null),
        ]);
    }

    public function edit(Request $request, License $license): Response
    {
        return Inertia::render('licenses/edit', [
            ...$this->formOptions(),
            'license' => [
                ...$this->payload($license),
                'product_key' => $request->user()->can('licenses.keys') ? $license->readableProductKey() : null,
            ],
        ]);
    }

    public function update(LicenseRequest $request, License $license): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $license): void {
                $license->fill($request->licenseAttributes())->save();
                $this->licenses->syncSeats($license, (int) $request->validated('seats'));
            });
        } catch (LicenseSeatException $e) {
            throw ValidationException::withMessages(['seats' => $e->getMessage()]);
        }

        return to_route('app.licenses.show', $license)->with('success', 'License updated.');
    }

    public function destroy(License $license): RedirectResponse
    {
        $deleted = DB::transaction(function () use ($license): bool {
            // Under the license lock, so no seat is checked out meanwhile.
            License::query()->whereKey($license->id)->lockForUpdate()->first();
            if ($license->licenseSeats()->where(fn ($q) => $q->whereNotNull('assigned_user_id')->orWhereNotNull('assigned_asset_id'))->exists()) {
                return false;
            }

            return (bool) $license->delete();
        });
        if (! $deleted) {
            return back()->with('error', 'Check all seats in before deleting the license.');
        }

        return to_route('app.licenses.index')->with('success', 'License deleted.');
    }

    /** @return array<string, mixed> */
    private function payload(License $license): array
    {
        return [
            ...$license->only([
                'id', 'name', 'seats', 'category_id', 'manufacturer_id', 'supplier_id', 'depreciation_id',
                'licensed_to_name', 'licensed_to_email', 'reassignable', 'maintained', 'requestable',
                'order_number', 'purchase_order', 'min_amt', 'notes',
            ]),
            'purchase_cost' => $license->purchase_cost,
            'purchase_date' => $license->purchase_date?->format('Y-m-d'),
            'expiration_date' => $license->expiration_date?->format('Y-m-d'),
            'termination_date' => $license->termination_date?->format('Y-m-d'),
        ];
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'categoryOptions' => $this->categoryOptions(),
            'manufacturerOptions' => $this->options(Manufacturer::query()),
            'supplierOptions' => $this->options(Supplier::query()),
            'depreciationOptions' => $this->options(Depreciation::query()),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function categoryOptions(): array
    {
        return $this->options(Category::query()->where('category_type', CategoryType::LICENSE));
    }

    /** @return list<array{value: string, label: string}> */
    private function options($query): array
    {
        return $query->orderBy('name')->get()->map(fn ($m) => ['value' => $m->id, 'label' => $m->name])->all();
    }
}

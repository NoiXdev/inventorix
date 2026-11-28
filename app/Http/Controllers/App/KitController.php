<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\KitRequest;
use App\Models\Kit;
use App\Models\KitItem;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KitController extends Controller
{
    public function index(Request $request): Response
    {
        $kits = TableQuery::for(Kit::query()->withCount('items'), $request)
            ->searchable(['name'])
            ->sortable(['name'])
            ->paginate();

        $kits->getCollection()->transform(fn (Kit $k) => [
            'id' => $k->id,
            'name' => $k->name,
            'items_count' => $k->items_count,
        ]);

        return Inertia::render('kits/index', [
            'kits' => [
                'data' => $kits->items(),
                'meta' => [
                    'current_page' => $kits->currentPage(),
                    'last_page' => $kits->lastPage(),
                    'per_page' => $kits->perPage(),
                    'total' => $kits->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('kits/create', $this->itemOptions());
    }

    public function store(KitRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $kit = Kit::create($request->safe()->only(['name', 'notes']));
            $this->syncItems($kit, $request->validated('items') ?? []);
        });

        return to_route('app.kits.index')->with('success', 'Kit created.');
    }

    public function show(Request $request, Kit $kit): Response
    {
        return Inertia::render('kits/show', [
            'kit' => $kit->only(['id', 'name', 'notes']),
            'lines' => $kit->lines()->map(fn (KitItem $line) => [
                'id' => $line->id,
                'type' => $line->typeKey(),
                'item_id' => $line->item_id,
                'name' => $line->item?->name,
                'qty' => $line->qty,
                'available' => $line->isAvailable(),
                'url' => $line->isAvailable() ? $this->itemUrl($line) : null,
            ])->values()->all(),
        ]);
    }

    public function edit(Kit $kit): Response
    {
        return Inertia::render('kits/edit', [
            'kit' => [
                ...$kit->only(['id', 'name', 'notes']),
                'items' => $kit->lines()->map(fn (KitItem $line) => [
                    'type' => $line->typeKey(),
                    'id' => $line->item_id,
                    'qty' => $line->qty,
                ])->values()->all(),
            ],
            ...$this->itemOptions($kit),
        ]);
    }

    public function update(KitRequest $request, Kit $kit): RedirectResponse
    {
        DB::transaction(function () use ($request, $kit): void {
            $kit->update($request->safe()->only(['name', 'notes']));
            $this->syncItems($kit, $request->validated('items') ?? []);
        });

        return to_route('app.kits.index')->with('success', 'Kit updated.');
    }

    public function destroy(Kit $kit): RedirectResponse
    {
        $kit->delete();

        return to_route('app.kits.index')->with('success', 'Kit deleted.');
    }

    /**
     * Make the kit's lines exactly `$lines`: lines that are still wanted keep
     * their row (only the quantity changes), the rest are removed or added.
     *
     * @param  list<array{type: string, id: string, qty: int|string}>  $lines
     */
    private function syncItems(Kit $kit, array $lines): void
    {
        $wanted = collect($lines)->mapWithKeys(fn (array $l) => [KitItem::TYPES[$l['type']].'|'.$l['id'] => $l]);

        foreach ($kit->items()->get() as $existing) {
            $key = "{$existing->item_type}|{$existing->item_id}";
            if ($wanted->has($key)) {
                $existing->update(['qty' => (int) $wanted[$key]['qty']]);
                $wanted->forget($key);
            } else {
                $existing->delete();
            }
        }

        foreach ($wanted as $line) {
            $kit->items()->create(['item_type' => KitItem::TYPES[$line['type']], 'item_id' => $line['id'], 'qty' => (int) $line['qty']]);
        }
    }

    private function itemUrl(KitItem $line): ?string
    {
        return match ($line->typeKey()) {
            'license' => "/app/licenses/{$line->item_id}",
            'accessory' => "/app/accessories/{$line->item_id}",
            'consumable' => "/app/consumables/{$line->item_id}",
            default => null, // asset models have no page of their own
        };
    }

    /**
     * The pickers of the line editor, one list per type. When editing, items
     * the kit already holds are offered even if deleted since, so the form can
     * show them and still be saved.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function itemOptions(?Kit $kit = null): array
    {
        $held = $kit ? $kit->items()->get(['item_type', 'item_id'])->groupBy('item_type') : collect();

        $options = [];
        foreach (KitItem::TYPES as $key => $class) {
            $keep = $held->get($class, collect())->pluck('item_id')->all();
            $options["{$key}Options"] = $class::withTrashed()
                ->where(fn ($q) => $q->whereNull('deleted_at')->orWhereIn('id', $keep))
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($m) => ['value' => $m->id, 'label' => $m->name])->values()->all();
        }

        return $options;
    }
}

<?php

namespace App\Http\Controllers\App;

use App\Enums\DepreciationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\DepreciationRequest;
use App\Models\Depreciation;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepreciationController extends Controller
{
    public function index(Request $request): Response
    {
        $depreciations = TableQuery::for(Depreciation::query(), $request)
            ->searchable(['name'])->sortable(['name', 'months', 'depreciation_type'])->paginate();

        $depreciations->getCollection()->transform(fn (Depreciation $d) => [
            ...$this->present($d),
            'depreciation_type_label' => $d->depreciation_type->getLabel(),
        ]);

        return Inertia::render('depreciations/index', [
            'depreciations' => [
                'data' => $depreciations->items(),
                'meta' => [
                    'current_page' => $depreciations->currentPage(),
                    'last_page' => $depreciations->lastPage(),
                    'per_page' => $depreciations->perPage(),
                    'total' => $depreciations->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('depreciations/create', ['typeOptions' => $this->typeOptions()]);
    }

    public function store(DepreciationRequest $request): RedirectResponse
    {
        Depreciation::create($request->validated());

        return to_route('app.depreciations.index')->with('success', 'Depreciation created.');
    }

    public function edit(Depreciation $depreciation): Response
    {
        return Inertia::render('depreciations/edit', [
            'depreciation' => $this->present($depreciation),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function update(DepreciationRequest $request, Depreciation $depreciation): RedirectResponse
    {
        $depreciation->update($request->validated());

        return to_route('app.depreciations.index')->with('success', 'Depreciation updated.');
    }

    public function destroy(Depreciation $depreciation): RedirectResponse
    {
        $depreciation->delete();

        return to_route('app.depreciations.index')->with('success', 'Depreciation deleted.');
    }

    private function present(Depreciation $d): array
    {
        return [
            'id' => $d->id,
            'name' => $d->name,
            'months' => $d->months,
            'depreciation_min' => $d->depreciation_min,
            'fiscal_year_start_month' => $d->fiscal_year_start_month,
            'rate_multiplier' => $d->rate_multiplier,
            'depreciation_type' => $d->depreciation_type->value,
        ];
    }

    /** @return list<array{value: string, label: ?string}> */
    private function typeOptions(): array
    {
        return array_map(
            fn (DepreciationType $t) => ['value' => $t->value, 'label' => $t->getLabel()],
            DepreciationType::cases(),
        );
    }
}

<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusLabelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\StatusLabelRequest;
use App\Models\StatusLabel;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StatusLabelController extends Controller
{
    public function index(Request $request): Response
    {
        $labels = TableQuery::for(StatusLabel::query()->withCount('assets'), $request)
            ->searchable(['name'])
            ->sortable(['name', 'assets_count'])
            ->paginate();

        $labels->getCollection()->transform(fn (StatusLabel $l) => [
            'id' => $l->id,
            'name' => $l->name,
            'type' => $l->type->value,
            'type_label' => $l->type->getLabel(),
            'show_in_nav' => (bool) $l->show_in_nav,
            'default_label' => (bool) $l->default_label,
            'system' => $l->isSystem(),
            'assets_count' => $l->assets_count,
        ]);

        return Inertia::render('status-labels/index', [
            'labels' => [
                'data' => $labels->items(),
                'meta' => [
                    'current_page' => $labels->currentPage(),
                    'last_page' => $labels->lastPage(),
                    'per_page' => $labels->perPage(),
                    'total' => $labels->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('status-labels/create', ['typeOptions' => $this->typeOptions()]);
    }

    public function store(StatusLabelRequest $request): RedirectResponse
    {
        DB::transaction(fn () => $this->keepSingleDefault(StatusLabel::create($request->labelAttributes())));

        return to_route('app.status-labels.index')->with('success', 'Status label created.');
    }

    public function edit(StatusLabel $statusLabel): Response
    {
        return Inertia::render('status-labels/edit', [
            'label' => [
                'id' => $statusLabel->id,
                'name' => $statusLabel->name,
                'type' => $statusLabel->type->value,
                'notes' => $statusLabel->notes,
                'show_in_nav' => (bool) $statusLabel->show_in_nav,
                'default_label' => (bool) $statusLabel->default_label,
                'system' => $statusLabel->isSystem(),
            ],
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function update(StatusLabelRequest $request, StatusLabel $statusLabel): RedirectResponse
    {
        DB::transaction(function () use ($request, $statusLabel) {
            $statusLabel->update($request->labelAttributes());
            $this->keepSingleDefault($statusLabel);
        });

        return to_route('app.status-labels.index')->with('success', 'Status label updated.');
    }

    public function destroy(StatusLabel $statusLabel): RedirectResponse
    {
        // Handovers move assets into and out of the system labels by their key.
        if ($statusLabel->isSystem()) {
            return back()->with('error', 'This status is used by handovers and cannot be deleted. Rename it instead.');
        }

        // Soft delete: assets keep pointing here, and a restore puts it back.
        $statusLabel->delete();

        return to_route('app.status-labels.index')->with('success', 'Status label deleted.');
    }

    /** Only one label can be the default for new assets. */
    private function keepSingleDefault(StatusLabel $label): void
    {
        if ($label->default_label) {
            StatusLabel::query()->whereKeyNot($label->id)->where('default_label', true)->update(['default_label' => false]);
        }
    }

    /** @return list<array{value: string, label: string}> */
    private function typeOptions(): array
    {
        return array_map(
            fn (StatusLabelType $t) => ['value' => $t->value, 'label' => $t->getLabel()],
            StatusLabelType::cases(),
        );
    }
}

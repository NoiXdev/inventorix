<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\CustomFieldsetRequest;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomFieldsetController extends Controller
{
    public function index(Request $request): Response
    {
        $fieldsets = TableQuery::for(CustomFieldset::query()->withCount('fields'), $request)
            ->searchable(['name'])
            ->sortable(['name', 'fields_count'])
            ->paginate();

        $fieldsets->getCollection()->transform(fn (CustomFieldset $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'fields_count' => $s->fields_count,
        ]);

        return Inertia::render('custom-fieldsets/index', [
            'customFieldsets' => [
                'data' => $fieldsets->items(),
                'meta' => [
                    'current_page' => $fieldsets->currentPage(),
                    'last_page' => $fieldsets->lastPage(),
                    'per_page' => $fieldsets->perPage(),
                    'total' => $fieldsets->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        $available = $this->availableFields();

        return Inertia::render('custom-fieldsets/create', [
            'availableFields' => $available,
            // Fields flagged auto_add_to_fieldsets start out selected in a new
            // fieldset; the user can still take them out before saving.
            'initialFields' => collect($available)
                ->where('auto_add_to_fieldsets', true)
                ->map(fn (array $f) => ['id' => $f['id'], 'required' => false])
                ->values(),
        ]);
    }

    public function store(CustomFieldsetRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $fieldset = CustomFieldset::create(['name' => $request->validated('name')]);
            $fieldset->fields()->sync($request->pivot());
        });

        return to_route('app.custom-fieldsets.index')->with('success', 'Fieldset created.');
    }

    public function edit(CustomFieldset $customFieldset): Response
    {
        return Inertia::render('custom-fieldsets/edit', [
            'customFieldset' => [
                'id' => $customFieldset->id,
                'name' => $customFieldset->name,
                'fields' => $customFieldset->fields->map(fn (CustomField $f) => [
                    'id' => $f->id,
                    'required' => (bool) $f->pivot->required,
                ]),
            ],
            'availableFields' => $this->availableFields(),
        ]);
    }

    public function update(CustomFieldsetRequest $request, CustomFieldset $customFieldset): RedirectResponse
    {
        DB::transaction(function () use ($request, $customFieldset) {
            $customFieldset->update(['name' => $request->validated('name')]);
            $customFieldset->fields()->sync($request->pivot());
        });

        return to_route('app.custom-fieldsets.index')->with('success', 'Fieldset updated.');
    }

    public function destroy(CustomFieldset $customFieldset): RedirectResponse
    {
        $customFieldset->delete();

        return to_route('app.custom-fieldsets.index')->with('success', 'Fieldset deleted.');
    }

    /** @return list<array{id: string, name: string, element_label: ?string, auto_add_to_fieldsets: bool}> */
    private function availableFields(): array
    {
        return CustomField::query()->orderBy('name')->get()
            ->map(fn (CustomField $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'element_label' => $f->element->getLabel(),
                'auto_add_to_fieldsets' => $f->auto_add_to_fieldsets,
            ])
            ->all();
    }
}

<?php

namespace App\Http\Controllers\App;

use App\Enums\CustomFieldElement;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\CustomFieldRequest;
use App\Models\CustomField;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomFieldController extends Controller
{
    public function index(Request $request): Response
    {
        $fields = TableQuery::for(CustomField::query()->withCount('fieldsets'), $request)
            ->searchable(['name'])
            ->sortable(['name', 'element', 'fieldsets_count'])
            ->filterable(['element'])
            ->paginate();

        $fields->getCollection()->transform(fn (CustomField $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'element' => $f->element->value,
            'element_label' => $f->element->getLabel(),
            'format' => $f->formatName(),
            'is_unique' => $f->is_unique,
            'fieldsets_count' => $f->fieldsets_count,
        ]);

        return Inertia::render('custom-fields/index', [
            'customFields' => [
                'data' => $fields->items(),
                'meta' => [
                    'current_page' => $fields->currentPage(),
                    'last_page' => $fields->lastPage(),
                    'per_page' => $fields->perPage(),
                    'total' => $fields->total(),
                ],
            ],
            'elementOptions' => $this->elementOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('custom-fields/create', $this->formOptions());
    }

    public function store(CustomFieldRequest $request): RedirectResponse
    {
        CustomField::create($request->storedAttributes());

        return to_route('app.custom-fields.index')->with('success', 'Custom field created.');
    }

    public function edit(CustomField $customField): Response
    {
        return Inertia::render('custom-fields/edit', [
            'customField' => [
                'id' => $customField->id,
                'name' => $customField->name,
                'db_column' => $customField->db_column,
                'element' => $customField->element->value,
                'format' => $customField->formatName(),
                // Shown without the `regex:` prefix the rule is stored with.
                'custom_regex' => $customField->customRegex() ? substr($customField->customRegex(), strlen('regex:')) : null,
                'field_values' => $customField->field_values,
                'help_text' => $customField->help_text,
                'field_encrypted' => $customField->field_encrypted,
                'show_in_email' => $customField->show_in_email,
                'show_in_requestable_list' => $customField->show_in_requestable_list,
                'is_unique' => $customField->is_unique,
                'display_in_user_view' => $customField->display_in_user_view,
                'auto_add_to_fieldsets' => $customField->auto_add_to_fieldsets,
                'show_in_listview' => $customField->show_in_listview,
                'display_checkin' => $customField->display_checkin,
                'display_audit' => $customField->display_audit,
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(CustomFieldRequest $request, CustomField $customField): RedirectResponse
    {
        $customField->update($request->storedAttributes());

        return to_route('app.custom-fields.index')->with('success', 'Custom field updated.');
    }

    public function destroy(CustomField $customField): RedirectResponse
    {
        // Soft delete: the field disappears from its fieldsets (the relation
        // skips trashed fields) but comes back with them if restored. Its
        // assets column and values stay; only a force delete drops them.
        $customField->delete();

        return to_route('app.custom-fields.index')->with('success', 'Custom field deleted.');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'elementOptions' => $this->elementOptions(),
            'formatOptions' => array_map(
                fn (string $name) => ['value' => $name, 'label' => $name],
                array_keys(CustomField::PREDEFINED_FORMATS),
            ),
            'textOnlyFormats' => [...CustomField::TEXT_ONLY_FORMATS, 'CUSTOM REGEX'],
        ];
    }

    /** @return list<array{value: string, label: ?string}> */
    private function elementOptions(): array
    {
        return array_map(
            fn (CustomFieldElement $e) => ['value' => $e->value, 'label' => $e->getLabel()],
            CustomFieldElement::cases(),
        );
    }
}

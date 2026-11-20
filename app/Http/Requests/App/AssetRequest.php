<?php

namespace App\Http\Requests\App;

use App\Models\Asset;
use App\Models\CustomField;
use App\Support\CustomFields\AssetCustomFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Normalize empty-string selects/inputs to null before validation. */
    protected function prepareForValidation(): void
    {
        $nullable = [
            'id', 'name', 'asset_tag', 'serial', 'assignee_type', 'assignee_id', 'location_id', 'rtd_location_id',
            'model_id', 'supplier_id', 'purchase_date', 'purchase_cost', 'order_number', 'warranty_months',
            'asset_eol_date', 'notes', 'expected_checkin', 'last_audit_date', 'next_audit_date',
        ];
        $this->merge(collect($this->only($nullable))
            ->map(fn ($v) => $v === '' ? null : $v)
            ->all());
    }

    public function rules(): array
    {
        $asset = $this->route('asset');

        return [
            'id' => ['nullable', 'uuid', 'unique:assets,id'],
            'name' => ['nullable', 'string', 'max:191'],
            // Unique among assets that are not deleted.
            'asset_tag' => ['nullable', 'string', 'max:191', Rule::unique('assets', 'asset_tag')
                ->ignore($asset instanceof Asset ? $asset->id : null)->whereNull('deleted_at')],
            'status_id' => ['required', 'uuid', $this->liveOrUnchanged('status_id', 'status_labels')],
            'model_id' => ['nullable', 'uuid', 'exists:asset_models,id'],
            'serial' => ['nullable', 'string', 'max:191'],

            'assignee_type' => ['nullable', Rule::in(array_keys(Asset::ASSIGNEE_TYPES))],
            'assignee_id' => ['nullable', 'required_with:assignee_type', 'uuid', ...$this->assigneeRules()],
            'location_id' => ['nullable', 'uuid', $this->liveOrUnchanged('location_id', 'locations')],
            'rtd_location_id' => ['nullable', 'uuid', $this->liveOrUnchanged('rtd_location_id', 'locations')],

            'supplier_id' => ['nullable', 'uuid', $this->liveOrUnchanged('supplier_id', 'suppliers')],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999.99', 'decimal:0,2'],
            'order_number' => ['nullable', 'string', 'max:191'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:1200'],
            'eol_explicit' => ['boolean'],
            'asset_eol_date' => ['nullable', 'date', 'required_if_accepted:eol_explicit'],
            'depreciate' => ['boolean'],

            'physical' => ['boolean'],
            'requestable' => ['boolean'],
            'byod' => ['boolean'],
            'archived' => ['boolean'],
            'expected_checkin' => ['nullable', 'date'],
            'last_audit_date' => ['nullable', 'date'],
            'next_audit_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],

            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],

            // Deliberately no SVG: the image is streamed inline, and an SVG can
            // carry script — that would be stored XSS through the upload.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],

            ...AssetCustomFields::rules($this->customFields(), $asset),
        ];
    }

    /**
     * The assignee must exist in the table of its type. Users and assets have
     * no deleted state worth keeping here; a location follows the usual
     * live-or-unchanged rule. An asset cannot be assigned to itself, nor to
     * an asset that is (directly) assigned to it.
     *
     * @return list<mixed>
     */
    private function assigneeRules(): array
    {
        $asset = $this->route('asset');

        return match ($this->input('assignee_type')) {
            'user' => [Rule::exists('users', 'id')],
            'location' => [$this->liveOrUnchanged('assignee_id', 'locations')],
            'asset' => [
                Rule::exists('assets', 'id')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, \Closure $fail) use ($asset): void {
                    if (! $asset instanceof Asset) {
                        return;
                    }
                    $assignedBack = Asset::query()->whereKey($value)
                        ->where('assignee_type', Asset::class)->where('assignee_id', $asset->id)->exists();
                    if ($value === $asset->id || $assignedBack) {
                        $fail('An asset cannot be assigned to itself or to an asset assigned to it.');
                    }
                },
            ],
            default => [],
        };
    }

    /**
     * The model class for the submitted assignee type, or null.
     */
    public function assigneeClass(): ?string
    {
        return Asset::ASSIGNEE_TYPES[$this->validated('assignee_type')] ?? null;
    }

    /**
     * A new link must point at a live (not soft-deleted) record. A link the
     * asset already has is kept even if that record was deleted since:
     * otherwise deleting a location or status would make every asset using it
     * impossible to save until it is moved.
     */
    private function liveOrUnchanged(string $attribute, string $table): mixed
    {
        $asset = $this->route('asset');
        $exists = Rule::exists($table, 'id');

        $unchanged = $asset instanceof Asset
            && $asset->{$attribute} !== null
            && $this->input($attribute) === $asset->{$attribute};

        return $unchanged ? $exists : $exists->whereNull('deleted_at');
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return AssetCustomFields::attributeNames($this->customFields());
    }

    /**
     * The custom fields of the submitted model's fieldset — the ones this
     * request may set and must validate.
     *
     * @return Collection<int, CustomField>
     */
    public function customFields(): Collection
    {
        return once(fn () => AssetCustomFields::forModel($this->input('model_id')));
    }
}

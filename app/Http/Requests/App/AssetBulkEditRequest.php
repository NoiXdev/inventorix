<?php

namespace App\Http\Requests\App;

use App\Support\Assets\BulkSelection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Bulk edit, as Snipe-IT: a field left empty is not changed, a field listed
 * in `clear` is emptied, yes/no fields are '' (keep), '1' or '0'.
 */
class AssetBulkEditRequest extends FormRequest
{
    /** Fields that take a value. */
    public const FIELDS = [
        'status_id', 'model_id', 'location_id', 'rtd_location_id', 'supplier_id',
        'purchase_date', 'purchase_cost', 'order_number', 'warranty_months',
        'next_audit_date', 'expected_checkin', 'notes',
    ];

    /** Fields that can be emptied; the status is required on every asset. */
    public const CLEARABLE = [
        'model_id', 'location_id', 'rtd_location_id', 'supplier_id',
        'purchase_date', 'purchase_cost', 'order_number', 'warranty_months',
        'next_audit_date', 'expected_checkin', 'notes',
    ];

    /** Yes/no fields. */
    public const BOOLEANS = ['requestable', 'archived', 'depreciate'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only([...self::FIELDS, ...self::BOOLEANS]))
            ->map(fn ($v) => $v === '' ? null : $v)
            ->all());
    }

    public function rules(): array
    {
        return [
            ...BulkSelection::rules(),
            'status_id' => ['nullable', 'uuid', Rule::exists('status_labels', 'id')->whereNull('deleted_at')],
            'model_id' => ['nullable', 'uuid', Rule::exists('asset_models', 'id')->whereNull('deleted_at')],
            'location_id' => ['nullable', 'uuid', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'rtd_location_id' => ['nullable', 'uuid', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999.99', 'decimal:0,2'],
            'order_number' => ['nullable', 'string', 'max:191'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:1200'],
            'next_audit_date' => ['nullable', 'date'],
            'expected_checkin' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'requestable' => ['nullable', 'boolean'],
            'archived' => ['nullable', 'boolean'],
            'depreciate' => ['nullable', 'boolean'],
            'clear' => ['nullable', 'array'],
            'clear.*' => ['string', Rule::in(self::CLEARABLE), 'distinct'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $clear = (array) $this->input('clear', []);
            foreach ($clear as $field) {
                if ($this->filled($field)) {
                    $validator->errors()->add($field, 'Either set a value or clear the field, not both.');
                }
            }
            if ($this->changes() === []) {
                $validator->errors()->add('changes', 'Choose at least one field to change.');
            }
        }];
    }

    /** @return array<string, mixed> attribute => new value (null for cleared fields) */
    public function changes(): array
    {
        $changes = [];
        foreach (self::FIELDS as $field) {
            if ($this->filled($field)) {
                $changes[$field] = $this->input($field);
            }
        }
        foreach (self::BOOLEANS as $field) {
            if ($this->input($field) !== null) {
                $changes[$field] = (bool) $this->input($field);
            }
        }
        foreach ((array) $this->input('clear', []) as $field) {
            if (in_array($field, self::CLEARABLE, true)) {
                $changes[$field] = null;
            }
        }

        return $changes;
    }
}

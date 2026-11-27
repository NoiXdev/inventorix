<?php

namespace App\Http\Requests\App;

use App\Enums\CategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->except(['reassignable', 'maintained', 'requestable']))
            ->map(fn ($v) => $v === '' ? null : $v)
            ->all());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            // Only someone who may see product keys may set one.
            'product_key' => $this->user()->can('licenses.keys') ? ['nullable', 'string', 'max:5000'] : ['prohibited'],
            'seats' => ['required', 'integer', 'min:1', 'max:10000'],
            'category_id' => ['nullable', 'uuid', Rule::exists('categories', 'id')->where('category_type', CategoryType::LICENSE->value)->whereNull('deleted_at')],
            'manufacturer_id' => ['nullable', 'uuid', Rule::exists('manufacturers', 'id')->whereNull('deleted_at')],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'depreciation_id' => ['nullable', 'uuid', Rule::exists('depreciations', 'id')->whereNull('deleted_at')],
            'licensed_to_name' => ['nullable', 'string', 'max:191'],
            'licensed_to_email' => ['nullable', 'email', 'max:191'],
            'reassignable' => ['boolean'],
            'maintained' => ['boolean'],
            'requestable' => ['boolean'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999.99', 'decimal:0,2'],
            'order_number' => ['nullable', 'string', 'max:191'],
            'purchase_order' => ['nullable', 'string', 'max:191'],
            'expiration_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date'],
            'min_amt' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return ['category_id.exists' => 'Choose a license category.'];
    }

    /**
     * The license attributes (the seat count goes through LicenseService).
     * Without licenses.keys the product key is never among them, so an empty
     * field (which `prohibited` lets through) cannot wipe it.
     *
     * @return array<string, mixed>
     */
    public function licenseAttributes(): array
    {
        $except = $this->user()->can('licenses.keys') ? ['seats'] : ['seats', 'product_key'];

        return collect($this->validated())->except($except)->all();
    }
}

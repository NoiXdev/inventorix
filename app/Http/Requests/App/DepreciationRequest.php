<?php

namespace App\Http\Requests\App;

use App\Enums\DepreciationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepreciationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the `auth` middleware; no per-resource policy exists
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'months' => ['required', 'integer', 'min:1', 'max:65535'],
            // The floor value: a share of the purchase price or a fixed amount, per depreciation_type.
            'depreciation_min' => [
                'required', 'numeric', 'min:0',
                $this->input('depreciation_type') === DepreciationType::PERCENTAGE->value ? 'max:100' : 'max:9999999999.99',
            ],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'rate_multiplier' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
            'depreciation_type' => ['required', Rule::enum(DepreciationType::class)],
        ];
    }
}

<?php

namespace App\Http\Requests\App;

use App\Enums\AssetState;
use App\Enums\BuyType;
use Illuminate\Foundation\Http\FormRequest;
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
        $nullable = ['id', 'owner_id', 'place_id', 'model_id', 'buy_type', 'buy_date', 'guarantee_end', 'buy_price', 'serial_number', 'invoice'];
        $this->merge(collect($this->only($nullable))
            ->map(fn ($v) => $v === '' ? null : $v)
            ->all());
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'uuid', 'unique:assets,id'],
            'state' => ['required', Rule::enum(AssetState::class)],
            'asset_type_id' => ['required', 'uuid', 'exists:asset_types,id'],
            'owner_id' => ['nullable', 'uuid', 'exists:people,id'],
            'place_id' => ['nullable', 'uuid', 'exists:places,id'],
            'model_id' => ['nullable', 'uuid', 'exists:asset_models,id'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'buy_date' => ['nullable', 'date'],
            'guarantee_end' => ['nullable', 'date'],
            'buy_type' => ['nullable', Rule::enum(BuyType::class)],
            'buy_price' => ['nullable', 'numeric', 'min:0'],
            'invoice' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
        ];
    }
}

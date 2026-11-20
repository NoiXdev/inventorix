<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetCheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only(['status_id', 'location_id', 'checkin_at', 'note']))
            ->map(fn ($v) => $v === '' ? null : $v)
            ->all());
    }

    public function rules(): array
    {
        return [
            'status_id' => ['nullable', 'uuid', Rule::exists('status_labels', 'id')->whereNull('deleted_at')],
            // Empty: the asset's default location, else where it is.
            'location_id' => ['nullable', 'uuid', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'checkin_at' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

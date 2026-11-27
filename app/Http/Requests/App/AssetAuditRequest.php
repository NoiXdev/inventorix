<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** An audit: where the asset was seen, when the next audit is due, a note. */
class AssetAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only(['location_id', 'next_audit_date', 'note']))
            ->map(fn ($v) => $v === '' ? null : $v)
            ->all());
    }

    public function rules(): array
    {
        return [
            'location_id' => ['nullable', 'uuid', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'next_audit_date' => ['nullable', 'date', 'after:today'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

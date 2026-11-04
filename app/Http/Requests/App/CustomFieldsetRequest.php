<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomFieldsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the `auth` middleware; no per-resource policy exists
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // In display order.
            'fields' => ['array'],
            'fields.*.id' => ['required', 'distinct', Rule::exists('custom_fields', 'id')->whereNull('deleted_at')],
            'fields.*.required' => ['boolean'],
        ];
    }

    /**
     * The fields in sync() shape, with their position as `order`.
     *
     * @return array<string, array{order: int, required: bool}>
     */
    public function pivot(): array
    {
        return collect($this->validated('fields', []))
            ->values()
            ->mapWithKeys(fn (array $field, int $i) => [
                $field['id'] => ['order' => $i, 'required' => (bool) ($field['required'] ?? false)],
            ])
            ->all();
    }
}

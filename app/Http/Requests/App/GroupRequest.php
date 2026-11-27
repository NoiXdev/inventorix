<?php

namespace App\Http\Requests\App;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the `can:superuser` route middleware
    }

    public function rules(): array
    {
        $group = $this->route('group');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('groups', 'name')->ignore($group)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'in:1'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $known = array_column(Permission::cases(), 'value');
            foreach (array_keys((array) $this->input('permissions', [])) as $key) {
                if (! in_array($key, $known, true)) {
                    $validator->errors()->add("permissions.$key", 'Unknown permission.');
                }
            }
        }];
    }

    /** @return array<string, int> only the granted keys */
    public function granted(): array
    {
        return array_map('intval', array_filter((array) ($this->validated()['permissions'] ?? []), fn ($v) => (int) $v === 1));
    }
}

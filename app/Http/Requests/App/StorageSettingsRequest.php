<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class StorageSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'key' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string'],
            'region' => ['nullable', 'string', 'max:255'],
            'bucket' => ['nullable', 'string', 'max:255'],
            'endpoint' => ['nullable', 'url', 'max:255'],
            'use_path_style_endpoint' => ['required', 'boolean'],
            'url' => ['nullable', 'url', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\App;

use App\Enums\CategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the `auth` middleware; no per-resource policy exists
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'category_type' => ['required', Rule::enum(CategoryType::class)],
            'eula_text' => ['nullable', 'string', 'max:65535'],
            'use_default_eula' => ['boolean'],
            'require_acceptance' => ['boolean'],
            'alert_on_response' => ['boolean'],
            'checkin_email' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],

            // Deliberately no SVG: the image is streamed inline, and an SVG can
            // carry script — that would be stored XSS through the upload.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}

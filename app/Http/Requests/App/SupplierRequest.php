<?php

namespace App\Http\Requests\App;

use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the `auth` middleware; no per-resource policy exists
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'address' => ['nullable', 'string', 'max:250'],
            'address2' => ['nullable', 'string', 'max:250'],
            'city' => ['nullable', 'string', 'max:191'],
            'state' => ['nullable', 'string', 'max:191'],
            'zip' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', Rule::in(Countries::CODES)],
            'phone' => ['nullable', 'string', 'max:35'],
            'fax' => ['nullable', 'string', 'max:35'],
            'email' => ['nullable', 'email', 'max:150'],
            'contact' => ['nullable', 'string', 'max:100'],
            'url' => ['nullable', 'url:http,https', 'max:250'],
            'notes' => ['nullable', 'string', 'max:5000'],

            // Deliberately no SVG: the image is streamed inline, and an SVG can
            // carry script — that would be stored XSS through the upload.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}

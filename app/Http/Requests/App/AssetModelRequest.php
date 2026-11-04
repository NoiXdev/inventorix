<?php

namespace App\Http\Requests\App;

use App\Enums\CategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the `auth` middleware; no per-resource policy exists
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'manufacturer_id' => ['required', 'uuid', 'exists:manufacturers,id'],
            'model_number' => ['nullable', 'string', 'max:191'],
            // Only asset categories apply to models; the others are for accessories, licenses etc.
            'category_id' => ['nullable', 'uuid', Rule::exists('categories', 'id')
                ->where('category_type', CategoryType::ASSET->value)->whereNull('deleted_at')],
            'depreciation_id' => ['nullable', 'uuid', Rule::exists('depreciations', 'id')->whereNull('deleted_at')],
            'fieldset_id' => ['nullable', 'uuid', Rule::exists('custom_fieldsets', 'id')->whereNull('deleted_at')],
            'eol' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'min_amt' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'require_serial' => ['boolean'],
            'requestable' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],

            // Deliberately no SVG: the image is streamed inline, and an SVG can
            // carry script — that would be stored XSS through the upload.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}

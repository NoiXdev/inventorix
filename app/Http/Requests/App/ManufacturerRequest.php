<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class ManufacturerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the `auth` middleware; no per-resource policy exists
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}

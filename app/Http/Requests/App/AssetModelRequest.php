<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class AssetModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'manufacturer_id' => ['required', 'uuid', 'exists:manufacturers,id'],
        ];
    }
}

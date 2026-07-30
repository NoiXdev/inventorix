<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class AssetImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']];
    }
}

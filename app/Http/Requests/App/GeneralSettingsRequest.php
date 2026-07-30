<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class GeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['app_name' => ['required', 'string', 'max:255']];
    }
}

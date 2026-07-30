<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class WarrantySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'recipients' => ['array'],
            'recipients.*' => ['email'],
            'lead_days' => ['array'],
            'lead_days.*' => ['integer', 'min:0'],
        ];
    }
}

<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class AuthSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'multi_factor_enabled' => ['required', 'boolean'],
            'multi_factor_force' => ['required', 'boolean'],
            'multi_factor_recoverable' => ['required', 'boolean'],
            'microsoft_enabled' => ['required', 'boolean'],
            'microsoft_client_id' => ['nullable', 'required_if:microsoft_enabled,true', 'string', 'max:255'],
            'microsoft_client_secret' => ['nullable', 'string'],
            'microsoft_redirect' => ['nullable', 'required_if:microsoft_enabled,true', 'url', 'max:255'],
            'microsoft_tenant' => ['nullable', 'required_if:microsoft_enabled,true', 'string', 'max:255'],
        ];
    }
}

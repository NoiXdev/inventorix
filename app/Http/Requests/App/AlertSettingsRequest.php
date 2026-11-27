<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class AlertSettingsRequest extends FormRequest
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
            'audit_interval_months' => ['required', 'integer', 'min:0', 'max:120'],
            'audit_warning_days' => ['required', 'integer', 'min:0', 'max:365'],
            'checkin_warning_days' => ['required', 'integer', 'min:0', 'max:365'],
            'license_warning_days' => ['required', 'integer', 'min:0', 'max:365'],
        ];
    }
}

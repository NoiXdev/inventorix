<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'default_mailer' => ['required', Rule::in(['smtp', 'postal', 'ses', 'postmark', 'resend', 'sendmail', 'log'])],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_scheme' => ['nullable', Rule::in(['smtp', 'smtps'])],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string'],
            'ses_key' => ['nullable', 'string', 'max:255'],
            'ses_secret' => ['nullable', 'string'],
            'ses_region' => ['nullable', 'string', 'max:255'],
            'postmark_token' => ['nullable', 'string'],
            'postmark_message_stream_id' => ['nullable', 'string', 'max:255'],
            'resend_key' => ['nullable', 'string'],
            'postal_domain' => ['nullable', 'url', 'max:255'],
            'postal_key' => ['nullable', 'string'],
        ];
    }
}

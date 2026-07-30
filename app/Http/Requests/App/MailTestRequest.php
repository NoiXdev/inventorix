<?php

namespace App\Http\Requests\App;

class MailTestRequest extends MailSettingsRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules() + ['email' => ['required', 'email']];
    }
}

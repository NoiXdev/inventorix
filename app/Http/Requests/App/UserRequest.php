<?php

// app/Http/Requests/App/UserRequest.php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'person_id' => ['nullable', 'uuid', 'exists:people,id'],
            'login_enabled' => ['boolean'],
            'email' => [
                'required_if:login_enabled,true', 'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
        ];
    }
}

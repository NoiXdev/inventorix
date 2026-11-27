<?php

namespace App\Http\Requests\App;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Consumables go to users only (as Snipe-IT). */
class ConsumableCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', Rule::exists('users', 'id')],
            'qty' => ['required', 'integer', 'min:1', 'max:10000'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function recipient(): User
    {
        return User::query()->findOrFail($this->validated('user_id'));
    }
}

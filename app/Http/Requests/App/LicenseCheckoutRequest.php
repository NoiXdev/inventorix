<?php

namespace App\Http\Requests\App;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LicenseCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignee_type' => ['required', Rule::in(['user', 'asset'])],
            'assignee_id' => ['required', 'uuid', $this->input('assignee_type') === 'asset'
                ? Rule::exists('assets', 'id')->whereNull('deleted_at')
                : Rule::exists('users', 'id')],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function target(): User|Asset
    {
        return $this->validated('assignee_type') === 'asset'
            ? Asset::query()->findOrFail($this->validated('assignee_id'))
            : User::query()->findOrFail($this->validated('assignee_id'));
    }
}

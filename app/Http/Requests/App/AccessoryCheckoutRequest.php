<?php

namespace App\Http\Requests\App;

use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccessoryCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignee_type' => ['required', Rule::in(array_keys(AccessoryCheckout::TARGET_TYPES))],
            'assignee_id' => ['required', 'uuid', match ($this->input('assignee_type')) {
                'location' => Rule::exists('locations', 'id')->whereNull('deleted_at'),
                'asset' => Rule::exists('assets', 'id')->whereNull('deleted_at'),
                default => Rule::exists('users', 'id'),
            }],
            'qty' => ['required', 'integer', 'min:1', 'max:10000'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function target(): User|Location|Asset
    {
        $class = AccessoryCheckout::TARGET_TYPES[$this->validated('assignee_type')];

        return $class::query()->findOrFail($this->validated('assignee_id'));
    }
}

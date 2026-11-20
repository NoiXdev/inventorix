<?php

namespace App\Http\Requests\App;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only(['status_id', 'checkout_at', 'expected_checkin', 'note']))
            ->map(fn ($v) => $v === '' ? null : $v)
            ->all());
    }

    public function rules(): array
    {
        return [
            'assignee_type' => ['required', Rule::in(array_keys(Asset::ASSIGNEE_TYPES))],
            'assignee_id' => ['required', 'uuid', ...$this->targetRules()],
            'status_id' => ['nullable', 'uuid', Rule::exists('status_labels', 'id')->where('deployable', true)->whereNull('deleted_at')],
            'checkout_at' => ['nullable', 'date', 'before_or_equal:today'],
            'expected_checkin' => ['nullable', 'date', 'after_or_equal:'.($this->input('checkout_at') ?: 'today')],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return list<mixed> */
    private function targetRules(): array
    {
        return match ($this->input('assignee_type')) {
            'user' => [Rule::exists('users', 'id')],
            'location' => [Rule::exists('locations', 'id')->whereNull('deleted_at')],
            // Self and circular targets are refused by the service.
            'asset' => [Rule::exists('assets', 'id')->whereNull('deleted_at')],
            default => [],
        };
    }

    public function messages(): array
    {
        return ['status_id.exists' => 'Choose a deployable status.'];
    }

    /** The user, location or asset to check out to. */
    public function target(): Model
    {
        $class = Asset::ASSIGNEE_TYPES[$this->validated('assignee_type')];

        return $class::query()->findOrFail($this->validated('assignee_id'));
    }
}

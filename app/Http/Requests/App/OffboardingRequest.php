<?php

namespace App\Http\Requests\App;

use App\Http\Controllers\App\UserOffboardingController;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * What to do with each item a user holds when they leave. Each action needs
 * the matching permission: a check-in `<module>.checkin`, a transfer also
 * `<module>.checkout`; turning the login off needs `users.edit` and the right
 * to manage the user.
 */
class OffboardingRequest extends FormRequest
{
    /** Item type => permission section. */
    public const MODULES = [
        'asset' => 'assets',
        'license_seat' => 'licenses',
        'accessory_checkout' => 'accessories',
    ];

    public function authorize(): bool
    {
        return UserOffboardingController::mayOffboard($this->user());
    }

    public function rules(): array
    {
        $transfers = collect((array) $this->input('items', []))->contains(fn ($item) => is_array($item) && ($item['action'] ?? null) === 'transfer');

        return [
            'items' => [$this->boolean('disable_login') ? 'nullable' : 'required', 'array'],
            'items.*' => ['array:type,id,action'],
            'items.*.type' => ['required', Rule::in(array_keys(self::MODULES))],
            'items.*.id' => ['required', 'uuid'],
            'items.*.action' => ['required', Rule::in(['checkin', 'transfer'])],
            'transfer_to' => [
                Rule::requiredIf($transfers), 'nullable', 'uuid', Rule::exists('users', 'id'),
                Rule::notIn([$this->offboarded()->id]),
            ],
            'status_id' => ['nullable', 'uuid', Rule::exists('status_labels', 'id')->whereNull('deleted_at')],
            'location_id' => ['nullable', 'uuid', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'note' => ['nullable', 'string', 'max:1000'],
            'disable_login' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Choose at least one item to check in or transfer.',
            'transfer_to.required' => 'Choose who gets the transferred items.',
            'transfer_to.not_in' => 'Items cannot be transferred to the user who is leaving.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $actor = $this->user();

            foreach ((array) $this->validated('items', []) as $item) {
                $module = self::MODULES[$item['type']];
                $allowed = $actor->can("{$module}.checkin")
                    && ($item['action'] !== 'transfer' || $actor->can("{$module}.checkout"));
                if (! $allowed) {
                    $verb = $item['action'] === 'transfer' ? 'transfer' : 'check in';
                    $validator->errors()->add('items', "You may not {$verb} {$module}.");

                    return;
                }
            }

            if ($this->boolean('disable_login') && ($message = self::disableLoginBlocker($actor, $this->offboarded())) !== null) {
                $validator->errors()->add('disable_login', $message);
            }
        }];
    }

    /** Why the actor cannot turn off the user's login, or null when they can. */
    public static function disableLoginBlocker(User $actor, User $user): ?string
    {
        return match (true) {
            ! $actor->can('users.edit') || ! $actor->can('manage', $user) => 'You may not disable this user\'s login.',
            $user->is($actor) => 'You cannot disable your own login.',
            $user->isLastActiveSuperuser() => User::LAST_SUPERUSER,
            default => null,
        };
    }

    public function offboarded(): User
    {
        return $this->route('user');
    }

    public function transferTo(): ?User
    {
        $id = $this->validated('transfer_to');

        return $id ? User::query()->findOrFail($id) : null;
    }
}

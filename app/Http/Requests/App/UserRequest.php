<?php

// app/Http/Requests/App/UserRequest.php

namespace App\Http\Requests\App;

use App\Enums\Permission;
use App\Models\Group;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $assign = $this->mayAssign();

        return [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'login_enabled' => ['boolean'],
            'email' => [
                'required_if:login_enabled,true', 'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'employee_num' => ['nullable', 'string', 'max:191'],
            'jobtitle' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'uuid', $this->liveOrCurrent('departments', 'department_id')],
            'manager_id' => ['nullable', 'uuid', Rule::exists('users', 'id'), ...($this->target() ? [Rule::notIn([$this->target()->id])] : [])],
            'location_id' => ['nullable', 'uuid', $this->liveOrCurrent('locations', 'location_id')],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', ...($this->filled('start_date') ? ['after_or_equal:start_date'] : [])],
            'notes' => ['nullable', 'string', 'max:10000'],
            // Group membership and the per-user map need admin, and only a
            // superuser may change their own.
            'groups' => $assign ? ['sometimes', 'array'] : ['prohibited'],
            'groups.*' => ['uuid', Rule::exists('groups', 'id')],
            'permissions' => $assign ? ['sometimes', 'array', $this->permissionMap()] : ['prohibited'],
        ];
    }

    /** Whether the actor may send `groups` / `permissions` for this target. */
    public function mayAssign(): bool
    {
        $actor = $this->user();
        if (! $actor instanceof User || ! $actor->hasAccess(Permission::ADMIN)) {
            return false;
        }

        $target = $this->target();

        return $target === null || ! $target->is($actor) || $actor->isSuperuser();
    }

    /** Granting or removing admin/superuser, directly or through a group, is superuser-only. */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $actor = $this->user();
            if (! $this->mayAssign() || $actor->isSuperuser() || $validator->errors()->isNotEmpty()) {
                return;
            }

            $target = $this->target();

            if ($this->has('groups')) {
                $current = $target ? $target->groups()->pluck('groups.id')->all() : [];
                $submitted = (array) $this->input('groups', []);
                $changed = array_merge(array_diff($submitted, $current), array_diff($current, $submitted));
                if (Group::whereIn('id', $changed)->get()->contains(fn (Group $g) => $g->isPrivileged())) {
                    $validator->errors()->add('groups', 'Only a superuser can add or remove a group that grants admin or superuser.');
                }
            }

            if ($this->has('permissions')) {
                $current = $target?->permissions ?? [];
                $submitted = (array) $this->input('permissions', []);
                foreach ([Permission::SUPERUSER, Permission::ADMIN] as $privileged) {
                    $key = $privileged->value;
                    if (array_key_exists($key, $submitted) && (int) $submitted[$key] !== (int) ($current[$key] ?? 0)) {
                        $validator->errors()->add('permissions', 'Only a superuser can grant or deny admin or superuser.');
                        break;
                    }
                }
            }
        }];
    }

    /** A live row, or the one the user already has (it may have been deleted since). */
    private function liveOrCurrent(string $table, string $column): Exists
    {
        $target = $this->target();
        $unchanged = $target !== null
            && $target->{$column} !== null
            && $this->input($column) === $target->{$column};

        $exists = Rule::exists($table, 'id');

        return $unchanged ? $exists : $exists->whereNull('deleted_at');
    }

    private function target(): ?User
    {
        $target = $this->route('user');

        return $target instanceof User ? $target : null;
    }

    /** Keys must be catalogue permissions, values 1 (grant) or -1 (deny); absent = inherit. */
    private function permissionMap(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            foreach ((array) $value as $key => $state) {
                if (Permission::tryFrom((string) $key) === null) {
                    $fail("Unknown permission: {$key}.");

                    return;
                }
                if (! in_array($state, [1, -1, '1', '-1'], true)) {
                    $fail('Each permission must be granted (1) or denied (-1).');

                    return;
                }
            }
        };
    }
}

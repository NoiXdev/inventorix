<?php

namespace App\Http\Requests\App;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the `auth` middleware; no per-resource policy exists
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')
                ->whereNull('deleted_at')->ignore($this->route('department'))],
            'manager_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
            'location_id' => ['nullable', 'uuid', $this->liveLocation()],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /** A live location, or the one the department already has (it may have been deleted since). */
    private function liveLocation(): Exists
    {
        $department = $this->route('department');
        $unchanged = $department instanceof Department
            && $department->location_id !== null
            && $this->input('location_id') === $department->location_id;

        $exists = Rule::exists('locations', 'id');

        return $unchanged ? $exists : $exists->whereNull('deleted_at');
    }
}

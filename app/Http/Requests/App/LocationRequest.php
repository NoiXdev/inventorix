<?php

namespace App\Http\Requests\App;

use App\Models\Location;
use App\Support\Countries;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the `auth` middleware; no per-resource policy exists
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'parent_id' => [
                'nullable', 'uuid',
                Rule::exists('locations', 'id')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $location = $this->route('location');
                    if (! $location instanceof Location) {
                        return;
                    }
                    if ($value === $location->id || in_array($value, $location->descendantIds(), true)) {
                        $fail('A location cannot be placed inside itself or one of its sub-locations.');
                    }
                },
            ],
            'manager_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
            'address' => ['nullable', 'string', 'max:191'],
            'address2' => ['nullable', 'string', 'max:191'],
            'city' => ['nullable', 'string', 'max:191'],
            'state' => ['nullable', 'string', 'max:191'],
            'zip' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', Rule::in(Countries::CODES)],
            'phone' => ['nullable', 'string', 'max:20'],
            'fax' => ['nullable', 'string', 'max:20'],
            'currency' => ['nullable', 'string', 'max:10'],
            'ldap_ou' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:5000'],

            // Deliberately no SVG: the image is streamed inline, and an SVG can
            // carry script — that would be stored XSS through the upload.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\App;

use App\Enums\CustomFieldElement;
use App\Models\CustomField;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomFieldRequest extends FormRequest
{
    /** Elements whose value is picked from `field_values`. */
    public const CHOICE_ELEMENTS = ['select', 'radio', 'checkbox'];

    public function authorize(): bool
    {
        return true; // gated by the `auth` middleware; no per-resource policy exists
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'element' => ['required', Rule::enum(CustomFieldElement::class)],
            'format' => [
                'required',
                Rule::in(array_keys(CustomField::PREDEFINED_FORMATS)),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->input('element') !== CustomFieldElement::TEXT->value && in_array($value, CustomField::TEXT_ONLY_FORMATS, true)) {
                        $fail('This format can only be used with a text element.');
                    }
                },
            ],
            // The pattern alone, delimiters included, e.g. /^[A-Z]{3}$/.
            'custom_regex' => [
                'nullable', 'required_if:format,CUSTOM REGEX', 'string', 'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->input('format') === 'CUSTOM REGEX' && @preg_match($value, '') === false) {
                        $fail('This is not a valid regular expression.');
                    }
                },
            ],
            'field_values' => [
                Rule::requiredIf(in_array($this->input('element'), self::CHOICE_ELEMENTS, true)),
                'nullable', 'string', 'max:65535',
            ],
            'help_text' => ['nullable', 'string', 'max:5000'],
            'field_encrypted' => ['boolean'],
            'show_in_email' => ['boolean'],
            'show_in_requestable_list' => ['boolean'],
            'is_unique' => ['boolean'],
            'display_in_user_view' => ['boolean'],
            'auto_add_to_fieldsets' => ['boolean'],
            'show_in_listview' => ['boolean'],
            'display_checkin' => ['boolean'],
            'display_audit' => ['boolean'],
        ];
    }

    /**
     * The validated attributes as stored: a custom regex goes into `format`
     * as a `regex:` rule, and options are dropped for elements that have none.
     *
     * @return array<string, mixed>
     */
    public function storedAttributes(): array
    {
        $data = collect($this->validated());

        if ($data->get('format') === 'CUSTOM REGEX') {
            $data->put('format', 'regex:'.$data->get('custom_regex'));
        }

        if (! in_array($data->get('element'), self::CHOICE_ELEMENTS, true)) {
            $data->put('field_values', null);
        }

        return $data->except('custom_regex')->all();
    }
}

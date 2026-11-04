<?php

namespace App\Http\Requests\App;

use App\Enums\CustomFieldElement;
use App\Models\CustomField;
use App\Support\CustomFields\CustomFieldColumns;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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
                    $element = $this->input('element');
                    $textOnly = [...CustomField::TEXT_ONLY_FORMATS, 'CUSTOM REGEX'];

                    if ($element !== CustomFieldElement::TEXT->value && in_array($value, $textOnly, true)) {
                        $fail('This format can only be used with a text element.');
                    } elseif ($value === 'DATE' && $element !== CustomFieldElement::DATE_PICKER->value) {
                        $fail('The DATE format needs the date picker element.');
                    } elseif ($value === 'DATETIME' && $element !== CustomFieldElement::DATETIME_PICKER->value) {
                        $fail('The DATETIME format needs the datetime picker element.');
                    }

                    // DATE / DATETIME fields are backed by a native date column,
                    // the others by text; switching would mean converting the
                    // column and every value in it.
                    $existing = $this->field();
                    if ($existing && CustomFieldColumns::isDateFormat($existing->formatName()) !== CustomFieldColumns::isDateFormat($value)) {
                        $fail('The format cannot be changed to or from DATE / DATETIME. Create a new field instead.');
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
            'field_encrypted' => [
                'boolean',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $encrypted = filter_var($value, FILTER_VALIDATE_BOOLEAN);

                    if ($encrypted && CustomFieldColumns::isDateFormat($this->input('format'))) {
                        $fail('Date fields cannot be encrypted: their column only holds dates.');
                    } elseif ($encrypted && in_array($this->input('element'), ['checkbox', 'radio'], true)) {
                        $fail('Checkbox and radio fields cannot be encrypted.');
                    } elseif ($encrypted && filter_var($this->input('is_unique'), FILTER_VALIDATE_BOOLEAN)) {
                        $fail('Encrypted fields cannot be unique: encrypted values cannot be compared.');
                    }

                    // Existing values would stay as they are — plain text read as
                    // ciphertext, or ciphertext shown as text.
                    $existing = $this->field();
                    if ($existing && $existing->field_encrypted !== $encrypted
                        && DB::table(CustomFieldColumns::TABLE)->whereNotNull($existing->db_column)->exists()) {
                        $fail('Encryption cannot be switched while assets have a value for this field.');
                    }
                },
            ],
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

    /** The field being edited, or null when creating one. */
    private function field(): ?CustomField
    {
        $field = $this->route('custom_field');

        return $field instanceof CustomField ? $field : null;
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

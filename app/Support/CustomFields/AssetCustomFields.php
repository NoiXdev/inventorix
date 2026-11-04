<?php

namespace App\Support\CustomFields;

use App\Enums\CustomFieldElement;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CustomField;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Reading, validating and writing an asset's custom field values, which live
 * in the `cf_*` columns of `assets` (see CustomFieldColumns).
 *
 * Which fields apply is decided by the asset model's fieldset. Values are
 * submitted as `custom_fields[<db_column>]`.
 */
class AssetCustomFields
{
    /** Checkbox choices are stored in one column, joined by this. */
    private const SEPARATOR = ', ';

    /**
     * The fields of a model's fieldset, in fieldset order, each carrying its
     * per-fieldset `required` flag on ->pivot.
     *
     * @return Collection<int, CustomField>
     */
    public static function forModel(?string $modelId): Collection
    {
        if (! $modelId || ! Str::isUuid($modelId)) {
            return collect();
        }

        return AssetModel::query()->with('fieldset.fields')->find($modelId)?->fieldset?->fields ?? collect();
    }

    /**
     * Fields shown as columns in the asset table. Encrypted ones are left
     * out: their ciphertext can be neither displayed, sorted nor searched.
     *
     * @return Collection<int, CustomField>
     */
    public static function listed(): Collection
    {
        return CustomField::query()
            ->where('show_in_listview', true)
            ->where('field_encrypted', false)
            ->orderBy('name')
            ->get();
    }

    /**
     * Validation rules for the given fields, keyed `custom_fields.<db_column>`.
     *
     * @param  Collection<int, CustomField>  $fields
     * @return array<string, mixed>
     */
    public static function rules(Collection $fields, ?Asset $asset = null): array
    {
        $rules = ['custom_fields' => ['nullable', 'array']];

        foreach ($fields as $field) {
            $key = 'custom_fields.'.$field->db_column;
            $presence = $field->pivot?->required ? 'required' : 'nullable';

            if ($field->element === CustomFieldElement::CHECKBOX) {
                $rules[$key] = [$presence, 'array'];
                $rules[$key.'.*'] = ['string', Rule::in($field->options())];

                continue;
            }

            $rule = [$presence, 'string', 'max:65535'];

            match ($field->element) {
                CustomFieldElement::SELECT, CustomFieldElement::RADIO => $rule[] = Rule::in($field->options()),
                CustomFieldElement::DATE_PICKER => $rule[] = 'date_format:Y-m-d',
                CustomFieldElement::DATETIME_PICKER => $rule[] = 'date_format:Y-m-d H:i:s',
                default => null,
            };

            if ($format = $field->validationRule()) {
                $rule[] = $format;
            }

            // Ciphertext differs on every encryption, so encrypted values can't
            // be compared; CustomFieldRequest forbids that combination.
            if ($field->is_unique && ! $field->field_encrypted) {
                $rule[] = Rule::unique(CustomFieldColumns::TABLE, $field->db_column)->ignore($asset?->id);
            }

            $rules[$key] = $rule;
        }

        return $rules;
    }

    /**
     * Readable names for validation messages, so errors say "MAC address"
     * rather than "custom_fields.cf_mac_address".
     *
     * @param  Collection<int, CustomField>  $fields
     * @return array<string, string>
     */
    public static function attributeNames(Collection $fields): array
    {
        return $fields->mapWithKeys(fn (CustomField $f) => [
            'custom_fields.'.$f->db_column => $f->name,
            'custom_fields.'.$f->db_column.'.*' => $f->name,
        ])->all();
    }

    /**
     * Write validated values onto the asset. Every field of the fieldset is
     * written, so a cleared input or unticked checkbox list becomes null;
     * columns of fields outside the fieldset are left untouched.
     *
     * @param  Collection<int, CustomField>  $fields
     * @param  array<string, mixed>  $input
     */
    public static function fill(Asset $asset, Collection $fields, array $input): void
    {
        foreach ($fields as $field) {
            $value = $input[$field->db_column] ?? null;
            if (is_array($value)) {
                $value = implode(self::SEPARATOR, $value);
            }
            if ($value === '') {
                $value = null;
            }

            if ($field->field_encrypted && $value !== null) {
                // Re-encrypting an unchanged value would produce new
                // ciphertext, marking the column dirty and logging a change
                // in the asset history that never happened.
                if (self::decrypt($asset->getAttribute($field->db_column)) === $value) {
                    continue;
                }
                $value = Crypt::encryptString($value);
            }

            $asset->forceFill([$field->db_column => $value]);
        }
    }

    /**
     * Current values for the edit form, decrypted, with checkbox choices as
     * arrays. Covers every field, so switching the model in the form shows
     * whatever the asset already holds for the new fieldset.
     *
     * @param  Collection<int, CustomField>  $fields
     * @return array<string, string|list<string>|null>
     */
    public static function values(Asset $asset, Collection $fields): array
    {
        return $fields->mapWithKeys(function (CustomField $field) use ($asset) {
            $value = self::plain($asset, $field);

            if ($field->element === CustomFieldElement::CHECKBOX) {
                $value = $value === null ? [] : array_values(array_filter(explode(self::SEPARATOR, $value), 'strlen'));
            }

            return [$field->db_column => $value];
        })->all();
    }

    /** The decrypted value as stored, or null. */
    public static function plain(Asset $asset, CustomField $field): ?string
    {
        $value = $asset->getAttribute($field->db_column);
        if ($value === null || $value === '') {
            return null;
        }

        return $field->field_encrypted ? self::decrypt($value) : (string) $value;
    }

    /**
     * What the frontend needs to render a field's input.
     *
     * @return array<string, mixed>
     */
    public static function definition(CustomField $field): array
    {
        return [
            'db_column' => $field->db_column,
            'name' => $field->name,
            'element' => $field->element->value,
            'format' => $field->formatName(),
            'help_text' => $field->help_text,
            'required' => (bool) $field->pivot?->required,
            'options' => $field->options(),
        ];
    }

    private static function decrypt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            // Written before encryption was enabled, or with another app key.
            return null;
        }
    }
}

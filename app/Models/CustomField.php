<?php

namespace App\Models;

use App\Concerns\Creator;
use App\Enums\CustomFieldElement;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'format',
    'element',
    'field_values',
    'field_encrypted',
    'db_column',
    'help_text',
    'show_in_email',
    'show_in_requestable_list',
    'is_unique',
    'display_in_user_view',
    'auto_add_to_fieldsets',
    'show_in_listview',
    'display_checkin',
    'display_audit',
])]
class CustomField extends Model
{
    use Creator, HasFactory, HasUuids, SoftDeletes;

    /**
     * Format name => the validation rule it applies to a field's value.
     *
     * The `format` column stores the format name (e.g. 'EMAIL'), or for
     * 'CUSTOM REGEX' the raw `regex:/…/` rule itself. Storing names rather than
     * rules keeps every format distinguishable even where two share a rule,
     * such as ANY and CUSTOM REGEX.
     */
    public const PREDEFINED_FORMATS = [
        'ANY' => '',
        'CUSTOM REGEX' => '',
        'ALPHA' => 'alpha',
        'ALPHA-DASH' => 'alpha_dash',
        'NUMERIC' => 'numeric',
        'ALPHA-NUMERIC' => 'alpha_num',
        'EMAIL' => 'email',
        'DATE' => 'date',
        // The datetime picker always outputs Y-m-d H:i:s, so this is the exact
        // shape to expect.
        'DATETIME' => 'date_format:Y-m-d H:i:s',
        'URL' => 'url',
        'IP' => 'ip',
        'IPV4' => 'ipv4',
        'IPV6' => 'ipv6',
        'MAC' => 'regex:/^[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}$/',
        'BOOLEAN' => 'boolean',
        // Phone / fax validation is famously unreliable (international
        // formats, extensions, punctuation variance), so both only require a
        // string.
        'PHONE' => 'string',
        'FAX' => 'string',
    ];

    public const TEXT_ONLY_FORMATS = [
        'EMAIL', 'URL', 'PHONE', 'FAX', 'MAC', 'IP', 'IPV4', 'IPV6',
        'ALPHA', 'ALPHA-DASH', 'ALPHA-NUMERIC', 'NUMERIC',
    ];

    protected function casts(): array
    {
        return [
            'element' => CustomFieldElement::class,
            'field_encrypted' => 'boolean',
            'show_in_email' => 'boolean',
            'show_in_requestable_list' => 'boolean',
            'is_unique' => 'boolean',
            'display_in_user_view' => 'boolean',
            'auto_add_to_fieldsets' => 'boolean',
            'show_in_listview' => 'boolean',
            'display_checkin' => 'boolean',
            'display_audit' => 'boolean',
        ];
    }

    public function fieldsets(): BelongsToMany
    {
        return $this->belongsToMany(CustomFieldset::class, 'custom_field_has_custom_fieldsets')
            ->withPivot(['order', 'required']);
    }

    /** The format name, with any stored custom regex reported as 'CUSTOM REGEX'. */
    public function formatName(): string
    {
        if ($this->format === null || $this->format === '') {
            return 'ANY';
        }

        return array_key_exists($this->format, self::PREDEFINED_FORMATS) ? $this->format : 'CUSTOM REGEX';
    }

    /** The stored custom `regex:/…/` rule, if the format is CUSTOM REGEX. */
    public function customRegex(): ?string
    {
        return $this->formatName() === 'CUSTOM REGEX' ? $this->format : null;
    }

    /** The validation rule this field applies to its value, or null for none. */
    public function validationRule(): ?string
    {
        return $this->customRegex() ?? (self::PREDEFINED_FORMATS[$this->formatName()] ?: null);
    }

    /**
     * The choices of a select, radio or checkbox field, one per line.
     *
     * @return list<string>
     */
    public function options(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->field_values))));
    }
}

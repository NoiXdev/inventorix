<?php

namespace App\Support\CustomFields;

use App\Models\CustomField;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The `assets` columns backing custom fields: one real column per field,
 * named `cf_<slug>`, so values can be sorted, filtered and queried in SQL
 * like any other asset attribute.
 */
class CustomFieldColumns
{
    public const TABLE = 'assets';

    /** Formats backed by a native date column rather than text. */
    public const DATE_FORMATS = ['DATE', 'DATETIME'];

    /**
     * A free column name for the field: `cf_` plus its slugged name, cut so
     * the result stays well under MariaDB's 64-character identifier limit,
     * with `_2`, `_3`… appended on a clash. `$current` is the field's own
     * column, which doesn't count as a clash when renaming.
     */
    public static function nameFor(CustomField $field, ?string $current = null): string
    {
        $slug = Str::limit(Str::slug($field->name, '_', 'de'), 50, '') ?: 'field';
        $base = 'cf_'.rtrim($slug, '_');

        $candidate = $base;
        for ($i = 2; $candidate !== $current && Schema::hasColumn(self::TABLE, $candidate); $i++) {
            $candidate = $base.'_'.$i;
        }

        return $candidate;
    }

    public static function add(CustomField $field): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) use ($field) {
            match ($field->formatName()) {
                'DATE' => $table->date($field->db_column)->nullable(),
                'DATETIME' => $table->dateTime($field->db_column)->nullable(),
                default => $table->text($field->db_column)->nullable(),
            };
        });
    }

    public static function rename(string $from, string $to): void
    {
        Schema::table(self::TABLE, fn (Blueprint $table) => $table->renameColumn($from, $to));
    }

    public static function drop(string $column): void
    {
        if (Schema::hasColumn(self::TABLE, $column)) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropColumn($column));
        }
    }

    public static function isDateFormat(?string $formatName): bool
    {
        return in_array($formatName, self::DATE_FORMATS, true);
    }
}

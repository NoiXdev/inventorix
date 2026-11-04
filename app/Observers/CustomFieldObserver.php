<?php

namespace App\Observers;

use App\Models\CustomField;
use App\Support\CustomFields\CustomFieldColumns;
use LogicException;

/**
 * Keeps each custom field's `assets` column in step with the field, so no
 * controller has to remember to. Schema changes can't be rolled back on
 * MariaDB, so the column is created before the row is written: a failed
 * insert leaves at most an unused column, never a field without one.
 */
class CustomFieldObserver
{
    public function creating(CustomField $field): void
    {
        $field->db_column = CustomFieldColumns::nameFor($field);
        CustomFieldColumns::add($field);
    }

    public function updating(CustomField $field): void
    {
        // A date column can't hold arbitrary text and vice versa; the request
        // rejects this with a message, this guards every other path.
        if ($field->isDirty('format')) {
            $from = (new CustomField(['format' => $field->getOriginal('format')]))->formatName();
            if (CustomFieldColumns::isDateFormat($from) !== CustomFieldColumns::isDateFormat($field->formatName())) {
                throw new LogicException('A custom field cannot change to or from a date format.');
            }
        }

        if ($field->isDirty('name')) {
            $column = CustomFieldColumns::nameFor($field, $field->db_column);
            if ($column !== $field->db_column) {
                CustomFieldColumns::rename($field->db_column, $column);
                $field->db_column = $column;
            }
        }
    }

    /**
     * Only a permanent delete drops the column: a soft-deleted field keeps
     * its values so restoring it brings them back.
     */
    public function forceDeleted(CustomField $field): void
    {
        if ($field->db_column) {
            CustomFieldColumns::drop($field->db_column);
        }
    }
}

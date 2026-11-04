<?php

namespace App\Enums;

enum CustomFieldElement: string
{
    case TEXT = 'text';
    case SELECT = 'select';
    case TEXTAREA = 'textarea';
    case MARKDOWN_TEXT = 'markdown_text';
    case CHECKBOX = 'checkbox';
    case RADIO = 'radio';
    case DATE_PICKER = 'date_picker';
    case DATETIME_PICKER = 'datetime_picker';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TEXT => 'Text',
            self::SELECT => 'Select',
            self::TEXTAREA => 'Textarea',
            self::MARKDOWN_TEXT => 'Markdown Text',
            self::CHECKBOX => 'Checkbox',
            self::RADIO => 'Radio',
            self::DATE_PICKER => 'Date Picker',
            self::DATETIME_PICKER => 'Datetime Picker',
        };
    }
}

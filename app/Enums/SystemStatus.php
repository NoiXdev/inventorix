<?php

namespace App\Enums;

/**
 * The status labels the application itself relies on, identified by
 * status_labels.system_key. Handovers move assets between these (see
 * HandoverType), so they can be renamed or retyped but not deleted.
 *
 * The values are the former asset states, which is what the migration to
 * status labels seeded them from.
 */
enum SystemStatus: string
{
    case NEW = 'new';
    case SOLD = 'sold';
    case STORAGE = 'storage';
    case LEND = 'lend';
    case DEFECT = 'defect';
    case UNDER_REPAIR = 'under-repair';
    case NEED_REPAIR = 'need-repair';
    case IN_USE = 'in-use';
}

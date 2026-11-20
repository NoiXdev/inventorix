<?php

namespace App\Observers;

use App\Models\Asset;
use App\Models\AssetModel;

class AssetObserver
{
    private const FIELD_EVENT_MAP = [
        'location_id' => 'location_changed',
        'status_id' => 'status_changed',
    ];

    /**
     * Unless the EOL date was entered by hand, it follows the purchase date
     * plus the model's EOL months.
     */
    public function saving(Asset $asset): void
    {
        if ($asset->eol_explicit) {
            return;
        }

        $months = $asset->model_id ? AssetModel::withTrashed()->whereKey($asset->model_id)->value('eol') : null;
        $asset->asset_eol_date = $asset->purchase_date && $months
            ? $asset->purchase_date->copy()->addMonthsNoOverflow((int) $months)
            : null;
    }

    public function updating(Asset $asset): void
    {
        if ($asset->isDirty(['assignee_type', 'assignee_id'])) {
            activity('asset')
                ->performedOn($asset)
                ->causedBy(auth()->user())
                ->withProperties([
                    'from' => self::assignee($asset->getOriginal('assignee_type'), $asset->getOriginal('assignee_id')),
                    'to' => self::assignee($asset->assignee_type, $asset->assignee_id),
                ])
                ->log('assignee_changed');
        }

        foreach (self::FIELD_EVENT_MAP as $field => $event) {
            if (! $asset->isDirty($field)) {
                continue;
            }

            $from = $asset->getOriginal($field);
            $to = $asset->getAttribute($field);

            // Serialize backed enums to their scalar value so the JSON column
            // stores 'new', not an enum object representation.
            if ($from instanceof \BackedEnum) {
                $from = $from->value;
            }
            if ($to instanceof \BackedEnum) {
                $to = $to->value;
            }

            activity('asset')
                ->performedOn($asset)
                ->causedBy(auth()->user())
                ->withProperties([
                    'from' => $from,
                    'to' => $to,
                ])
                ->log($event);
        }
    }

    /** @return array{type: string, id: string}|null */
    private static function assignee(?string $type, ?string $id): ?array
    {
        return $type && $id ? ['type' => $type, 'id' => $id] : null;
    }
}

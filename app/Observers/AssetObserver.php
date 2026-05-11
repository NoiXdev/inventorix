<?php

namespace App\Observers;

use App\Models\Asset;

class AssetObserver
{
    private const FIELD_EVENT_MAP = [
        'owner_id' => 'owner_changed',
        'place_id' => 'place_changed',
        'state'    => 'state_changed',
    ];

    public function updating(Asset $asset): void
    {
        foreach (self::FIELD_EVENT_MAP as $field => $event) {
            if (! $asset->isDirty($field)) {
                continue;
            }

            $from = $asset->getOriginal($field);
            $to   = $asset->getAttribute($field);

            // Serialize backed enums to their scalar value so the JSON column
            // stores 'new', not an AssetState object representation.
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
                    'to'   => $to,
                ])
                ->log($event);
        }
    }
}

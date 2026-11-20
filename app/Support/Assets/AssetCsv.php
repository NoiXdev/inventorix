<?php

namespace App\Support\Assets;

use Carbon\Carbon;
use Throwable;

class AssetCsv
{
    /** Canonical CSV column order, shared by export + import. */
    public const HEADERS = [
        'id', 'name', 'asset_tag', 'status', 'category', 'manufacturer', 'model', 'serial',
        'location', 'assigned_user', 'purchase_date', 'purchase_cost', 'supplier', 'order_number',
        'warranty_months', 'notes', 'tags',
    ];

    /**
     * The columns of files exported before the asset fields were reworked.
     * Such files still import; see fromLegacy().
     */
    public const LEGACY_HEADERS = [
        'id', 'status', 'category', 'manufacturer', 'model', 'location', 'owner',
        'serial_number', 'buy_date', 'guarantee_end', 'buy_price', 'buy_type', 'tags',
    ];

    /**
     * A legacy row in the current columns. The owner becomes the assigned
     * user, and the guarantee end becomes warranty months counted from the
     * buy date (it is dropped without one). The buy type is dropped.
     *
     * @param  array<string, string>  $row  keyed by LEGACY_HEADERS
     * @return array<string, string> keyed by HEADERS
     */
    public static function fromLegacy(array $row): array
    {
        $months = '';
        try {
            if (($row['buy_date'] ?? '') !== '' && ($row['guarantee_end'] ?? '') !== '') {
                $months = (string) max(0, (int) round(Carbon::parse($row['buy_date'])->floatDiffInMonths(Carbon::parse($row['guarantee_end']))));
            }
        } catch (Throwable) {
            // An unreadable date leaves the warranty empty; the row still imports.
        }

        return array_merge(array_fill_keys(self::HEADERS, ''), [
            'id' => $row['id'] ?? '',
            'status' => $row['status'] ?? '',
            'category' => $row['category'] ?? '',
            'manufacturer' => $row['manufacturer'] ?? '',
            'model' => $row['model'] ?? '',
            'serial' => $row['serial_number'] ?? '',
            'location' => $row['location'] ?? '',
            'assigned_user' => $row['owner'] ?? '',
            'purchase_date' => $row['buy_date'] ?? '',
            'purchase_cost' => $row['buy_price'] ?? '',
            'warranty_months' => $months,
            'tags' => $row['tags'] ?? '',
        ]);
    }
}

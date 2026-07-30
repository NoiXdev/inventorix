<?php

namespace App\Support\Assets;

class AssetCsv
{
    /** Canonical CSV column order, shared by export + import. */
    public const HEADERS = [
        'id', 'state', 'asset_type', 'manufacturer', 'model', 'place', 'owner',
        'serial_number', 'buy_date', 'guarantee_end', 'buy_price', 'buy_type', 'tags',
    ];
}

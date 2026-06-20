<?php

namespace App\DataObjects;

use App\Models\Asset;

readonly class WarrantyScanResult
{
    public function __construct(
        public Asset $asset,
        public string $milestone,
        public int $daysLeft,
    ) {}
}

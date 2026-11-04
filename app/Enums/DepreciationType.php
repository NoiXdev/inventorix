<?php

namespace App\Enums;

/** How a depreciation's `depreciation_min` floor value is expressed. */
enum DepreciationType: string
{
    case PERCENTAGE = 'percentage';
    case AMOUNT = 'amount';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PERCENTAGE => 'Prozent',
            self::AMOUNT => 'Betrag',
        };
    }
}

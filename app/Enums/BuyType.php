<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BuyType: string implements HasLabel
{
    case ONCE = 'once';
    case ABO = 'abo';
    public function getLabel(): ?string
    {
        return match ($this) {
            self::ONCE => 'Abo',
            self::ABO => 'Einmalig (gekauft)',
        };
    }

}

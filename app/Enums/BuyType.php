<?php

namespace App\Enums;

enum BuyType: string
{
    case ONCE = 'once';
    case ABO = 'abo';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ONCE => 'Einmalig (gekauft)',
            self::ABO => 'Abo',
        };
    }
}

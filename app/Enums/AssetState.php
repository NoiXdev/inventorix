<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AssetState: string implements HasLabel
{
    case NEW = 'new';
    case SOLD = 'sold';
    case STORAGE = 'storage';

    case LEND = 'lend';
    case DEFECT = 'defect';
    case UNDER_REPAIR = 'under-repair';
    case NEED_REPAIR = 'need-repair';
    case IN_USE = 'in-use';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NEW => 'Neu',
            self::DEFECT => 'Defekt',
            self::SOLD => 'Verkauft',
            self::STORAGE => 'Lager',
            self::LEND => 'Verleiht',
            self::IN_USE => 'In Benutzung',
            self::UNDER_REPAIR => 'In Reparatur',
            self::NEED_REPAIR => 'Benötigt Reparatur',
        };
    }

}

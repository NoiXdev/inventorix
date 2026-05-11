<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetState: string implements HasColor, HasLabel
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

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NEW => Color::Amber,
            self::DEFECT => Color::Red,
            self::SOLD => Color::Orange,
            self::STORAGE => Color::Gray,
            self::LEND => Color::Slate,
            self::IN_USE => Color::Green,
            self::UNDER_REPAIR => Color::Yellow,
            self::NEED_REPAIR => Color::Fuchsia,
        };
    }
}

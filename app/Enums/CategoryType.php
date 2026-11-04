<?php

namespace App\Enums;

enum CategoryType: string
{
    case ASSET = 'asset';
    case ACCESSORY = 'accessory';
    case CONSUMABLE = 'consumable';
    case COMPONENT = 'component';
    case LICENSE = 'license';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ASSET => 'Asset',
            self::ACCESSORY => 'Zubehör',
            self::CONSUMABLE => 'Verbrauchsmaterial',
            self::COMPONENT => 'Komponente',
            self::LICENSE => 'Lizenz',
        };
    }
}

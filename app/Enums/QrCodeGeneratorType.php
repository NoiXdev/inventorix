<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum QrCodeGeneratorType: string implements HasLabel
{
    case TXT = 'txt';
    public function getLabel(): ?string
    {
        return match ($this) {
            self::TXT => 'Text File',
        };
    }

}

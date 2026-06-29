<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AttachmentCategory: string implements HasLabel
{
    case RECHNUNG = 'rechnung';
    case FOTO = 'foto';
    case VIDEO = 'video';
    case DOKUMENT = 'dokument';
    case SONSTIGES = 'sonstiges';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::RECHNUNG => 'Rechnung',
            self::FOTO => 'Foto',
            self::VIDEO => 'Video',
            self::DOKUMENT => 'Dokument',
            self::SONSTIGES => 'Sonstiges',
        };
    }
}

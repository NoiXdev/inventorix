<?php

namespace App\Enums;

enum AttachmentCategory: string
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

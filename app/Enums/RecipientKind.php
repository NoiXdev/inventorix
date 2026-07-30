<?php

namespace App\Enums;

enum RecipientKind: string
{
    case INTERNAL = 'internal';
    case EXTERNAL = 'external';

    public function getLabel(): ?string
    {
        return trans('handover.recipient_kind.'.$this->value);
    }
}

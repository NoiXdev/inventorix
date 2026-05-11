<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RecipientKind: string implements HasLabel
{
    case INTERNAL = 'internal';
    case EXTERNAL = 'external';

    public function getLabel(): ?string
    {
        return trans('handover.recipient_kind.' . $this->value);
    }
}

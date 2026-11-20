<?php

namespace App\Enums;

enum HandoverType: string
{
    case ISSUE = 'issue';
    case LEND = 'lend';
    case RETURN_ = 'return';
    case RETURN_DEFECT = 'return_defect';

    public function getLabel(): ?string
    {
        return trans('handover.type.'.$this->value);
    }

    /** @return array<int, SystemStatus> the statuses an asset may be in for this handover */
    public function allowedStateFrom(): array
    {
        return match ($this) {
            self::ISSUE, self::LEND => [SystemStatus::NEW, SystemStatus::STORAGE],
            self::RETURN_, self::RETURN_DEFECT => [SystemStatus::IN_USE, SystemStatus::LEND],
        };
    }

    public function stateTo(): SystemStatus
    {
        return match ($this) {
            self::ISSUE => SystemStatus::IN_USE,
            self::LEND => SystemStatus::LEND,
            self::RETURN_ => SystemStatus::STORAGE,
            self::RETURN_DEFECT => SystemStatus::NEED_REPAIR,
        };
    }

    public function assignsRecipientAsOwner(): bool
    {
        return $this === self::ISSUE || $this === self::LEND;
    }
}

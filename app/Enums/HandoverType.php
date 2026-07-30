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

    /** @return array<int, AssetState> */
    public function allowedStateFrom(): array
    {
        return match ($this) {
            self::ISSUE, self::LEND => [AssetState::NEW, AssetState::STORAGE],
            self::RETURN_, self::RETURN_DEFECT => [AssetState::IN_USE, AssetState::LEND],
        };
    }

    public function stateTo(): AssetState
    {
        return match ($this) {
            self::ISSUE => AssetState::IN_USE,
            self::LEND => AssetState::LEND,
            self::RETURN_ => AssetState::STORAGE,
            self::RETURN_DEFECT => AssetState::NEED_REPAIR,
        };
    }

    public function assignsRecipientAsOwner(): bool
    {
        return $this === self::ISSUE || $this === self::LEND;
    }
}

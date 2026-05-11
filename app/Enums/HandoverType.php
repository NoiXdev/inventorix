<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum HandoverType: string implements HasLabel, HasColor
{
    case ISSUE = 'issue';
    case LEND = 'lend';
    case RETURN_ = 'return';
    case RETURN_DEFECT = 'return_defect';

    public function getLabel(): ?string
    {
        return trans('handover.type.' . $this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ISSUE         => Color::Green,
            self::LEND          => Color::Slate,
            self::RETURN_       => Color::Gray,
            self::RETURN_DEFECT => Color::Red,
        };
    }

    /** @return array<int, AssetState> */
    public function allowedStateFrom(): array
    {
        return match ($this) {
            self::ISSUE, self::LEND               => [AssetState::NEW, AssetState::STORAGE],
            self::RETURN_, self::RETURN_DEFECT    => [AssetState::IN_USE, AssetState::LEND],
        };
    }

    public function stateTo(): AssetState
    {
        return match ($this) {
            self::ISSUE          => AssetState::IN_USE,
            self::LEND           => AssetState::LEND,
            self::RETURN_        => AssetState::STORAGE,
            self::RETURN_DEFECT  => AssetState::NEED_REPAIR,
        };
    }

    public function assignsRecipientAsOwner(): bool
    {
        return $this === self::ISSUE || $this === self::LEND;
    }
}

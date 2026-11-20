<?php

namespace App\Enums;

/**
 * What a status label means for an asset. Stored as the three flags
 * deployable / pending / archived, of which at most one is set; none set
 * means undeployable.
 */
enum StatusLabelType: string
{
    case DEPLOYABLE = 'deployable';
    case PENDING = 'pending';
    case UNDEPLOYABLE = 'undeployable';
    case ARCHIVED = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::DEPLOYABLE => 'Einsatzbereit',
            self::PENDING => 'Ausstehend',
            self::UNDEPLOYABLE => 'Nicht einsatzbereit',
            self::ARCHIVED => 'Archiviert',
        };
    }

    public static function fromFlags(bool $deployable, bool $pending, bool $archived): self
    {
        return match (true) {
            $deployable => self::DEPLOYABLE,
            $pending => self::PENDING,
            $archived => self::ARCHIVED,
            default => self::UNDEPLOYABLE,
        };
    }

    /** @return array{deployable: bool, pending: bool, archived: bool} */
    public function flags(): array
    {
        return [
            'deployable' => $this === self::DEPLOYABLE,
            'pending' => $this === self::PENDING,
            'archived' => $this === self::ARCHIVED,
        ];
    }
}

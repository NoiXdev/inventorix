<?php

namespace App\Exceptions;

use RuntimeException;

class HandoverStateConflictException extends RuntimeException
{
    /** @param array<int, string> $assetIds */
    public function __construct(public readonly array $assetIds, ?string $message = null)
    {
        parent::__construct($message ?? 'Asset state changed since the handover was started.');
    }
}

<?php

namespace App\DataObjects;

use App\Enums\HandoverType;
use App\Enums\RecipientKind;

final class HandoverData
{
    /** @param array<int, string> $assetIds */
    public function __construct(
        public HandoverType $type,
        public RecipientKind $recipientKind,
        public ?string $recipientUserId,
        public string $recipientName,
        public ?string $recipientEmail,
        public array $assetIds,
        public ?string $accessories,
        public ?string $conditionNotes,
        public string $termsText,
        public string $signaturePngBase64,
        public ?string $signatureIp,
        public ?string $signatureUserAgent,
        public string $createdById,
    ) {}
}

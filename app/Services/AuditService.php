<?php

namespace App\Services;

use App\Models\Asset;
use App\Settings\AlertSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Audits an asset, as Snipe-IT does: it was seen, optionally at another
 * location; the last audit is now and the next one is the given date, else
 * today plus the audit interval. Recorded in the history as "audited".
 */
class AuditService
{
    public function __construct(private readonly AlertSettings $settings) {}

    /** The next audit date proposed after an audit today, or null without an interval. */
    public function proposedNextAudit(): ?Carbon
    {
        return $this->settings->audit_interval_months > 0
            ? today()->addMonthsNoOverflow($this->settings->audit_interval_months)
            : null;
    }

    public function audit(Asset $asset, ?string $locationId = null, ?CarbonInterface $nextAudit = null, ?string $note = null): Asset
    {
        $asset->last_audit_date = now();
        $asset->next_audit_date = $nextAudit ?? $this->proposedNextAudit();
        if ($locationId !== null) {
            $asset->location_id = $locationId;
        }

        $asset->historyEvent = ['event' => 'audited', 'note' => $note !== null && trim($note) !== '' ? $note : null];
        try {
            $asset->save();
        } finally {
            $asset->historyEvent = null;
        }

        return $asset;
    }
}

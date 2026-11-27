<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Audits and expected check-ins, as Snipe-IT's alert settings: how often
 * assets are audited, how early something counts as "due soon", and who
 * gets the daily overview.
 */
class AlertSettings extends Settings
{
    /** Send the daily overview and the reminders to assignees. */
    public bool $enabled;

    /** @var array<int, string> Recipients of the daily overview. */
    public array $recipients;

    /** Months until the next audit, proposed after an audit and set on new assets. 0 = no default. */
    public int $audit_interval_months;

    /** An audit counts as due this many days before its date. */
    public int $audit_warning_days;

    /** A check-in counts as due this many days before the expected date. */
    public int $checkin_warning_days;

    /** A license counts as expiring this many days before its expiration date. */
    public int $license_warning_days;

    public static function group(): string
    {
        return 'alerts';
    }
}

<?php

namespace App\Enums;

use App\Settings\AlertSettings;
use Illuminate\Database\Eloquent\Builder;

/**
 * Audits and check-ins that are due soon or overdue, as Snipe-IT's "due for
 * audit" and "due for check-in" lists. "Due" means within the warning window
 * but not yet past; "overdue" means the date has passed.
 */
enum AssetDue: string
{
    case AUDIT_DUE = 'audit_due';
    case AUDIT_OVERDUE = 'audit_overdue';
    case CHECKIN_DUE = 'checkin_due';
    case CHECKIN_OVERDUE = 'checkin_overdue';

    public function label(): string
    {
        return match ($this) {
            self::AUDIT_DUE => 'Audit due soon',
            self::AUDIT_OVERDUE => 'Audit overdue',
            self::CHECKIN_DUE => 'Check-in due soon',
            self::CHECKIN_OVERDUE => 'Check-in overdue',
        };
    }

    /**
     * Limit an asset query (the assets table may be joined) to this case.
     * Archived assets, and assets with an archived status, are never due
     * (as in Snipe-IT).
     */
    public function apply(Builder $query, AlertSettings $settings): Builder
    {
        $today = today();

        $query->where(fn (Builder $q) => $q->where('assets.archived', false)->orWhereNull('assets.archived'))
            ->whereDoesntHave('status', fn (Builder $q) => $q->where('archived', true));

        return match ($this) {
            self::AUDIT_DUE => $query->whereNotNull('assets.next_audit_date')
                ->whereDate('assets.next_audit_date', '>=', $today)
                ->whereDate('assets.next_audit_date', '<=', $today->copy()->addDays($settings->audit_warning_days)),
            self::AUDIT_OVERDUE => $query->whereNotNull('assets.next_audit_date')
                ->whereDate('assets.next_audit_date', '<', $today),
            // Only assets that are checked out can be due back.
            self::CHECKIN_DUE => $query->whereNotNull('assets.assignee_id')->whereNotNull('assets.expected_checkin')
                ->whereDate('assets.expected_checkin', '>=', $today)
                ->whereDate('assets.expected_checkin', '<=', $today->copy()->addDays($settings->checkin_warning_days)),
            self::CHECKIN_OVERDUE => $query->whereNotNull('assets.assignee_id')->whereNotNull('assets.expected_checkin')
                ->whereDate('assets.expected_checkin', '<', $today),
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $d) => ['value' => $d->value, 'label' => $d->label()], self::cases());
    }
}

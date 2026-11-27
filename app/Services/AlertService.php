<?php

namespace App\Services;

use App\Enums\AssetDue;
use App\Mail\AlertsDigest;
use App\Mail\CheckinReminder as CheckinReminderMail;
use App\Models\Asset;
use App\Models\CheckinReminder;
use App\Models\User;
use App\Settings\AlertSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Due and overdue check-ins and audits (Snipe-IT's expected check-in and
 * upcoming audit alerts): reminders to the users who have the assets and a
 * daily overview for the IT team.
 */
class AlertService
{
    public function __construct(private readonly AlertSettings $settings) {}

    /** @return Collection<int, Asset> */
    public function assets(AssetDue $due): Collection
    {
        $column = in_array($due, [AssetDue::AUDIT_DUE, AssetDue::AUDIT_OVERDUE], true) ? 'next_audit_date' : 'expected_checkin';

        return $due->apply(Asset::query(), $this->settings)
            ->with('assignee', 'location')
            ->orderBy($column)
            ->get();
    }

    /** @return array<string, Collection<int, Asset>> AssetDue value => assets, non-empty only, most urgent first */
    public function digestSections(): array
    {
        $sections = [];
        foreach ([AssetDue::CHECKIN_OVERDUE, AssetDue::CHECKIN_DUE, AssetDue::AUDIT_OVERDUE, AssetDue::AUDIT_DUE] as $due) {
            $assets = $this->assets($due);
            if ($assets->isNotEmpty()) {
                $sections[$due->value] = $assets;
            }
        }

        return $sections;
    }

    /** Send the overview to the configured recipients. @return int assets listed (0: nothing sent) */
    public function sendDigest(): int
    {
        $sections = $this->digestSections();
        if ($sections === [] || $this->settings->recipients === []) {
            return 0;
        }
        Mail::to($this->settings->recipients)->send(new AlertsDigest($sections));

        return array_sum(array_map(fn (Collection $c) => $c->count(), $sections));
    }

    /**
     * Remind each user of their assets that are due or overdue, once per
     * asset and expected date. @return int users reminded
     */
    public function sendReminders(): int
    {
        $due = $this->assets(AssetDue::CHECKIN_OVERDUE)->concat($this->assets(AssetDue::CHECKIN_DUE))
            ->filter(fn (Asset $a) => $a->assignee instanceof User && filled($a->assignee->email))
            ->reject(fn (Asset $a) => CheckinReminder::query()
                ->where('asset_id', $a->id)
                ->whereDate('expected_checkin', $a->expected_checkin)
                ->exists());

        $sent = 0;
        foreach ($due->groupBy('assignee_id') as $assets) {
            /** @var User $user */
            $user = $assets->first()->assignee;
            Mail::to($user->email)->send(new CheckinReminderMail($user, $assets->values()));
            foreach ($assets as $asset) {
                CheckinReminder::create([
                    'asset_id' => $asset->id,
                    'expected_checkin' => $asset->expected_checkin->toDateString(),
                    'sent_at' => now(),
                ]);
            }
            $sent++;
        }

        return $sent;
    }
}

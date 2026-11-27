<?php

namespace App\Services;

use App\Enums\AssetDue;
use App\Mail\AlertsDigest;
use App\Mail\CheckinReminder as CheckinReminderMail;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\CheckinReminder;
use App\Models\CheckoutAcceptance;
use App\Models\Consumable;
use App\Models\License;
use App\Models\User;
use App\Settings\AlertSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

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

    /**
     * Licenses that expire within the warning window or have expired (not
     * yet terminated), and licenses with fewer free seats than their minimum.
     *
     * Also accessories and consumables with too few units left.
     *
     * @return array<string, Collection<int, License|Accessory|Consumable>> 'license_expiring' / 'license_low' / 'accessory_low' / 'consumable_low', non-empty only
     */
    public function licenseSections(): array
    {
        $today = today();
        $expiring = License::query()
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<=', $today->copy()->addDays($this->settings->license_warning_days))
            ->where(fn ($q) => $q->whereNull('termination_date')->orWhereDate('termination_date', '>', $today))
            ->orderBy('expiration_date')
            ->get();
        $low = License::query()->whereNotNull('min_amt')
            ->withCount(['freeSeats as free_seats_count'])
            ->get()
            ->filter(fn (License $l) => $l->isLow((int) $l->free_seats_count))
            ->values();

        // Accessories whose remaining units are at or below their minimum (Snipe-IT's inventory alert).
        $accessoriesLow = Accessory::query()->whereNotNull('min_amt')->where('archived', false)
            ->withCount('checkouts')->orderBy('name')->get()
            ->filter(fn (Accessory $a) => $a->isLowStock())
            ->values();

        $consumablesLow = Consumable::query()->whereNotNull('min_amt')->where('archived', false)
            ->withSum('assignments as handed_out', 'qty')->orderBy('name')->get()
            ->filter(fn (Consumable $c) => $c->isLowStock())
            ->values();

        // Acceptances still open three days after the link went out.
        $acceptancesPending = CheckoutAcceptance::query()->pending()->with('user')
            ->whereRaw('coalesce(reminded_at, sent_at, created_at) <= ?', [now()->subDays(3)])
            ->oldest()->get();

        return array_filter([
            'acceptance_pending' => $acceptancesPending,
            'license_expiring' => $expiring,
            'license_low' => $low,
            'accessory_low' => $accessoriesLow,
            'consumable_low' => $consumablesLow,
        ], fn (Collection $c) => $c->isNotEmpty());
    }

    /** Send the overview to the configured recipients. @return int entries listed (0: nothing sent) */
    public function sendDigest(): int
    {
        $sections = $this->digestSections();
        $licenses = $this->licenseSections();
        if (($sections === [] && $licenses === []) || $this->settings->recipients === []) {
            return 0;
        }
        Mail::to($this->settings->recipients)->send(new AlertsDigest($sections, $licenses));

        return array_sum(array_map(fn (Collection $c) => $c->count(), [...$sections, ...$licenses]));
    }

    /**
     * Remind each user of their assets that are due or overdue, once per
     * asset, user and expected date (a new assignee is reminded again).
     *
     * @return int users reminded
     */
    public function sendReminders(): int
    {
        $due = $this->assets(AssetDue::CHECKIN_OVERDUE)->concat($this->assets(AssetDue::CHECKIN_DUE))
            ->filter(fn (Asset $a) => $a->assignee instanceof User && filled($a->assignee->email));

        // Who was already reminded of which date, keyed asset|user|date.
        $reminded = CheckinReminder::query()->whereIn('asset_id', $due->pluck('id'))->get()
            ->map(fn (CheckinReminder $r) => $this->reminderKey($r->asset_id, $r->user_id, $r->expected_checkin->toDateString()))
            ->flip();
        $due = $due->reject(fn (Asset $a) => $reminded->has(
            $this->reminderKey($a->id, $a->assignee_id, $a->expected_checkin->toDateString())
        ));

        $sent = 0;
        foreach ($due->groupBy('assignee_id') as $assets) {
            /** @var User $user */
            $user = $assets->first()->assignee;
            try {
                Mail::to($user->email)->send(new CheckinReminderMail($user, $assets->values()));
            } catch (Throwable $e) {
                // One bad address must not stop the others; it is retried tomorrow.
                report($e);

                continue;
            }
            foreach ($assets as $asset) {
                CheckinReminder::create([
                    'asset_id' => $asset->id,
                    'user_id' => $user->id,
                    'expected_checkin' => $asset->expected_checkin->toDateString(),
                    'sent_at' => now(),
                ]);
            }
            $sent++;
        }

        return $sent;
    }

    private function reminderKey(string $assetId, string $userId, string $date): string
    {
        return "{$assetId}|{$userId}|{$date}";
    }
}

<?php

namespace App\Services;

use App\Enums\StatusLabelType;
use App\Exceptions\CheckoutNotAllowedException;
use App\Models\Asset;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Checks assets out to a user, location or asset and back in. The asset
 * dialogs, asset creation and handovers all go through here, so the
 * assignee, the check-out/-in dates and counters, the location and the
 * history entry always change together.
 *
 * Location rules (as in Snipe-IT):
 * - checked out to a location: the asset moves there;
 * - checked out to an asset: it takes that asset's location, or else its
 *   default location;
 * - checked out to a user: the location stays (users have no location);
 * - checked in: it goes to the given location, else its default location,
 *   else it stays where it is.
 * Assets assigned to the asset move along with it.
 */
class CheckoutService
{
    /**
     * Why the asset cannot be checked out right now, or null when it can: it
     * must not be deleted, not be assigned already, and have a deployable
     * status.
     */
    public function checkoutBlocker(Asset $asset): ?string
    {
        if ($asset->trashed()) {
            return 'The asset is deleted.';
        }
        if ($asset->assignee_id !== null) {
            return 'The asset is already checked out. Check it in first.';
        }
        $status = $asset->status_id ? StatusLabel::withTrashed()->find($asset->status_id) : null;
        if ($status?->type !== StatusLabelType::DEPLOYABLE) {
            return 'Only assets with a deployable status can be checked out.';
        }

        return null;
    }

    /**
     * Check the asset out. A null target is a checkout to someone outside the
     * system (a handover to an external recipient): it is dated and counted,
     * but leaves the asset unassigned.
     *
     * @throws CheckoutNotAllowedException
     */
    public function checkOut(
        Asset $asset,
        ?Model $target,
        ?string $statusId = null,
        ?CarbonInterface $checkoutAt = null,
        ?CarbonInterface $expectedCheckin = null,
        ?string $note = null,
    ): Asset {
        return DB::transaction(function () use ($asset, $target, $statusId, $checkoutAt, $expectedCheckin, $note): Asset {
            // Re-read under a lock: two simultaneous checkouts must not both
            // see the asset as available.
            $locked = Asset::withTrashed()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($blocker = $this->checkoutBlocker($locked)) {
                throw new CheckoutNotAllowedException($blocker);
            }
            $this->guardTarget($locked, $target);

            if ($statusId !== null) {
                $status = StatusLabel::query()->find($statusId);
                if ($status?->type !== StatusLabelType::DEPLOYABLE) {
                    throw new CheckoutNotAllowedException('An asset can only be checked out with a deployable status.');
                }
                $locked->status_id = $statusId;
            }

            $locked->assignTo($target);
            $locked->last_checkout = $checkoutAt ?? now();
            $locked->expected_checkin = $expectedCheckin;
            $locked->accepted = null;
            $locked->checkout_counter++;

            $newLocation = match (true) {
                $target instanceof Location => $target->id,
                $target instanceof Asset => $target->location_id ?? $target->rtd_location_id ?? $locked->location_id,
                default => $locked->location_id,
            };
            $locked->location_id = $newLocation;

            $this->saveLogged($locked, 'checked_out', $note);
            $this->moveAssignedAssets($locked);

            return $asset->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Check several assets out to the same target, all or none: if any of
     * them cannot be checked out, nothing changes and the exception names
     * every asset that failed and why.
     *
     * @param  list<string>  $assetIds
     * @return int the number of assets checked out
     *
     * @throws CheckoutNotAllowedException
     */
    public function checkOutMany(
        array $assetIds,
        Model $target,
        ?string $statusId = null,
        ?CarbonInterface $checkoutAt = null,
        ?CarbonInterface $expectedCheckin = null,
        ?string $note = null,
    ): int {
        return DB::transaction(function () use ($assetIds, $target, $statusId, $checkoutAt, $expectedCheckin, $note): int {
            // Lock all rows up front, in a fixed order, so overlapping bulk
            // checkouts wait for each other instead of deadlocking.
            $assets = Asset::withTrashed()->whereKey($assetIds)->orderBy('id')->lockForUpdate()->get();
            if ($assets->count() !== count(array_unique($assetIds))) {
                throw new CheckoutNotAllowedException('Some of the selected assets no longer exist.');
            }

            $problems = [];
            foreach ($assets as $asset) {
                try {
                    if ($blocker = $this->checkoutBlocker($asset)) {
                        throw new CheckoutNotAllowedException($blocker);
                    }
                    $this->guardTarget($asset, $target);
                } catch (CheckoutNotAllowedException $e) {
                    $problems[] = $asset->displayName().': '.$e->getMessage();
                }
            }
            if ($problems !== []) {
                throw new CheckoutNotAllowedException('Nothing was checked out. '.implode(' ', $problems));
            }

            foreach ($assets as $asset) {
                $this->checkOut($asset, $target, $statusId, $checkoutAt, $expectedCheckin, $note);
            }

            return $assets->count();
        });
    }

    /**
     * Check several assets in, all or none: each must be checked out; if one
     * is not, nothing changes and the exception names every asset that
     * failed. Each goes to $locationId, else its own default location.
     *
     * @param  list<string>  $assetIds
     * @return int the number of assets checked in
     *
     * @throws CheckoutNotAllowedException
     */
    public function checkInMany(
        array $assetIds,
        ?string $statusId = null,
        ?string $locationId = null,
        ?CarbonInterface $checkinAt = null,
        ?string $note = null,
    ): int {
        return DB::transaction(function () use ($assetIds, $statusId, $locationId, $checkinAt, $note): int {
            $assets = Asset::query()->whereKey($assetIds)->orderBy('id')->lockForUpdate()->get();
            if ($assets->count() !== count(array_unique($assetIds))) {
                throw new CheckoutNotAllowedException('Some of the selected assets no longer exist.');
            }

            $notOut = $assets->filter(fn (Asset $a) => $a->assignee_id === null);
            if ($notOut->isNotEmpty()) {
                throw new CheckoutNotAllowedException('Nothing was checked in. '.$notOut
                    ->map(fn (Asset $a) => $a->displayName().': The asset is not checked out.')->implode(' '));
            }

            foreach ($assets as $asset) {
                $this->checkIn($asset, $statusId, $locationId, $checkinAt, $note);
            }

            return $assets->count();
        });
    }

    /**
     * Check the asset in: unassign it, date and count the check-in and move
     * it to $locationId, else its default location, else leave it.
     */
    public function checkIn(
        Asset $asset,
        ?string $statusId = null,
        ?string $locationId = null,
        ?CarbonInterface $checkinAt = null,
        ?string $note = null,
    ): Asset {
        return DB::transaction(function () use ($asset, $statusId, $locationId, $checkinAt, $note): Asset {
            $locked = Asset::withTrashed()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($statusId !== null) {
                $locked->status_id = $statusId;
            }

            $locked->assignTo(null);
            $locked->last_checkin = $checkinAt ?? now();
            $locked->expected_checkin = null;
            $locked->accepted = null;
            $locked->checkin_counter++;
            $locked->location_id = $locationId ?? $locked->rtd_location_id ?? $locked->location_id;

            $this->saveLogged($locked, 'checked_in', $note);
            $this->moveAssignedAssets($locked);

            return $asset->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /** @throws CheckoutNotAllowedException */
    private function guardTarget(Asset $asset, ?Model $target): void
    {
        if ($target === null || $target instanceof User) {
            return;
        }
        if ($target instanceof Location) {
            if ($target->trashed()) {
                throw new CheckoutNotAllowedException('The location is deleted.');
            }

            return;
        }
        if ($target instanceof Asset) {
            if ($target->is($asset)) {
                throw new CheckoutNotAllowedException('An asset cannot be checked out to itself.');
            }
            if ($target->trashed()) {
                throw new CheckoutNotAllowedException('The target asset is deleted.');
            }
            if ($target->assignee_type === Asset::class && $target->assignee_id === $asset->id) {
                throw new CheckoutNotAllowedException('An asset cannot be checked out to an asset assigned to it.');
            }

            return;
        }

        throw new CheckoutNotAllowedException('Assets can only be checked out to a user, location or asset.');
    }

    /**
     * Save, recording the change in the asset history as a check-out or
     * check-in (with the note) instead of a plain update.
     */
    private function saveLogged(Asset $asset, string $event, ?string $note): void
    {
        $asset->historyEvent = ['event' => $event, 'note' => $note !== null && trim($note) !== '' ? $note : null];
        try {
            $asset->save();
        } finally {
            $asset->historyEvent = null;
        }
    }

    /** Assets assigned to this asset are where it is. Also used when an asset's location is edited. */
    public function moveAssignedAssets(Asset $asset): void
    {
        if ($asset->location_id === null) {
            return;
        }

        $asset->assignedAssets()
            ->where(fn ($q) => $q->whereNull('location_id')->orWhere('location_id', '!=', $asset->location_id))
            ->get()
            ->each(function (Asset $child) use ($asset): void {
                $child->location_id = $asset->location_id;
                $child->save();
            });
    }
}

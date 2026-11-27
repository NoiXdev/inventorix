<?php

namespace App\Services;

use App\Exceptions\AccessoryCheckoutException;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Checking accessories out to users, locations and assets and back in
 * (Snipe-IT's accessory checkout): one row per unit, only as many as are
 * left, recorded in the accessory's history.
 */
class AccessoryService
{
    /**
     * Check $qty units out to the target.
     *
     * @throws AccessoryCheckoutException
     */
    public function checkOut(Accessory $accessory, User|Location|Asset $target, int $qty = 1, ?string $note = null, ?CarbonInterface $at = null): int
    {
        if ($qty < 1) {
            throw new AccessoryCheckoutException('Check out at least one.');
        }
        if (method_exists($target, 'trashed') && $target->trashed()) {
            throw new AccessoryCheckoutException('The target is deleted.');
        }

        return DB::transaction(function () use ($accessory, $target, $qty, $note, $at): int {
            // Lock the accessory: two check-outs must not both take the last units.
            $locked = Accessory::query()->whereKey($accessory->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new AccessoryCheckoutException('The accessory is deleted.');
            }
            if ($locked->archived) {
                throw new AccessoryCheckoutException('An archived accessory cannot be checked out.');
            }
            $remaining = $locked->remaining();
            if ($remaining < $qty) {
                throw new AccessoryCheckoutException($remaining === 0
                    ? 'None of this accessory is left.'
                    : "Only {$remaining} of this accessory are left.");
            }

            $when = $at ?? now();
            $first = null;
            for ($i = 0; $i < $qty; $i++) {
                $checkout = new AccessoryCheckout(['note' => $this->note($note)]);
                $checkout->accessory_id = $locked->id;
                $checkout->assigned_type = $target::class;
                $checkout->assigned_id = $target->id;
                $checkout->checked_out_at = $when;
                $checkout->save();
                $first ??= $checkout;
            }

            $this->log($locked, 'checked_out', $target, $qty, $note);
            if ($target instanceof User) {
                app(AcceptanceService::class)->checkedOut($locked, $target, $first->id);
            }

            return $qty;
        });
    }

    /** Check one checked-out unit back in. */
    public function checkIn(AccessoryCheckout $checkout, ?string $note = null): void
    {
        DB::transaction(function () use ($checkout, $note): void {
            $locked = AccessoryCheckout::query()->whereKey($checkout->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new AccessoryCheckoutException('This accessory was already checked in.');
            }
            $holder = $locked->assigned;
            $locked->delete();
            $this->log($locked->accessory, 'checked_in', $holder, 1, $note);
            if ($holder instanceof User) {
                app(AcceptanceService::class)->checkedIn($locked->accessory, $holder, $locked->id);
            }
        });
    }

    /** Check in everything a user, location or asset holds, e.g. before deleting it. @return int units */
    public function checkInAllFor(User|Location|Asset $holder, ?string $note = null): int
    {
        $checkouts = AccessoryCheckout::query()->where('assigned_type', $holder::class)->where('assigned_id', $holder->id)->get();
        foreach ($checkouts as $checkout) {
            $this->checkIn($checkout, $note);
        }

        return $checkouts->count();
    }

    private function log(Accessory $accessory, string $event, User|Location|Asset|null $target, int $qty, ?string $note): void
    {
        $kind = match (true) {
            $target instanceof User => 'User',
            $target instanceof Location => 'Location',
            $target instanceof Asset => 'Asset',
            default => 'Former holder',
        };
        $name = match (true) {
            $target instanceof User => $target->name ?? $target->email,
            $target instanceof Location => $target->name,
            $target instanceof Asset => $target->displayName(),
            default => null,
        };

        activity()
            ->performedOn($accessory)
            ->causedBy(auth()->user())
            ->event($event)
            ->withProperties(array_filter([
                'target' => trim($kind.': '.($name ?? '—')).($qty > 1 ? " (×{$qty})" : ''),
                'qty' => $qty,
                'note' => $this->note($note),
            ], fn ($v) => $v !== null))
            ->log($event);
    }

    private function note(?string $note): ?string
    {
        return $note !== null && trim($note) !== '' ? $note : null;
    }
}

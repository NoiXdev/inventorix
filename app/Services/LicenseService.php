<?php

namespace App\Services;

use App\Exceptions\LicenseSeatException;
use App\Models\Asset;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Seats of a license (Snipe-IT's license seats): the seat count, checking
 * a free seat out to a user or an asset, and checking it back in. A seat of
 * a license that is not reassignable is retired when it comes back.
 * Check-outs and check-ins are recorded in the license's history.
 */
class LicenseService
{
    /**
     * Make the license have exactly $seats seats: add free seats, or remove
     * free ones. Checked-out and retired seats are never removed.
     *
     * @throws LicenseSeatException
     */
    public function syncSeats(License $license, int $seats): void
    {
        if ($seats < 1) {
            throw new LicenseSeatException('A license has at least one seat.');
        }

        DB::transaction(function () use ($license, $seats): void {
            License::query()->whereKey($license->id)->lockForUpdate()->first();
            $total = $license->licenseSeats()->count();

            if ($seats > $total) {
                $license->licenseSeats()->createMany(array_fill(0, $seats - $total, []));
            } elseif ($seats < $total) {
                $remove = $total - $seats;
                $free = $license->freeSeats()->limit($remove)->pluck('id');
                if ($free->count() < $remove) {
                    $used = $total - $license->freeSeats()->count();
                    throw new LicenseSeatException("{$used} seats are checked out or used up; the license cannot have fewer. Check seats in first.");
                }
                LicenseSeat::query()->whereKey($free)->delete();
            }

            if ($license->seats !== $seats) {
                $license->seats = $seats;
                $license->save();
            }
        });
    }

    /**
     * Check a free seat out to a user or an asset.
     *
     * @throws LicenseSeatException
     */
    public function checkOut(License $license, User|Asset $target, ?string $note = null, ?CarbonInterface $at = null): LicenseSeat
    {
        if ($license->trashed()) {
            throw new LicenseSeatException('The license is deleted.');
        }
        if ($target instanceof Asset && $target->trashed()) {
            throw new LicenseSeatException('The asset is deleted.');
        }

        return DB::transaction(function () use ($license, $target, $note, $at): LicenseSeat {
            // Lock a free seat, so two check-outs never take the same one.
            $seat = $license->freeSeats()->orderBy('created_at')->orderBy('id')->lockForUpdate()->first();
            if ($seat === null) {
                throw new LicenseSeatException('All seats of this license are checked out.');
            }

            $seat->assigned_user_id = $target instanceof User ? $target->id : null;
            $seat->assigned_asset_id = $target instanceof Asset ? $target->id : null;
            $seat->checked_out_at = $at ?? now();
            $seat->notes = $this->note($note);
            $seat->save();

            $this->log($license, 'checked_out', $target instanceof User ? 'User' : 'Asset', $seat->setRelations([])->load('user', 'asset')->holderName(), $note);

            return $seat;
        });
    }

    /**
     * Check a seat back in; a seat of a license that is not reassignable is
     * retired and cannot be checked out again.
     *
     * @throws LicenseSeatException
     */
    public function checkIn(LicenseSeat $seat, ?string $note = null): LicenseSeat
    {
        return DB::transaction(function () use ($seat, $note): LicenseSeat {
            $locked = LicenseSeat::query()->whereKey($seat->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isCheckedOut()) {
                throw new LicenseSeatException('The seat is not checked out.');
            }

            $holder = $locked->load('user', 'asset')->holderName();
            $kind = $locked->assigned_user_id !== null ? 'User' : 'Asset';
            $license = $locked->license;

            $locked->assigned_user_id = null;
            $locked->assigned_asset_id = null;
            $locked->checked_out_at = null;
            $locked->notes = null;
            $locked->retired = ! $license->reassignable;
            $locked->save();

            $this->log($license, 'checked_in', $kind, $holder, $note);

            return $seat->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private function log(License $license, string $event, string $kind, ?string $holder, ?string $note): void
    {
        activity()
            ->performedOn($license)
            ->causedBy(auth()->user())
            ->event($event)
            ->withProperties(array_filter([
                'target' => trim($kind.': '.($holder ?? '—')),
                'note' => $this->note($note),
            ], fn ($v) => $v !== null))
            ->log($event);
    }

    private function note(?string $note): ?string
    {
        return $note !== null && trim($note) !== '' ? $note : null;
    }
}

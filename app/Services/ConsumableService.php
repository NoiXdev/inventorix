<?php

namespace App\Services;

use App\Exceptions\ConsumableCheckoutException;
use App\Models\Consumable;
use App\Models\ConsumableAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Handing consumables to users (Snipe-IT's consumable checkout): only as
 * many as are left, never from an archived consumable, recorded in the
 * consumable's history. Consumables are not checked back in.
 */
class ConsumableService
{
    /** @throws ConsumableCheckoutException */
    public function checkOut(Consumable $consumable, User $user, int $qty = 1, ?string $note = null, ?CarbonInterface $at = null): ConsumableAssignment
    {
        if ($qty < 1) {
            throw new ConsumableCheckoutException('Check out at least one.');
        }

        return DB::transaction(function () use ($consumable, $user, $qty, $note, $at): ConsumableAssignment {
            // Lock the consumable: two check-outs must not both take the last units.
            $locked = Consumable::query()->whereKey($consumable->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new ConsumableCheckoutException('The consumable is deleted.');
            }
            if ($locked->archived) {
                throw new ConsumableCheckoutException('An archived consumable cannot be checked out.');
            }
            $remaining = $locked->remaining();
            if ($remaining < $qty) {
                throw new ConsumableCheckoutException($remaining === 0
                    ? 'None of this consumable is left.'
                    : "Only {$remaining} of this consumable are left.");
            }

            $assignment = ConsumableAssignment::create([
                'consumable_id' => $locked->id,
                'user_id' => $user->id,
                'user_name' => $user->name ?? $user->email,
                'qty' => $qty,
                'note' => $note !== null && trim($note) !== '' ? $note : null,
                'checked_out_at' => $at ?? now(),
            ]);

            activity()
                ->performedOn($locked)
                ->causedBy(auth()->user())
                ->event('checked_out')
                ->withProperties(array_filter([
                    'target' => 'User: '.($user->name ?? $user->email ?? '—').($qty > 1 ? " (×{$qty})" : ''),
                    'qty' => $qty,
                    'note' => $assignment->note,
                ], fn ($v) => $v !== null))
                ->log('checked_out');

            app(AcceptanceService::class)->checkedOut($locked, $user, $assignment->id);

            return $assignment;
        });
    }
}

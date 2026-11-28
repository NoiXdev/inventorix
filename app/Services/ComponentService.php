<?php

namespace App\Services;

use App\Exceptions\ComponentCheckoutException;
use App\Models\Asset;
use App\Models\Component;
use App\Models\ComponentAsset;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Installing components in assets and taking them out again (Snipe-IT's
 * component checkout): one row per component and asset holding a quantity,
 * only as many as are left, recorded in the component's history.
 */
class ComponentService
{
    /**
     * Install $qty units in the asset.
     *
     * @throws ComponentCheckoutException
     */
    public function checkOut(Component $component, Asset $asset, int $qty, ?string $note = null, ?CarbonInterface $at = null): ComponentAsset
    {
        if ($qty < 1) {
            throw new ComponentCheckoutException('Check out at least one.');
        }
        if ($asset->trashed()) {
            throw new ComponentCheckoutException('The asset is deleted.');
        }

        return DB::transaction(function () use ($component, $asset, $qty, $note, $at): ComponentAsset {
            // The row ComponentController::update locks too: two check-outs must not both take the last units.
            $locked = Component::query()->whereKey($component->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new ComponentCheckoutException('The component is deleted.');
            }
            $remaining = $locked->remaining();
            if ($remaining < $qty) {
                throw new ComponentCheckoutException($remaining === 0
                    ? 'None of this component is left.'
                    : "Only {$remaining} of this component are left.");
            }

            $row = ComponentAsset::query()->where('component_id', $locked->id)->where('asset_id', $asset->id)->first()
                ?? new ComponentAsset(['assigned_qty' => 0]);
            $row->component_id = $locked->id;
            $row->asset_id = $asset->id;
            $row->assigned_qty += $qty;
            $row->checked_out_at ??= $at ?? now();
            if ($this->note($note) !== null) {
                $row->note = $this->note($note);
            }
            $row->save();

            $this->log($locked, 'checked_out', $asset, $qty, $note);

            return $row;
        });
    }

    /**
     * Take $qty units back out of the asset; the row goes when none is left in it.
     *
     * @throws ComponentCheckoutException
     */
    public function checkIn(ComponentAsset $row, int $qty, ?string $note = null): void
    {
        if ($qty < 1) {
            throw new ComponentCheckoutException('Check in at least one.');
        }

        DB::transaction(function () use ($row, $qty, $note): void {
            $locked = ComponentAsset::query()->whereKey($row->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new ComponentCheckoutException('This component was already checked in.');
            }
            if ($qty > $locked->assigned_qty) {
                throw new ComponentCheckoutException("Only {$locked->assigned_qty} of this component are installed here.");
            }

            if ($qty === $locked->assigned_qty) {
                $locked->delete();
            } else {
                $locked->decrement('assigned_qty', $qty);
            }
            $this->log($locked->component, 'checked_in', $locked->asset, $qty, $note);
        });
    }

    /** Take everything out of an asset, e.g. before deleting it. @return int units */
    public function checkInAllFor(Asset $asset, ?string $note = null): int
    {
        $units = 0;
        foreach (ComponentAsset::query()->where('asset_id', $asset->id)->get() as $row) {
            $units += $row->assigned_qty;
            $this->checkIn($row, $row->assigned_qty, $note);
        }

        return $units;
    }

    private function log(Component $component, string $event, Asset $asset, int $qty, ?string $note): void
    {
        activity()
            ->performedOn($component)
            ->causedBy(auth()->user())
            ->event($event)
            ->withProperties(array_filter([
                'target' => ['type' => 'asset', 'id' => $asset->id, 'name' => $asset->displayName()],
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

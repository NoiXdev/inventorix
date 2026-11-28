<?php

namespace App\Services;

use App\Exceptions\AccessoryCheckoutException;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\ConsumableCheckoutException;
use App\Exceptions\KitCheckoutException;
use App\Exceptions\LicenseSeatException;
use App\Models\Asset;
use App\Models\Kit;
use App\Models\KitItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Checking a kit out to a user: every line (the chosen assets of each model,
 * the license seats, accessories and consumables) is checked out in one go.
 * All or nothing: if one item fails, nothing changes and the exception names
 * the item.
 */
class KitCheckoutService
{
    public function __construct(
        private readonly CheckoutService $assets,
        private readonly LicenseService $licenses,
        private readonly AccessoryService $accessories,
        private readonly ConsumableService $consumables,
    ) {}

    /**
     * @param  array<string, list<string>>  $assetIds  kit item id => the asset ids chosen for that model line
     * @return array{assets: int, licenses: int, accessories: int, consumables: int}
     *
     * @throws KitCheckoutException
     */
    public function checkOut(Kit $kit, User $user, array $assetIds, ?string $note = null): array
    {
        $note = "Kit: {$kit->name}".($note !== null && trim($note) !== '' ? ' — '.trim($note) : '');
        $lines = $kit->lines();
        if ($lines->isEmpty()) {
            throw new KitCheckoutException('The kit is empty.');
        }

        return DB::transaction(function () use ($lines, $user, $assetIds, $note): array {
            $counts = ['assets' => 0, 'licenses' => 0, 'accessories' => 0, 'consumables' => 0];

            foreach ($lines as $line) {
                if (! $line->isAvailable()) {
                    throw new KitCheckoutException(($line->item?->name ?? 'An item').' is no longer available.');
                }

                match ($line->typeKey()) {
                    'model' => $counts['assets'] += $this->assets($line, $user, $assetIds[$line->id] ?? [], $note),
                    'license' => $counts['licenses'] += $this->licenses($line, $user, $note),
                    'accessory' => $counts['accessories'] += $this->accessories($line, $user, $note),
                    'consumable' => $counts['consumables'] += $this->consumables($line, $user, $note),
                    default => throw new KitCheckoutException('The kit has a line of an unknown type.'),
                };
            }

            return $counts;
        });
    }

    /** @param  list<string>  $ids */
    private function assets(KitItem $line, User $user, array $ids, string $note): int
    {
        $ids = array_values(array_unique($ids));
        if (count($ids) !== $line->qty) {
            throw new KitCheckoutException("{$line->item->name}: choose {$line->qty} assets.");
        }

        foreach ($ids as $id) {
            $asset = Asset::query()->find($id);
            if ($asset === null || $asset->model_id !== $line->item_id) {
                throw new KitCheckoutException("{$line->item->name}: a chosen asset is not of this model.");
            }
            try {
                // Locks the asset and checks again that it is still available.
                $this->assets->checkOut($asset, $user, note: $note);
            } catch (CheckoutNotAllowedException $e) {
                throw $this->failed($asset->displayName(), $e);
            }
        }

        return count($ids);
    }

    private function licenses(KitItem $line, User $user, string $note): int
    {
        try {
            for ($i = 0; $i < $line->qty; $i++) {
                $this->licenses->checkOut($line->item, $user, $note);
            }
        } catch (LicenseSeatException $e) {
            throw $this->failed($line->item->name, $e);
        }

        return $line->qty;
    }

    private function accessories(KitItem $line, User $user, string $note): int
    {
        try {
            return $this->accessories->checkOut($line->item, $user, $line->qty, $note);
        } catch (AccessoryCheckoutException $e) {
            throw $this->failed($line->item->name, $e);
        }
    }

    private function consumables(KitItem $line, User $user, string $note): int
    {
        try {
            $this->consumables->checkOut($line->item, $user, $line->qty, $note);
        } catch (ConsumableCheckoutException $e) {
            throw $this->failed($line->item->name, $e);
        }

        return $line->qty;
    }

    private function failed(string $item, \Throwable $e): KitCheckoutException
    {
        return new KitCheckoutException("{$item}: {$e->getMessage()} Nothing was changed.", previous: $e);
    }
}

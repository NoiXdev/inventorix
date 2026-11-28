<?php

namespace App\Models;

use App\Concerns\Creator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A predefined kit (Snipe-IT's Kit): a bundle of models, licenses, accessories and consumables. */
#[Fillable(['name', 'notes'])]
class Kit extends Model
{
    use Creator, HasFactory, HasUuids, SoftDeletes;

    /** @return HasMany<KitItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(KitItem::class);
    }

    /**
     * The lines with their item, in a stable order: lines have no position of
     * their own, so group them by type (models, licenses, accessories,
     * consumables), then by name.
     *
     * @return Collection<int, KitItem>
     */
    public function lines(): Collection
    {
        $this->loadMissing('items.item');
        $types = array_flip(array_values(KitItem::TYPES));

        return $this->items->sortBy([
            fn (KitItem $a, KitItem $b) => ($types[$a->item_type] ?? 99) <=> ($types[$b->item_type] ?? 99),
            fn (KitItem $a, KitItem $b) => strcasecmp((string) $a->item?->name, (string) $b->item?->name),
            fn (KitItem $a, KitItem $b) => $a->id <=> $b->id,
        ])->values();
    }
}

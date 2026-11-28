<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One line of a kit: a quantity of an asset model, license, accessory or consumable. */
#[Fillable(['item_type', 'item_id', 'qty'])]
class KitItem extends Model
{
    use HasUuids;

    /** What a line can hold: form key => morph class. */
    public const TYPES = [
        'model' => AssetModel::class,
        'license' => License::class,
        'accessory' => Accessory::class,
        'consumable' => Consumable::class,
    ];

    protected function casts(): array
    {
        return ['qty' => 'integer'];
    }

    /** @return BelongsTo<Kit, $this> */
    public function kit(): BelongsTo
    {
        return $this->belongsTo(Kit::class);
    }

    public function item(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /** The form key of the line's kind: model, license, accessory or consumable. */
    public function typeKey(): ?string
    {
        return array_search($this->item_type, self::TYPES, true) ?: null;
    }

    /** The item still exists and has not been deleted. */
    public function isAvailable(): bool
    {
        $item = $this->item;

        return $item !== null && ! $item->trashed();
    }
}

<?php

namespace App\Models;

use App\Concerns\Creator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
}

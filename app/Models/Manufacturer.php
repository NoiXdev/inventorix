<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name'])]
class Manufacturer extends Model
{
    use HasFactory, HasUuids;

    public function models(): HasMany
    {
        return $this->hasMany(AssetModel::class, 'manufacturer_id');
    }

    /**
     * All assets belonging to any of this manufacturer's models, reached
     * through asset_models.manufacturer_id -> assets.model_id. Lets the
     * controller use a single withCount() instead of a query per row.
     */
    public function assets(): HasManyThrough
    {
        return $this->hasManyThrough(Asset::class, AssetModel::class, 'manufacturer_id', 'model_id');
    }
}

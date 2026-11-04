<?php

namespace App\Models;

use App\Concerns\Creator;
use App\Enums\DepreciationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'months', 'depreciation_min', 'fiscal_year_start_month', 'rate_multiplier', 'depreciation_type'])]
class Depreciation extends Model
{
    use Creator, HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'depreciation_min' => 'decimal:2',
            'fiscal_year_start_month' => 'integer',
            'rate_multiplier' => 'decimal:2',
            'depreciation_type' => DepreciationType::class,
        ];
    }

    public function models(): HasMany
    {
        return $this->hasMany(AssetModel::class, 'depreciation_id');
    }
}

<?php

namespace App\Models;

use App\Concerns\Creator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// image is set by the controller from an upload, never mass-assigned:
// accepting it as input would let a client point the row at any stored file.
#[Fillable([
    'name', 'manufacturer_id', 'model_number', 'min_amt', 'category_id', 'require_serial',
    'depreciation_id', 'eol', 'fieldset_id', 'notes', 'requestable',
])]
class AssetModel extends Model
{
    use Creator, HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'min_amt' => 'integer',
            'eol' => 'integer',
            'require_serial' => 'boolean',
            'requestable' => 'boolean',
        ];
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function depreciation(): BelongsTo
    {
        return $this->belongsTo(Depreciation::class);
    }

    public function fieldset(): BelongsTo
    {
        return $this->belongsTo(CustomFieldset::class, 'fieldset_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'model_id');
    }
}

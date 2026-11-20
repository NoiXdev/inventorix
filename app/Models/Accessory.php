<?php

namespace App\Models;

use App\Concerns\Creator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// image is set by the controller from an upload, never mass-assigned:
// accepting it as input would let a client point the row at any stored file.
#[Fillable([
    'name', 'category_id', 'qty', 'requestable', 'archived', 'location_id', 'default_purchase_cost',
    'min_amt', 'manufacturer_id', 'model_number', 'default_supplier_id', 'notes',
])]
class Accessory extends Model
{
    use Creator, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'accessories';

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'min_amt' => 'integer',
            'requestable' => 'boolean',
            'archived' => 'boolean',
            'default_purchase_cost' => 'decimal:2',
        ];
    }

    // Related records are soft-deleted; trashed ones are included so an
    // accessory keeps showing what it was linked to.

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class)->withTrashed();
    }

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id')->withTrashed();
    }

    /** Stock is at or below the minimum the accessory should be kept at. */
    public function isLowStock(): bool
    {
        return $this->min_amt !== null && $this->qty <= $this->min_amt;
    }
}

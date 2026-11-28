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
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A component (Snipe-IT's Component): stock of parts that are installed into
 * assets and can be taken out again. What is left is the quantity minus what
 * is installed.
 */
// image is set by the controller from an upload, never mass-assigned.
#[Fillable([
    'name', 'category_id', 'manufacturer_id', 'model_number', 'serial', 'location_id', 'supplier_id',
    'order_number', 'purchase_date', 'purchase_cost', 'qty', 'min_amt', 'notes',
])]
class Component extends Model
{
    use Creator, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'min_amt' => 'integer',
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class)->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /** @return HasMany<ComponentAsset, $this> */
    public function checkouts(): HasMany
    {
        return $this->hasMany(ComponentAsset::class);
    }

    /**
     * Units installed. Uses a loaded `checked_out` sum when there is one
     * (withSum('checkouts as checked_out', 'assigned_qty'), NULL when nothing
     * is installed), else sums.
     */
    public function checkedOutQty(): int
    {
        return array_key_exists('checked_out', $this->getAttributes())
            ? (int) $this->checked_out
            : (int) $this->checkouts()->sum('assigned_qty');
    }

    public function remaining(): int
    {
        return max(0, $this->qty - $this->checkedOutQty());
    }

    /** What is left is at or below the minimum the component should be kept at (as Snipe-IT). */
    public function isLowStock(): bool
    {
        return $this->min_amt !== null && $this->remaining() <= $this->min_amt;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'category_id', 'manufacturer_id', 'model_number', 'serial', 'location_id', 'supplier_id',
                'order_number', 'purchase_date', 'purchase_cost', 'qty', 'min_amt', 'notes',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('component');
    }
}

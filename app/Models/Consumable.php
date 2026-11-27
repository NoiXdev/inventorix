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
 * A consumable (Snipe-IT's Consumable): stock that is handed to users and
 * not taken back. What is left is the quantity minus what was handed out.
 */
// image is set by the controller from an upload, never mass-assigned.
#[Fillable([
    'name', 'category_id', 'manufacturer_id', 'supplier_id', 'location_id', 'model_number', 'item_no',
    'qty', 'min_amt', 'purchase_date', 'purchase_cost', 'order_number', 'requestable', 'archived', 'notes',
])]
class Consumable extends Model
{
    use Creator, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'min_amt' => 'integer',
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'requestable' => 'boolean',
            'archived' => 'boolean',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ConsumableAssignment::class);
    }

    /** Units handed out; uses a loaded `handed_out` sum when there is one. */
    public function handedOut(): int
    {
        return (int) ($this->handed_out ?? $this->assignments()->sum('qty'));
    }

    public function remaining(): int
    {
        return max(0, $this->qty - $this->handedOut());
    }

    /** What is left is at or below the minimum (as Snipe-IT). */
    public function isLowStock(): bool
    {
        return $this->min_amt !== null && $this->remaining() <= $this->min_amt;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'category_id', 'manufacturer_id', 'supplier_id', 'location_id', 'model_number', 'item_no',
                'qty', 'min_amt', 'purchase_date', 'purchase_cost', 'order_number', 'requestable', 'archived', 'notes',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('consumable');
    }
}

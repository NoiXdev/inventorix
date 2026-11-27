<?php

namespace App\Models;

use App\Concerns\Creator;
use Illuminate\Contracts\Encryption\DecryptException;
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
 * A software license with a number of seats (Snipe-IT's License). The seat
 * count is kept in sync with the license_seats rows by LicenseService.
 */
#[Fillable([
    'name', 'product_key', 'category_id', 'manufacturer_id', 'supplier_id', 'depreciation_id',
    'licensed_to_name', 'licensed_to_email', 'reassignable', 'maintained', 'requestable',
    'purchase_date', 'purchase_cost', 'order_number', 'purchase_order',
    'expiration_date', 'termination_date', 'min_amt', 'notes',
])]
class License extends Model
{
    use Creator, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $hidden = ['product_key'];

    protected function casts(): array
    {
        return [
            'product_key' => 'encrypted',
            'seats' => 'integer',
            'reassignable' => 'boolean',
            'maintained' => 'boolean',
            'requestable' => 'boolean',
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'expiration_date' => 'date',
            'termination_date' => 'date',
            'min_amt' => 'integer',
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

    public function depreciation(): BelongsTo
    {
        return $this->belongsTo(Depreciation::class)->withTrashed();
    }

    public function licenseSeats(): HasMany
    {
        return $this->hasMany(LicenseSeat::class);
    }

    /** Seats that can be checked out: not assigned, not retired. */
    public function freeSeats(): HasMany
    {
        return $this->licenseSeats()->whereNull('assigned_user_id')->whereNull('assigned_asset_id')->where('retired', false);
    }

    /** The product key, or null when it cannot be decrypted (e.g. after the app key changed). */
    public function readableProductKey(): ?string
    {
        try {
            return $this->product_key;
        } catch (DecryptException) {
            return null;
        }
    }

    /** Fewer free seats than the minimum the license should keep. */
    public function isLow(int $free): bool
    {
        return $this->min_amt !== null && $free < $this->min_amt;
    }

    public function getActivitylogOptions(): LogOptions
    {
        // The product key is never written to the history.
        return LogOptions::defaults()
            ->logOnly([
                'name', 'category_id', 'manufacturer_id', 'supplier_id', 'depreciation_id', 'seats',
                'licensed_to_name', 'licensed_to_email', 'reassignable', 'maintained', 'requestable',
                'purchase_date', 'purchase_cost', 'order_number', 'purchase_order',
                'expiration_date', 'termination_date', 'min_amt', 'notes',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('license');
    }
}

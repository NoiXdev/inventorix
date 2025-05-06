<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use HasUuids;

    protected $fillable = [
        'asset_type_id',
        'manufacturer_id',
        'model',
        'serial_number',
        'buy_date',
        'guarantee_end',
        'invoice',
        'owner_id',
    ];

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    protected function casts(): array
    {
        return [
            'buy_date' => 'date',
            'guarantee_end' => 'date',
        ];
    }
}

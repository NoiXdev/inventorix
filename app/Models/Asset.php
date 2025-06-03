<?php

namespace App\Models;

use App\Enums\AssetState;
use App\Enums\BuyType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Tags\HasTags;

class Asset extends Model
{
    use HasUuids, HasTags;

    protected $fillable = [
        'id',
        'asset_type_id',
        'model_id',
        'serial_number',
        'buy_date',
        'guarantee_end',
        'invoice',
        'owner_id',
        'buy_price',
        'buy_type',
        'state',
    ];

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'asset_id')
            ->orderBy('open_date', 'asc');
    }

    protected function casts(): array
    {
        return [
            'buy_date' => 'date',
            'guarantee_end' => 'date',
            'buy_type' => BuyType::class,
            'state' => AssetState::class,
        ];
    }
}

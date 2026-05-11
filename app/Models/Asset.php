<?php

namespace App\Models;

use App\Enums\AssetState;
use App\Enums\BuyType;
use App\Observers\AssetObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Tags\HasTags;

#[Fillable(['state', 'asset_type_id', 'owner_id', 'place_id', 'model_id', 'serial_number', 'buy_date', 'buy_type', 'buy_price', 'guarantee_end', 'invoice'])]
#[ObservedBy(AssetObserver::class)]
class Asset extends Model
{
    use HasUuids, HasTags, HasFactory, LogsActivity;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'asset_type_id',
                'model_id',
                'owner_id',
                'place_id',
                'serial_number',
                'buy_date',
                'buy_type',
                'buy_price',
                'guarantee_end',
                'invoice',
                'state',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('asset');
    }
}

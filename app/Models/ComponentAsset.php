<?php

namespace App\Models;

use App\Concerns\Creator;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Units of a component installed in an asset. */
class ComponentAsset extends Model
{
    use Creator, HasUuids;

    protected $table = 'component_assets';

    protected $fillable = ['component_id', 'asset_id', 'assigned_qty', 'note', 'checked_out_at'];

    protected function casts(): array
    {
        return ['assigned_qty' => 'integer', 'checked_out_at' => 'datetime'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class)->withTrashed();
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }
}

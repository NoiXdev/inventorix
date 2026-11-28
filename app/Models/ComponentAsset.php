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

    /**
     * What is installed in an asset, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function installedIn(Asset $asset): array
    {
        return self::query()->where('asset_id', $asset->id)->whereHas('component')->with('component')
            ->orderBy('checked_out_at')->get()
            ->map(fn (self $r) => [
                'id' => $r->id,
                'component_id' => $r->component_id,
                'name' => (string) $r->component->name,
                'serial' => $r->component->serial,
                'qty' => $r->assigned_qty,
                'checked_out_at' => $r->checked_out_at?->format('Y-m-d'),
                'note' => $r->note,
            ])->all();
    }
}

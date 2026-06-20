<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['asset_id', 'guarantee_end', 'milestone', 'sent_at'])]
class WarrantyNotification extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'guarantee_end' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}

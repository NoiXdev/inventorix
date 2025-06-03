<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    protected $fillable = [
        'notes',
        'title',
        'open_date',
        'closed_date',
    ];

    protected function casts(): array
    {
        return [
            'open_date' => 'datetime',
            'closed_date' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['asset_id', 'notes', 'title', 'open_date', 'closed_date'])]
class Incident extends Model
{
    use HasFactory, LogsActivity;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'notes', 'open_date', 'closed_date'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('incident');
    }
}

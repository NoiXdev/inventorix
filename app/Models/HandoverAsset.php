<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class HandoverAsset extends Pivot
{
    use HasUuids;

    protected $table = 'handover_asset';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $fillable = [
        'handover_id', 'asset_id',
        'status_from_id', 'status_to_id',
        'owner_from_id', 'owner_to_id',
    ];
}

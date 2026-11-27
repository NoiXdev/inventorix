<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** An assignee was reminded that an asset is due back on expected_checkin. */
class CheckinReminder extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['asset_id', 'user_id', 'expected_checkin', 'sent_at'];

    protected function casts(): array
    {
        return ['expected_checkin' => 'date', 'sent_at' => 'datetime'];
    }
}

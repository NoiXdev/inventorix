<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AssetModel extends Model
{
    use HasUuids;
    protected $fillable = [
        'name',
    ];
}

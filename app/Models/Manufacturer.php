<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Manufacturer extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
    ];

    public function models()
    {
        return $this->hasMany(AssetModel::class, 'manufacturer_id');
    }
}

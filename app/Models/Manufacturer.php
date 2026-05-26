<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
class Manufacturer extends Model
{
    use HasFactory, HasUuids;

    public function models()
    {
        return $this->hasMany(AssetModel::class, 'manufacturer_id');
    }
}

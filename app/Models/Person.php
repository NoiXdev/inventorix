<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['firstname', 'lastname', 'name', 'email'])]
class Person extends Model
{
    use HasFactory, HasUuids;

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'owner_id');
    }
}

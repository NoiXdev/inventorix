<?php

namespace App\Models;

use App\Concerns\Creator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// image is set by the controller from an upload, never mass-assigned:
// accepting it as input would let a client point the row at any stored file.
#[Fillable(['name', 'address', 'address2', 'city', 'state', 'country', 'zip', 'phone', 'fax', 'email', 'contact', 'url', 'notes'])]
class Supplier extends Model
{
    use Creator, HasFactory, HasUuids, SoftDeletes;

    public function accessories(): HasMany
    {
        return $this->hasMany(Accessory::class, 'default_supplier_id');
    }
}

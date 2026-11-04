<?php

namespace App\Models;

use App\Concerns\Creator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name'])]
class CustomFieldset extends Model
{
    use Creator, HasFactory, HasUuids, SoftDeletes;

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(CustomField::class, 'custom_field_has_custom_fieldsets')
            ->withPivot(['order', 'required'])
            ->orderByPivot('order');
    }
}

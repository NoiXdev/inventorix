<?php

namespace App\Models;

use App\Concerns\Creator;
use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// image is set by the controller from an upload, never mass-assigned:
// accepting it as input would let a client point the row at any stored file.
#[Fillable(['name', 'eula_text', 'use_default_eula', 'require_acceptance', 'alert_on_response', 'category_type', 'checkin_email', 'notes'])]
class Category extends Model
{
    use Creator, HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'use_default_eula' => 'boolean',
            'require_acceptance' => 'boolean',
            'alert_on_response' => 'boolean',
            'checkin_email' => 'boolean',
            'category_type' => CategoryType::class,
        ];
    }

    public function models(): HasMany
    {
        return $this->hasMany(AssetModel::class, 'category_id');
    }
}

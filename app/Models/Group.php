<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** A named set of permissions; a user gets the union of their groups' grants. */
#[Fillable(['name', 'permissions', 'notes'])]
class Group extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** Whether the group grants admin or superuser (membership is then superuser-only to change). */
    public function isPrivileged(): bool
    {
        $permissions = $this->permissions ?? [];

        return (int) ($permissions[Permission::ADMIN->value] ?? 0) === 1
            || (int) ($permissions[Permission::SUPERUSER->value] ?? 0) === 1;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'permissions'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('group');
    }
}

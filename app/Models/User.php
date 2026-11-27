<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['firstname', 'lastname', 'name', 'email', 'password', 'login_enabled', 'remember_token', 'entra_id'])]
class User extends Authenticatable
{
    use CausesActivity, HasFactory, HasUuids, LogsActivity, Notifiable;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'login_enabled' => 'boolean',
            'permissions' => 'array',
        ];
    }

    /** Assets assigned to this user. */
    public function assets(): MorphMany
    {
        return $this->morphMany(Asset::class, 'assignee');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['permissions', 'login_enabled'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('user');
    }

    /**
     * Whether the user holds a permission: a user-level deny beats everything
     * granted elsewhere, superuser covers all, admin covers all but the
     * superuser-only permissions, then the user's own grant, then any group.
     */
    public function hasAccess(Permission|string $permission): bool
    {
        $permission = $permission instanceof Permission ? $permission : Permission::tryFrom($permission);
        if ($permission === null) {
            return false;
        }

        if ($this->grants(Permission::SUPERUSER)) {
            return true;
        }

        if (! $permission->isSuperuserOnly() && $this->grants(Permission::ADMIN)) {
            return true;
        }

        return $this->grants($permission);
    }

    public function isSuperuser(): bool
    {
        return $this->grants(Permission::SUPERUSER);
    }

    /** @return array<string, bool> every catalogue permission => whether the user holds it */
    public function effectivePermissions(): array
    {
        $effective = [];
        foreach (Permission::cases() as $permission) {
            $effective[$permission->value] = $this->hasAccess($permission);
        }

        return $effective;
    }

    /**
     * Every catalogue permission => whether it is held and where it comes from:
     * a group name, 'user', 'admin', 'superuser', or null when not granted.
     * Mirrors hasAccess().
     *
     * @return array<string, array{granted: bool, via: string|null}>
     */
    public function permissionSources(): array
    {
        $superuser = $this->grantSource(Permission::SUPERUSER);
        $admin = $superuser === null ? $this->grantSource(Permission::ADMIN) : null;

        $sources = [];
        foreach (Permission::cases() as $permission) {
            $via = match (true) {
                $permission === Permission::SUPERUSER => $superuser,
                $superuser !== null => 'superuser',
                ! $permission->isSuperuserOnly() && $admin !== null => 'admin',
                default => $this->grantSource($permission),
            };
            $sources[$permission->value] = ['granted' => $via !== null, 'via' => $via];
        }

        return $sources;
    }

    /** The user's own grant or deny, else any group's grant; no admin/superuser reach. */
    private function grants(Permission $permission): bool
    {
        return $this->grantSource($permission) !== null;
    }

    /** 'user' or the granting group's name; null when denied on the user or not granted. */
    private function grantSource(Permission $permission): ?string
    {
        $own = ($this->permissions ?? [])[$permission->value] ?? null;
        if ($own !== null && (int) $own !== 0) {
            return (int) $own === 1 ? 'user' : null;
        }

        $this->loadMissing('groups');

        return $this->groups
            ->first(fn (Group $g) => (int) (($g->permissions ?? [])[$permission->value] ?? 0) === 1)
            ?->name;
    }
}

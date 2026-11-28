<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['firstname', 'lastname', 'name', 'email', 'password', 'login_enabled', 'remember_token', 'entra_id',
    'employee_num', 'jobtitle', 'department_id', 'manager_id', 'location_id', 'phone', 'mobile', 'start_date', 'end_date', 'notes'])]
class User extends Authenticatable
{
    use CausesActivity, HasFactory, HasUuids, LogsActivity, Notifiable;

    /** Why the last active superuser cannot lose superuser access or login. */
    public const LAST_SUPERUSER = 'At least one active superuser must remain.';

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
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /** Assets assigned to this user. */
    public function assets(): MorphMany
    {
        return $this->morphMany(Asset::class, 'assignee');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    /** The users this user manages. @return HasMany<User, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
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
     * Whether the user holds a permission: superuser covers all, admin covers
     * all but the superuser-only permissions (both also when granted by a
     * group, and checked before the user's own map, so a user-level deny does
     * not beat them), then the user's own grant or deny, then any group.
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

    /** Whether this user is the only one left who is a superuser and can sign in. */
    public function isLastActiveSuperuser(): bool
    {
        if (! $this->login_enabled || ! $this->isSuperuser()) {
            return false;
        }

        return ! static::activeSuperuserExists($this);
    }

    /** Whether anyone (optionally ignoring one user) is a superuser who can sign in. */
    public static function activeSuperuserExists(?User $except = null): bool
    {
        return static::query()
            ->where('login_enabled', true)
            ->when($except, fn ($q) => $q->whereKeyNot($except->getKey()))
            ->with('groups')
            ->lazy()
            ->contains(fn (User $user) => $user->isSuperuser());
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

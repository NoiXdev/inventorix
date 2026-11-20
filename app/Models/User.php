<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\CausesActivity;

#[Fillable(['firstname', 'lastname', 'name', 'email', 'password', 'login_enabled', 'remember_token', 'entra_id'])]
class User extends Authenticatable
{
    use CausesActivity, HasFactory, HasUuids, Notifiable;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'login_enabled' => 'boolean',
        ];
    }

    /** Assets assigned to this user. */
    public function assets(): MorphMany
    {
        return $this->morphMany(Asset::class, 'assignee');
    }
}

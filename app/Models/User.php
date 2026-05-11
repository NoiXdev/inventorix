<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Events\UserCreatingEvent;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\CausesActivity;

#[Fillable(['name', 'firstname', 'lastname', 'email', 'password', 'login_enabled', 'remember_token', 'entra_id'])]
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasUuids, CausesActivity;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->login_enabled;
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'login_enabled' => 'boolean'
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'owner_id');
    }
}

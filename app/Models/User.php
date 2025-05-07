<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Events\UserCreatingEvent;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasUuids;

    protected $fillable = [
        'id',
        'name',
        'email',
        'firstname',
        'lastname',
        'password',
        'login_enabled'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected $dispatchesEvents = [
        'creating' => UserCreatingEvent::class
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
}

<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\EntraLoginDisabledException;
use App\Exceptions\Auth\EntraTenantMismatchException;
use App\Exceptions\Auth\EntraUserNotProvisionedException;
use App\Models\User;
use Laravel\Socialite\Two\User as SocialiteUser;

class EntraIdAuthService
{
    public function assertTenantMatches(SocialiteUser $msUser): void
    {
        $expected = config('services.microsoft-azure.tenant');
        $actual   = $msUser->user['tid'] ?? null;

        if ($expected === null || $actual === null || $actual !== $expected) {
            throw new EntraTenantMismatchException();
        }
    }

    public function resolveUser(SocialiteUser $msUser): User
    {
        $user = User::query()->where('entra_id', $msUser->id)->first();

        if ($user === null) {
            $email = $this->extractEmail($msUser);
            if ($email !== null) {
                $user = User::query()
                    ->whereNull('entra_id')
                    ->whereRaw('LOWER(email) = ?', [strtolower($email)])
                    ->first();

                if ($user !== null) {
                    $user->entra_id = $msUser->id;
                    $user->save();
                }
            }
        }

        if ($user === null) {
            throw new EntraUserNotProvisionedException();
        }

        if (! $user->login_enabled) {
            throw new EntraLoginDisabledException();
        }

        return $user;
    }

    private function extractEmail(SocialiteUser $msUser): ?string
    {
        $email = $msUser->email
            ?? ($msUser->user['mail'] ?? null)
            ?? ($msUser->user['userPrincipalName'] ?? null);

        return ($email === null || $email === '') ? null : $email;
    }
}

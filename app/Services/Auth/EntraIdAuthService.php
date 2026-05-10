<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\EntraTenantMismatchException;
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
}

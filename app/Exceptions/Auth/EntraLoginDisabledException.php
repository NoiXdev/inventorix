<?php

namespace App\Exceptions\Auth;

class EntraLoginDisabledException extends EntraAuthException
{
    public function getUserMessage(): string
    {
        return __('Your account is disabled. Contact an administrator.');
    }
}

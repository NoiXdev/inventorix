<?php

namespace App\Exceptions\Auth;

class EntraTenantMismatchException extends EntraAuthException
{
    public function getUserMessage(): string
    {
        return __('This Microsoft account is not from the authorized tenant.');
    }
}

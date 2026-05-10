<?php

namespace App\Exceptions\Auth;

class EntraUserNotProvisionedException extends EntraAuthException
{
    public function getUserMessage(): string
    {
        return __('Your Microsoft account is not authorized for this app. Contact an administrator.');
    }
}

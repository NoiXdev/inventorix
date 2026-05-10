<?php

namespace App\Exceptions\Auth;

use RuntimeException;

abstract class EntraAuthException extends RuntimeException
{
    abstract public function getUserMessage(): string;
}

<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AuthSettings extends Settings
{
    public bool $microsoft_enabled;

    public ?string $microsoft_client_id;

    public ?string $microsoft_client_secret;

    public ?string $microsoft_redirect;

    public ?string $microsoft_tenant;

    public static function group(): string
    {
        return 'auth';
    }

    public static function encrypted(): array
    {
        return [
            'microsoft_client_secret',
        ];
    }
}

<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $app_name;

    /** Used by categories set to "use the default EULA". */
    public string $default_eula;

    public static function group(): string
    {
        return 'general';
    }
}

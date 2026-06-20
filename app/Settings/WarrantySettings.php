<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class WarrantySettings extends Settings
{
    public bool $enabled;

    /** @var array<int, string> */
    public array $recipients;

    /** @var array<int, int> */
    public array $lead_days;

    public static function group(): string
    {
        return 'warranty';
    }
}

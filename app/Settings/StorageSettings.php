<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class StorageSettings extends Settings
{
    public ?string $key;

    public ?string $secret;

    public ?string $region;

    public ?string $bucket;

    public ?string $endpoint;

    public bool $use_path_style_endpoint;

    public ?string $url;

    public static function group(): string
    {
        return 'storage';
    }

    public static function encrypted(): array
    {
        return ['secret'];
    }

    public function isConfigured(): bool
    {
        return filled($this->key) && filled($this->secret) && filled($this->bucket);
    }
}

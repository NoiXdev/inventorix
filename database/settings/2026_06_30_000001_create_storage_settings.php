<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('storage.key', env('AWS_ACCESS_KEY_ID'));
        $this->migrator->addEncrypted('storage.secret', env('AWS_SECRET_ACCESS_KEY'));
        $this->migrator->add('storage.region', env('AWS_DEFAULT_REGION', 'us-east-1'));
        $this->migrator->add('storage.bucket', env('AWS_BUCKET'));
        $this->migrator->add('storage.endpoint', env('AWS_ENDPOINT'));
        $this->migrator->add('storage.use_path_style_endpoint', (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', false));
        $this->migrator->add('storage.url', env('AWS_URL'));
    }
};

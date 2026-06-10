<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add(
            'auth.microsoft_enabled',
            filter_var(env('MS_LOGIN_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        );
        $this->migrator->add('auth.microsoft_client_id', env('MS_CLIENT_ID'));
        $this->migrator->addEncrypted('auth.microsoft_client_secret', env('MS_CLIENT_SECRET'));
        $this->migrator->add('auth.microsoft_redirect', env('MS_REDIRECT_URI'));
        $this->migrator->add('auth.microsoft_tenant', env('MS_TENANT_ID'));
    }
};

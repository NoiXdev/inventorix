<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add(
            'auth.multi_factor_enabled',
            filter_var(env('AUTH_MULTIFACTOR_AUTH_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        );
        $this->migrator->add(
            'auth.multi_factor_force',
            filter_var(env('AUTH_MULTIFACTOR_AUTH_FORCE', false), FILTER_VALIDATE_BOOLEAN),
        );
        $this->migrator->add(
            'auth.multi_factor_recoverable',
            filter_var(env('AUTH_MULTIFACTOR_AUTH_RECOVERABLE', false), FILTER_VALIDATE_BOOLEAN),
        );
    }
};

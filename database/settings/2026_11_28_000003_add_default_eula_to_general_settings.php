<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // The EULA categories use when "use default EULA" is set (Snipe-IT's default EULA).
        $this->migrator->add('general.default_eula', '');
    }
};

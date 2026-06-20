<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('warranty.enabled', false);
        $this->migrator->add('warranty.recipients', []);
        $this->migrator->add('warranty.lead_days', [90, 30, 7]);
    }
};

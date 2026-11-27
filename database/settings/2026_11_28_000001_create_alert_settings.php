<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('alerts.enabled', false);
        $this->migrator->add('alerts.recipients', []);
        $this->migrator->add('alerts.audit_interval_months', 12);
        $this->migrator->add('alerts.audit_warning_days', 14);
        $this->migrator->add('alerts.checkin_warning_days', 7);
    }
};

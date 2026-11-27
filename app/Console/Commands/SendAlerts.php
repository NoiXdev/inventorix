<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use App\Settings\AlertSettings;
use Illuminate\Console\Command;
use Throwable;

class SendAlerts extends Command
{
    protected $signature = 'alerts:send';

    protected $description = 'Remind users of assets due back and email the IT team an overview of due check-ins and audits.';

    public function handle(AlertSettings $settings, AlertService $alerts): int
    {
        if (! $settings->enabled) {
            $this->info('Alerts are disabled; skipping.');

            return self::SUCCESS;
        }

        try {
            $users = $alerts->sendReminders();
            $listed = $alerts->sendDigest();
        } catch (Throwable $e) {
            $this->error('Failed to send alerts: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->info("Reminded {$users} user(s); overview lists {$listed} asset(s).");

        return self::SUCCESS;
    }
}

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

        // Reminders and the overview fail independently of each other.
        $ok = true;
        try {
            $users = $alerts->sendReminders();
            $this->info("Reminded {$users} user(s).");
        } catch (Throwable $e) {
            $this->error('Failed to send reminders: '.$e->getMessage());
            report($e);
            $ok = false;
        }
        try {
            $listed = $alerts->sendDigest();
            $this->info("Overview lists {$listed} asset(s).");
        } catch (Throwable $e) {
            $this->error('Failed to send the overview: '.$e->getMessage());
            report($e);
            $ok = false;
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use App\Mail\WarrantyExpiryDigest;
use App\Models\WarrantyNotification;
use App\Services\WarrantyScanner;
use App\Settings\WarrantySettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ScanWarrantyExpiries extends Command
{
    protected $signature = 'warranty:scan-expiries';

    protected $description = 'Scan asset guarantee end dates and email a grouped expiry digest.';

    public function handle(WarrantySettings $settings): int
    {
        if (! $settings->enabled || $settings->recipients === []) {
            $this->info('Warranty notifications disabled or no recipients configured; skipping.');

            return self::SUCCESS;
        }

        $results = (new WarrantyScanner($settings->lead_days))->scan();

        if ($results->isEmpty()) {
            $this->info('No warranty milestones reached.');

            return self::SUCCESS;
        }

        try {
            Mail::to($settings->recipients)->send(new WarrantyExpiryDigest($results));
        } catch (Throwable $e) {
            $this->error('Failed to send warranty digest: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        foreach ($results as $result) {
            WarrantyNotification::create([
                'asset_id' => $result->asset->id,
                'guarantee_end' => $result->asset->guarantee_end->toDateString(),
                'milestone' => $result->milestone,
                'sent_at' => now(),
            ]);
        }

        $this->info("Sent warranty digest for {$results->count()} asset(s).");

        return self::SUCCESS;
    }
}

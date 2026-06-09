<?php

namespace App\Listeners;

use App\Support\ApplySettings;
use Illuminate\Queue\Events\JobProcessing;

class ApplySettingsToJob
{
    public function __construct(protected ApplySettings $applySettings) {}

    public function handle(JobProcessing $event): void
    {
        ($this->applySettings)();
    }
}

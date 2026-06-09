<?php

namespace App\Providers;

use App\Listeners\ApplySettingsToJob;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Azure\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        URL::forceScheme('https');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            SocialiteWasCalled::class,
            function ($event) {
                $event->extendSocialite(
                    'microsoft-azure',
                    Provider::class,
                );
            },
        );

        Event::listen(JobProcessing::class, ApplySettingsToJob::class);
    }
}

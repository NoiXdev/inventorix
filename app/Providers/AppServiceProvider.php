<?php

namespace App\Providers;

use App\Listeners\ApplySettingsToJob;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Schema\Blueprint;
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

        ResetPassword::createUrlUsing(fn (object $notifiable, string $token) => url(
            '/app/reset-password/'.$token.'?email='.urlencode($notifiable->getEmailForPasswordReset()),
        ));

        // Audit columns to pair with the App\Concerns\Creator trait.
        // `after()` only applies on MySQL/MariaDB; SQLite ignores it. It is only
        // valid when altering a table: MariaDB rejects AFTER inside CREATE TABLE,
        // where the columns simply land in declaration order instead.
        Blueprint::macro('creator', function (bool $withDeleted = true) {
            $column = function (string $name, string $after) {
                $definition = $this->foreignUuid($name)->nullable();

                if (! $this->creating()) {
                    $definition->after($after);
                }

                $definition->constrained('users')->nullOnDelete();
            };

            $column('created_by', 'created_at');
            $column('updated_by', 'updated_at');

            if ($withDeleted) {
                $column('deleted_by', 'deleted_at');
            }
        });

        Blueprint::macro('dropCreator', function (bool $withDeleted = true) {
            // dropConstrainedForeignId() removes the foreign key before the column.
            // A plain dropColumn() fails on MariaDB with "Cannot drop index ...:
            // needed in a foreign key constraint" — SQLite tolerates it, so the
            // test suite would not catch the difference.
            $this->dropConstrainedForeignId('created_by');
            $this->dropConstrainedForeignId('updated_by');

            if ($withDeleted) {
                $this->dropConstrainedForeignId('deleted_by');
            }
        });
    }
}

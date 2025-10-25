<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\SmtpServiceManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (!SmtpServiceManager::isRunning()) {
            SmtpServiceManager::start();
            logger()->info('SMTP Catcher started automatically');
        }

        // Register shutdown handler to stop SMTP when app closes
        register_shutdown_function(function () {
            SmtpServiceManager::stop();
        });
    }
}

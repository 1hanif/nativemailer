<?php

namespace App\Providers;

use Native\Desktop\Facades\Window;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Symfony\Component\Process\Process;
use Illuminate\Support\ServiceProvider;
use App\Services\SmtpServiceManager;

class NativeAppServiceProvider extends ServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        // Start SMTP when app opens
        Window::on('booted', function () {
            SmtpServiceManager::start();
        });

        // Stop SMTP when app closes
        Window::on('closing', function () {
            SmtpServiceManager::stop();
        });
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'display_errors' => '1',
            'error_reporting' => 'E_ALL',
            'max_execution_time' => '0',
            'max_input_time' => '0',
        ];
    }
}

<?php

namespace App\Providers;

use Native\Desktop\Facades\Window;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Illuminate\Support\Facades\Event;
use Native\Desktop\Events\ChildProcess\MessageReceived;
use Native\Desktop\Events\ChildProcess\ErrorReceived;
use Native\Desktop\Facades\ChildProcess;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {

        Window::open();
        ChildProcess::artisan('smtp:start', alias: 'smtp-catcher', persistent: true);
        Event::listen(MessageReceived::class, function (MessageReceived $event) {
            if ($event->alias === 'smtp-catcher') {
                \Illuminate\Support\Facades\Log::info('SMTP Catcher Output: ' . $event->data);
            }
        });

        Event::listen(ErrorReceived::class, function (ErrorReceived $event) {
            if ($event->alias === 'smtp-catcher') {
                \Illuminate\Support\Facades\Log::error('SMTP Catcher Error: ' . $event->data);
            }
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

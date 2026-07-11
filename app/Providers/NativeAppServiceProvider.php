<?php

namespace App\Providers;

use App\Filament\Resources\Emails\EmailResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Events\ChildProcess\ErrorReceived;
use Native\Desktop\Events\ChildProcess\MessageReceived;
use Native\Desktop\Events\Notifications\NotificationClicked;
use Native\Desktop\Facades\ChildProcess;
use Native\Desktop\Facades\Window;
use Throwable;

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

        // Fires only if the catcher writes to stdout (it shouldn't — kept for debugging)
        Event::listen(MessageReceived::class, function (MessageReceived $event) {
            if ($event->alias === 'smtp-catcher') {
                Log::info('SMTP Catcher Output: ' . $event->data);
            }
        });

        Event::listen(ErrorReceived::class, function (ErrorReceived $event) {
            if ($event->alias === 'smtp-catcher') {
                Log::error('SMTP Catcher Error: ' . $event->data);
            }
        });

        // Clicking the notification opens the email in the main window
        Event::listen(NotificationClicked::class, function (NotificationClicked $event) {
            if (!str_starts_with($event->reference, 'email:')) {
                return;
            }

            $id = substr($event->reference, strlen('email:'));

            try {
                Window::get('main')->url(EmailResource::getUrl('view', ['record' => $id]));
                Window::show('main');
            } catch (Throwable $e) {
                Log::warning('Could not open email from notification: ' . $e->getMessage());
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

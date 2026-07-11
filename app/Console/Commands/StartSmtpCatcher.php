<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\SmtpCatcher;

class StartSmtpCatcher extends Command
{
    protected $signature = 'smtp:start';
    protected $description = 'Start the local SMTP catcher';

    public function handle(SmtpCatcher $catcher)
    {
        // Avoid writing to stdout: when run as a NativePHP child process,
        // stdout is piped to Electron and can crash the app (EPIPE) if the
        // launching terminal has gone away. Log to file instead.
        try {
            Log::info('Starting SMTP Catcher...');
            $catcher->start();
        } catch (\Exception $e) {
            Log::error('Failed to start SMTP Catcher: ' . $e->getMessage() . ' in line ' . $e->getLine());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

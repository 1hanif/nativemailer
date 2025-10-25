<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SmtpCatcher;

class StartSmtpCatcher extends Command
{
    protected $signature = 'smtp:start';
    protected $description = 'Start the local SMTP catcher';

    public function handle(SmtpCatcher $catcher)
    {
        try {
            $this->info('Starting SMTP Catcher on 127.0.0.1:1025...');
            $catcher->start();
            $this->info('SMTP Catcher started successfully.');
        } catch (\Exception $e) {
            $this->error('Failed to start SMTP Catcher: ' . $e->getMessage() . " in line " . $e->getLine());

        }
    }
}

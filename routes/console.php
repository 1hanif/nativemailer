<?php

use App\Models\Email;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Apply the inbox retention settings (see Email::prunable). NativePHP
// runs the scheduler while the app is open.
Schedule::command('model:prune', ['--model' => [Email::class]])->hourly();

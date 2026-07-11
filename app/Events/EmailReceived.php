<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by the SMTP catcher (child process) when an email is captured.
 *
 * NativePHP's EventWatcher forwards it to the Electron runtime, which
 * broadcasts it to all windows — Livewire components receive it as
 * "native:\App\Events\EmailReceived".
 */
class EmailReceived
{
    use Dispatchable;

    public function __construct(
        public int $id,
        public ?string $from,
        public ?string $subject,
    ) {}

    /**
     * Must return the channel as a *string*: NativePHP's EventWatcher
     * checks in_array('nativephp', $channels) — a Channel object
     * would silently fail the check.
     */
    public function broadcastOn(): array
    {
        return ['nativephp'];
    }
}

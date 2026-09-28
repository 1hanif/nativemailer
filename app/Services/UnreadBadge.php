<?php

namespace App\Services;

use App\Models\Email;
use Native\Desktop\Facades\App;
use Throwable;

/**
 * Mirrors the unread count onto the app icon badge (macOS dock, and
 * Linux launchers that support it). A no-op outside the desktop app.
 */
class UnreadBadge
{
    public static function sync(): void
    {
        if (! config('nativephp-internal.running')) {
            return;
        }

        try {
            App::badgeCount(Email::query()->where('is_read', false)->count());
        } catch (Throwable) {
            // The badge is cosmetic; never let it break capture or the UI
        }
    }
}

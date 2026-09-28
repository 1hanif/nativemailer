<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Works out whether the SMTP catcher is up by connecting to its port
 * and reading the greeting: our catcher introduces itself as
 * "SMTP Catcher", so it can be told apart from another program that
 * happens to hold the port. Start-up failures are recorded by
 * `smtp:start` so the UI can say why it's down.
 */
class CatcherStatus
{
    public const RUNNING = 'running';

    public const PORT_TAKEN = 'port_taken';

    public const DOWN = 'down';

    public const GREETING_MARKER = 'SMTP Catcher';

    /** @return array{state: string, port: int, message: string} */
    public static function check(?int $port = null): array
    {
        $port ??= SmtpCatcher::port();
        $host = config('mail.catcher.host', '127.0.0.1');
        $greeting = self::greeting($host, $port);

        if ($greeting !== null && str_contains($greeting, self::GREETING_MARKER)) {
            return ['state' => self::RUNNING, 'port' => $port, 'message' => "Catching mail on {$host}:{$port}"];
        }

        if ($greeting !== null) {
            return [
                'state' => self::PORT_TAKEN,
                'port' => $port,
                'message' => "Another program is using port {$port}. Pick a different port in Settings.",
            ];
        }

        $error = Setting::get('catcher_error');

        return [
            'state' => self::DOWN,
            'port' => $port,
            'message' => $error
                ? "Not running: {$error}"
                : "Nothing is listening on {$host}:{$port}. The catcher may still be starting.",
        ];
    }

    /** True when anything at all accepts connections on the port */
    public static function portInUse(int $port): bool
    {
        $conn = @fsockopen(config('mail.catcher.host', '127.0.0.1'), $port, $errno, $errstr, 0.3);
        if ($conn === false) {
            return false;
        }
        fclose($conn);

        return true;
    }

    public static function recordStartFailure(string $message): void
    {
        Setting::set('catcher_error', $message);
    }

    public static function clearStartFailure(): void
    {
        Setting::set('catcher_error', null);
    }

    /** The server's first line, or null when nothing accepts the connection */
    private static function greeting(string $host, int $port): ?string
    {
        $conn = @fsockopen($host, $port, $errno, $errstr, 0.3);
        if ($conn === false) {
            return null;
        }

        stream_set_timeout($conn, 0, 500_000);
        $line = (string) fgets($conn, 512);
        @fwrite($conn, "QUIT\r\n");
        fclose($conn);

        return trim($line);
    }
}

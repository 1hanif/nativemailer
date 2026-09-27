<?php

namespace App\Services;

use App\Models\Email;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;
use Throwable;

/**
 * "Release" a captured email: forward the original message, byte for
 * byte, through a real SMTP relay to the recipients the user picks.
 * The headers are untouched, so it arrives looking exactly as the app
 * sent it; only the SMTP envelope decides where it goes.
 */
class ReleaseEmail
{
    public const ENCRYPTION_STARTTLS = 'starttls';

    public const ENCRYPTION_TLS = 'tls';

    public const ENCRYPTION_NONE = 'none';

    public static function isConfigured(): bool
    {
        return filled(Setting::get('relay_host'));
    }

    /** @return array{host: ?string, port: int, encryption: string, username: ?string, from: ?string} */
    public static function settings(): array
    {
        return [
            'host' => Setting::get('relay_host'),
            'port' => (int) Setting::get('relay_port', 587),
            'encryption' => Setting::get('relay_encryption', self::ENCRYPTION_STARTTLS),
            'username' => Setting::get('relay_username'),
            'from' => Setting::get('relay_from'),
        ];
    }

    /**
     * Save relay settings. A blank password keeps the stored one, so the
     * form never has to echo the secret back.
     */
    public static function saveSettings(array $data): void
    {
        Setting::set('relay_host', filled($data['relay_host'] ?? null) ? trim($data['relay_host']) : null);
        Setting::set('relay_port', (int) ($data['relay_port'] ?? 587));
        Setting::set('relay_encryption', $data['relay_encryption'] ?? self::ENCRYPTION_STARTTLS);
        Setting::set('relay_username', filled($data['relay_username'] ?? null) ? $data['relay_username'] : null);
        Setting::set('relay_from', filled($data['relay_from'] ?? null) ? trim($data['relay_from']) : null);

        if (filled($data['relay_password'] ?? null)) {
            Setting::set('relay_password', Crypt::encryptString($data['relay_password']));
        }
        if (blank($data['relay_host'] ?? null)) {
            Setting::set('relay_password', null); // relay removed: forget the secret too
        }
    }

    /**
     * @param  list<string>  $recipients
     *
     * @throws TransportExceptionInterface
     */
    public static function send(Email $email, array $recipients, ?TransportInterface $transport = null): void
    {
        $settings = self::settings();
        $sender = $settings['from'] ?: $email->from;

        ($transport ?? self::transport())->send(
            new RawMessage($email->raw),
            new Envelope(new Address($sender), array_map(fn (string $to) => new Address($to), $recipients))
        );
    }

    public static function transport(): TransportInterface
    {
        $s = self::settings();

        $dsn = new Dsn(
            $s['encryption'] === self::ENCRYPTION_TLS ? 'smtps' : 'smtp',
            $s['host'],
            $s['username'],
            self::password(),
            $s['port'],
            match ($s['encryption']) {
                self::ENCRYPTION_NONE => ['auto_tls' => 'false'],
                self::ENCRYPTION_STARTTLS => ['require_tls' => 'true'],
                default => [],
            },
        );

        return (new EsmtpTransportFactory)->create($dsn);
    }

    /** Split "a@x.test, b@x.test; c@x.test" into validated addresses */
    public static function parseRecipients(string $input): array
    {
        $addresses = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\s]+/', $input)))));

        return array_values(array_filter($addresses, fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
    }

    private static function password(): ?string
    {
        $stored = Setting::get('relay_password');

        try {
            return $stored ? Crypt::decryptString($stored) : null;
        } catch (Throwable) {
            return null; // app key changed: the user has to re-enter it
        }
    }
}

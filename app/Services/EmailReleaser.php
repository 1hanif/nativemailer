<?php

namespace App\Services;

use App\Models\Email;
use InvalidArgumentException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * "Release" a captured email: forward the original raw message to a
 * real SMTP server, unchanged. The envelope recipients can differ from
 * the message's To: header (like Mailpit's release feature).
 */
class EmailReleaser
{
    /**
     * @param string[] $recipients
     *
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function release(
        Email $email,
        string $host,
        int $port,
        string $encryption, // 'auto' (plain / STARTTLS if offered) or 'ssl' (implicit TLS)
        ?string $username,
        ?string $password,
        array $recipients,
    ): void {
        if (blank($email->raw)) {
            throw new InvalidArgumentException('This email has no stored raw message to release.');
        }

        $recipients = array_values(array_filter(
            array_map('trim', $recipients),
            fn (string $r) => filter_var($r, FILTER_VALIDATE_EMAIL) !== false,
        ));

        if (empty($recipients)) {
            throw new InvalidArgumentException('No valid recipient addresses given.');
        }

        $from = $email->from ?: $username;
        if (blank($from) || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('No valid sender address available for the envelope.');
        }

        // tls=true forces implicit TLS; tls=null lets the transport use
        // STARTTLS automatically when the server offers it
        $transport = new EsmtpTransport($host, $port, $encryption === 'ssl' ? true : null);

        if (filled($username)) {
            $transport->setUsername($username);
        }
        if (filled($password)) {
            $transport->setPassword($password);
        }

        $transport->send(
            new RawMessage($email->raw),
            new Envelope(
                new Address($from),
                array_map(fn (string $r) => new Address($r), $recipients),
            ),
        );
    }
}

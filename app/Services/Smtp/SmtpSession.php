<?php

namespace App\Services\Smtp;

/**
 * The SMTP protocol state machine for one client connection.
 * Knows nothing about sockets: bytes come in via feed(), replies go
 * out through the injected $send callable, and completed messages are
 * handed to $onMessage.
 */
class SmtpSession
{
    private const STATE_HELO = 'HELO';

    private const STATE_MAIL = 'MAIL';

    private const STATE_RCPT = 'RCPT';

    private const STATE_DATA_BODY = 'DATA_BODY';

    /**
     * Extensions advertised in the EHLO reply. AUTH is accepted with any
     * credentials so apps configured with MAIL_USERNAME/MAIL_PASSWORD
     * (e.g. a copied production .env) can still deliver here.
     */
    private const EXTENSIONS = ['AUTH PLAIN LOGIN', '8BITMIME', 'SMTPUTF8'];

    /** Longest command line accepted outside DATA (RFC 5321 §4.5.3.1.4 is 512) */
    private const MAX_COMMAND_LINE = 4096;

    private string $state = self::STATE_HELO;

    private string $buffer = '';

    private string $raw = '';

    private ?string $mailFrom = null;

    private array $recipients = [];

    /** Set once the current message passes maxMessageSize; the rest is discarded */
    private bool $tooLarge = false;

    /**
     * Pending AUTH exchange: null when idle, otherwise the next thing we
     * expect from the client ('plain', 'login_user' or 'login_pass').
     */
    private ?string $authStep = null;

    public function __construct(
        /** @var callable(string): void */
        private $send,
        /** @var callable(string $raw, ?string $from, array $recipients): void */
        private $onMessage,
        /** @var callable(): void */
        private $close,
        private int $maxMessageSize = 50 * 1024 * 1024,
    ) {}

    public function greet(): void
    {
        $this->reply("220 localhost SMTP Catcher Ready\r\n");
    }

    public function feed(string $data): void
    {
        $this->buffer .= $data;

        while (($pos = strpos($this->buffer, "\r\n")) !== false) {
            $line = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + 2);

            if ($this->state === self::STATE_DATA_BODY) {
                $this->handleBodyLine($line);

                continue;
            }

            if ($this->authStep !== null) {
                $this->handleAuthLine(trim($line));

                continue;
            }

            if ($this->handleCommand(trim($line)) === false) {
                return; // connection closed (QUIT)
            }
        }

        $this->guardUnterminatedLine();
    }

    /**
     * Stop a client from growing the buffer without ever sending CRLF.
     * In DATA the partial line is dropped (the message is already too
     * big); keep the last byte in case it is the "\r" of the next CRLF.
     */
    private function guardUnterminatedLine(): void
    {
        if ($this->state === self::STATE_DATA_BODY && strlen($this->buffer) > $this->maxMessageSize) {
            $this->tooLarge = true;
            $this->raw = '';
            $this->buffer = substr($this->buffer, -1);
        } elseif ($this->state !== self::STATE_DATA_BODY && strlen($this->buffer) > self::MAX_COMMAND_LINE) {
            $this->buffer = '';
            $this->reply("500 5.5.6 Line too long\r\n");
        }
    }

    /** In DATA, everything is message content until the terminating "." */
    private function handleBodyLine(string $line): void
    {
        if ($line === '.') {
            if ($this->tooLarge) {
                $this->reply("552 5.3.4 Message exceeds fixed maximum message size of {$this->maxMessageSize} bytes\r\n");
            } else {
                ($this->onMessage)($this->raw, $this->mailFrom, $this->recipients);
                $this->reply("250 OK: Message accepted\r\n");
            }
            $this->resetTransaction(self::STATE_MAIL);

            return;
        }

        if ($this->tooLarge) {
            return; // keep reading until "." but don't store anything
        }

        // Reverse SMTP dot-stuffing (RFC 5321 §4.5.2)
        if (isset($line[0]) && $line[0] === '.') {
            $line = substr($line, 1);
        }
        $this->raw .= $line."\r\n";

        if (strlen($this->raw) > $this->maxMessageSize) {
            $this->tooLarge = true;
            $this->raw = ''; // free the memory now
        }
    }

    /** @return bool false when the connection was closed */
    private function handleCommand(string $line): bool
    {
        $upper = strtoupper($line);

        if (preg_match('/^EHLO\b/i', $line)) {
            $lines = ['localhost', "SIZE {$this->maxMessageSize}", ...self::EXTENSIONS];
            $last = array_pop($lines);
            $this->reply(implode('', array_map(fn ($l) => "250-{$l}\r\n", $lines))."250 {$last}\r\n");
            $this->resetTransaction(self::STATE_MAIL);
        } elseif (preg_match('/^HELO\b/i', $line)) {
            $this->reply("250 localhost\r\n");
            $this->resetTransaction(self::STATE_MAIL);
        } elseif (preg_match('/^AUTH\s+(\S+)(?:\s+(\S+))?/i', $line, $m)) {
            $this->startAuth(strtoupper($m[1]), $m[2] ?? null);
        } elseif (preg_match('/^MAIL FROM:\s*(.*)/i', $line, $m)) {
            if ($this->state === self::STATE_HELO) {
                $this->reply("503 5.5.1 Send HELO/EHLO first\r\n");
            } elseif (preg_match('/\bSIZE=(\d+)/i', $m[1], $size) && (int) $size[1] > $this->maxMessageSize) {
                // Client declared the size up front (RFC 1870): refuse early
                $this->reply("552 5.3.4 Message size exceeds fixed maximum message size\r\n");
            } else {
                $this->mailFrom = MimeMessageParser::extractAddress($m[1]);
                $this->recipients = [];
                $this->state = self::STATE_RCPT;
                $this->reply("250 OK\r\n");
            }
        } elseif (preg_match('/^RCPT TO:\s*(.*)/i', $line, $m)) {
            if ($this->state !== self::STATE_RCPT) {
                $this->reply("503 5.5.1 Need MAIL FROM first\r\n");
            } else {
                $recipient = MimeMessageParser::extractAddress($m[1]);
                if ($recipient !== null) {
                    $this->recipients[] = $recipient;
                }
                $this->reply("250 OK\r\n");
            }
        } elseif ($upper === 'DATA') {
            if ($this->state !== self::STATE_RCPT || empty($this->recipients)) {
                $this->reply("503 5.5.1 Need MAIL FROM and RCPT TO first\r\n");
            } else {
                $this->reply("354 Start mail input; end with <CRLF>.<CRLF>\r\n");
                $this->state = self::STATE_DATA_BODY;
            }
        } elseif ($upper === 'NOOP') {
            $this->reply("250 OK\r\n");
        } elseif ($upper === 'RSET') {
            $this->resetTransaction($this->state === self::STATE_HELO ? self::STATE_HELO : self::STATE_MAIL);
            $this->reply("250 OK\r\n");
        } elseif ($upper === 'QUIT') {
            $this->reply("221 Bye\r\n");
            ($this->close)();

            return false;
        } else {
            $this->reply("500 5.5.2 Unknown command\r\n");
        }

        return true;
    }

    private function startAuth(string $mechanism, ?string $initialResponse): void
    {
        if ($this->state === self::STATE_HELO) {
            $this->reply("503 5.5.1 Send EHLO first\r\n");
        } elseif ($mechanism === 'PLAIN') {
            if ($initialResponse !== null) {
                $this->acceptAuth();
            } else {
                $this->authStep = 'plain';
                $this->reply("334 \r\n");
            }
        } elseif ($mechanism === 'LOGIN') {
            // An initial response, if sent, is the username
            $this->authStep = $initialResponse !== null ? 'login_pass' : 'login_user';
            $this->reply($initialResponse !== null ? "334 UGFzc3dvcmQ6\r\n" : "334 VXNlcm5hbWU6\r\n");
        } else {
            $this->reply("504 5.5.4 Unrecognized authentication type\r\n");
        }
    }

    /** Continuation lines of an AUTH exchange; credentials are never checked. */
    private function handleAuthLine(string $line): void
    {
        if ($line === '*') {
            $this->authStep = null;
            $this->reply("501 5.7.0 Authentication cancelled\r\n");
        } elseif ($this->authStep === 'login_user') {
            $this->authStep = 'login_pass';
            $this->reply("334 UGFzc3dvcmQ6\r\n");
        } else {
            $this->acceptAuth();
        }
    }

    private function acceptAuth(): void
    {
        $this->authStep = null;
        $this->reply("235 2.7.0 Authentication successful\r\n");
    }

    private function resetTransaction(string $state): void
    {
        $this->state = $state;
        $this->raw = '';
        $this->tooLarge = false;
        $this->mailFrom = null;
        $this->recipients = [];
    }

    private function reply(string $message): void
    {
        ($this->send)($message);
    }
}

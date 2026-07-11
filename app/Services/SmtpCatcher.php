<?php

namespace App\Services;

use App\Services\Smtp\PersistCapturedEmail;
use App\Services\Smtp\SmtpServer;

/**
 * Entry point for the local SMTP catcher. Wires the socket server to
 * the message handler; the actual work lives in App\Services\Smtp:
 *
 *  - SmtpServer           sockets, select loop, client lifecycle
 *  - SmtpSession          per-client SMTP protocol state machine
 *  - MimeMessageParser    raw message -> headers/bodies/attachments
 *  - PersistCapturedEmail store + notify, swallow bad-email errors
 */
class SmtpCatcher
{
    private SmtpServer $server;

    public function __construct()
    {
        $this->server = new SmtpServer(
            host: config('mail.catcher.host', '127.0.0.1'),
            port: (int) config('mail.catcher.port', 1025),
            timeout: (int) config('mail.catcher.timeout', 30),
            onMessage: new PersistCapturedEmail(),
        );
    }

    public function start(): void
    {
        $this->server->start();
    }
}

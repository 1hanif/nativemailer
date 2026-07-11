<?php

namespace App\Services\Smtp;

use App\Models\Email;
use Exception;
use Native\Desktop\Facades\ChildProcess;

/**
 * Handles a completed SMTP message: parse, store, notify the app.
 * Never throws — a malformed email must not bring down the server loop.
 */
class PersistCapturedEmail
{
    public function __construct(
        private MimeMessageParser $parser = new MimeMessageParser(),
    ) {}

    public function __invoke(string $raw, ?string $envelopeFrom, array $envelopeRecipients): void
    {
        try {
            $data = $this->parser->parse($raw, $envelopeFrom, $envelopeRecipients);

            Email::create($data);
            ChildProcess::message('Email created', 'smtp-catcher');
        } catch (Exception $e) {
            error_log(
                '[' . date('Y-m-d H:i:s') . "] Failed to handle email: " . $e->getMessage()
                    . "\nTrace: " . $e->getTraceAsString()
                    . "\nRaw message: " . substr($raw, 0, 1000) . "\n",
                3,
                storage_path('logs/smtp.log')
            );
        }
    }
}

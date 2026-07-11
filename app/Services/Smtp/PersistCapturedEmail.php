<?php

namespace App\Services\Smtp;

use App\Events\EmailReceived;
use App\Models\Email;
use Exception;
use Native\Desktop\Facades\Notification;
use Throwable;

/**
 * Handles a completed SMTP message: parse, store, notify.
 * Never throws — a malformed email must not bring down the server loop.
 *
 * NOTE: this runs inside the catcher child process. Do NOT use
 * ChildProcess::message() to reach the app — in the Electron plugin that
 * writes into our own stdin. And do NOT write to stdout — that crashes
 * Electron (EPIPE) when the launching terminal is gone. Both the event
 * broadcast and the notification go over NativePHP's HTTP API instead.
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

            $email = Email::create($data);

            // Broadcast to all windows (live inbox refresh) via EventWatcher
            event(new EmailReceived($email->id, $email->from, $email->subject));

            $this->notify($email);
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

    private function notify(Email $email): void
    {
        try {
            Notification::new()
                ->reference('email:' . $email->id)
                ->title($email->subject ?: 'New email')
                ->message('From: ' . ($email->from ?? 'unknown'))
                ->show();
        } catch (Throwable $e) {
            // A failed notification must never break email capture
            error_log(
                '[' . date('Y-m-d H:i:s') . '] Notification failed: ' . $e->getMessage() . "\n",
                3,
                storage_path('logs/smtp.log')
            );
        }
    }
}

<?php

namespace App\Services;

use App\Models\Email;
use App\Support\MimeHeader;
use Exception;
use Native\Desktop\Facades\ChildProcess;

class SmtpCatcher
{
    private $socket;
    private $clients = [];
    private $clientIdCounter = 0;
    private $timeout;
    private $host;
    private $port;

    public function __construct()
    {
        $this->host = config('mail.catcher.host', '127.0.0.1');
        $this->port = (int) config('mail.catcher.port', 1025);
        $this->timeout = (int) config('mail.catcher.timeout', 30);

        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            throw new Exception('Socket creation failed: ' . socket_strerror(socket_last_error()));
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_nonblock($this->socket);

        if (!socket_bind($this->socket, $this->host, $this->port)) {
            throw new Exception('Socket bind failed: ' . socket_strerror(socket_last_error()));
        }

        if (!socket_listen($this->socket, 5)) {
            throw new Exception('Socket listen failed: ' . socket_strerror(socket_last_error()));
        }
    }

    public function start()
    {
        // NOTE: never write to stdout here. Child-process stdout is piped to
        // Electron's console.log; if the launching terminal is gone, that
        // write throws EPIPE and crashes the whole app.

        while (true) {
            $read = [$this->socket, ...array_column($this->clients, 'socket')];
            $write = $except = [];
            if (socket_select($read, $write, $except, 0, 100000) > 0) {
                if (in_array($this->socket, $read, true)) {
                    $client = socket_accept($this->socket);
                    if ($client !== false) {
                        socket_set_nonblock($client);
                        $clientId = ++$this->clientIdCounter;
                        $this->clients[$clientId] = $this->newClientState($client);
                        $this->send($client, "220 localhost SMTP Catcher Ready\r\n");
                        $key = array_search($this->socket, $read, true);
                        if ($key !== false) {
                            unset($read[$key]);
                        }
                    }
                }

                foreach ($read as $sock) {
                    if ($sock !== $this->socket) {
                        $clientId = $this->findClientIdBySocket($sock);
                        if ($clientId === null) {
                            continue;
                        }

                        $data = @socket_read($sock, 8192);
                        if ($data === false || $data === '') {
                            $this->closeClient($clientId);
                            continue;
                        }

                        $this->clients[$clientId]['last_activity'] = time();
                        $this->handleClient($clientId, $data);
                    }
                }
            }

            $this->reapIdleClients();

            usleep(10000);
        }
    }

    private function newClientState($socket): array
    {
        return [
            'socket' => $socket,
            'state' => 'HELO',
            'buffer' => '',
            'raw' => '',
            'mail_from' => null,
            'recipients' => [],
            'last_activity' => time(),
        ];
    }

    private function reapIdleClients(): void
    {
        $now = time();
        foreach ($this->clients as $clientId => $client) {
            if ($now - $client['last_activity'] > $this->timeout) {
                $this->send($client['socket'], "421 4.4.2 Idle timeout, closing connection\r\n");
                $this->closeClient($clientId);
            }
        }
    }

    private function handleClient($clientId, $data)
    {
        $client = $this->clients[$clientId]['socket'];
        $this->clients[$clientId]['buffer'] .= $data;

        while (isset($this->clients[$clientId]) && ($pos = strpos($this->clients[$clientId]['buffer'], "\r\n")) !== false) {
            $line = substr($this->clients[$clientId]['buffer'], 0, $pos);
            $this->clients[$clientId]['buffer'] = substr($this->clients[$clientId]['buffer'], $pos + 2);

            $state = $this->clients[$clientId]['state'];

            // In DATA_BODY, everything is message content until the terminating "."
            if ($state === 'DATA_BODY') {
                if ($line === '.') {
                    $this->handleMessage(
                        $this->clients[$clientId]['raw'],
                        $this->clients[$clientId]['mail_from'],
                        $this->clients[$clientId]['recipients']
                    );
                    $this->send($client, "250 OK: Message accepted\r\n");
                    $this->clients[$clientId]['state'] = 'MAIL';
                    $this->clients[$clientId]['raw'] = '';
                    $this->clients[$clientId]['mail_from'] = null;
                    $this->clients[$clientId]['recipients'] = [];
                } else {
                    // Reverse SMTP dot-stuffing (RFC 5321 §4.5.2)
                    if (isset($line[0]) && $line[0] === '.') {
                        $line = substr($line, 1);
                    }
                    $this->clients[$clientId]['raw'] .= $line . "\r\n";
                }
                continue;
            }

            $line = trim($line);
            $upper = strtoupper($line);

            if (preg_match('/^(HELO|EHLO)\b/i', $line)) {
                $this->send($client, "250 localhost\r\n");
                $this->clients[$clientId]['state'] = 'MAIL';
                $this->clients[$clientId]['raw'] = '';
                $this->clients[$clientId]['mail_from'] = null;
                $this->clients[$clientId]['recipients'] = [];
            } elseif (preg_match('/^MAIL FROM:\s*(.*)/i', $line, $m)) {
                if ($state === 'HELO') {
                    $this->send($client, "503 5.5.1 Send HELO/EHLO first\r\n");
                } else {
                    $this->clients[$clientId]['mail_from'] = $this->extractEmail($m[1]);
                    $this->clients[$clientId]['recipients'] = [];
                    $this->clients[$clientId]['state'] = 'RCPT';
                    $this->send($client, "250 OK\r\n");
                }
            } elseif (preg_match('/^RCPT TO:\s*(.*)/i', $line, $m)) {
                if ($state !== 'RCPT') {
                    $this->send($client, "503 5.5.1 Need MAIL FROM first\r\n");
                } else {
                    $recipient = $this->extractEmail($m[1]);
                    if ($recipient !== null) {
                        $this->clients[$clientId]['recipients'][] = $recipient;
                    }
                    $this->send($client, "250 OK\r\n");
                }
            } elseif ($upper === 'DATA') {
                if ($state !== 'RCPT' || empty($this->clients[$clientId]['recipients'])) {
                    $this->send($client, "503 5.5.1 Need MAIL FROM and RCPT TO first\r\n");
                } else {
                    $this->send($client, "354 Start mail input; end with <CRLF>.<CRLF>\r\n");
                    $this->clients[$clientId]['state'] = 'DATA_BODY';
                }
            } elseif ($upper === 'NOOP') {
                $this->send($client, "250 OK\r\n");
            } elseif ($upper === 'RSET') {
                $this->clients[$clientId]['state'] = $state === 'HELO' ? 'HELO' : 'MAIL';
                $this->clients[$clientId]['raw'] = '';
                $this->clients[$clientId]['mail_from'] = null;
                $this->clients[$clientId]['recipients'] = [];
                $this->send($client, "250 OK\r\n");
            } elseif ($upper === 'QUIT') {
                $this->send($client, "221 Bye\r\n");
                $this->closeClient($clientId);
                return;
            } else {
                $this->send($client, "500 5.5.2 Unknown command\r\n");
            }
        }
    }

    /**
     * Parse and persist a captured message. Never throws: a malformed
     * email must not bring down the whole catcher loop.
     */
    private function handleMessage($rawMessage, $envelopeFrom = null, array $envelopeRecipients = [])
    {
        try {
            $rawMessage = rtrim($rawMessage, "\r\n");

            // Normalize line endings to \r\n
            $rawMessage = str_replace(["\r\n", "\r"], "\n", $rawMessage);
            $rawMessage = str_replace("\n", "\r\n", $rawMessage);

            // Split raw message into headers and body
            if (strpos($rawMessage, "\r\n\r\n") !== false) {
                list($headerString, $bodyString) = explode("\r\n\r\n", $rawMessage, 2);
            } else {
                $headerString = $rawMessage;
                $bodyString = '';
            }

            if (trim($headerString) === '') {
                throw new Exception('Invalid email format: Missing headers');
            }

            $headers = $this->parseHeaders($headerString);

            $fromEmail = $this->extractEmail($headers['from'] ?? null) ?? $envelopeFrom;
            $toEmails = array_filter(array_map(
                [$this, 'extractEmail'],
                preg_split('/\s*,\s*/', $headers['to'] ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: []
            ));
            if (empty($toEmails)) {
                $toEmails = $envelopeRecipients;
            }

            // Parse body: handles single-part and nested multipart (alternative/mixed/related)
            $result = ['text' => null, 'html' => null, 'attachments' => []];
            $this->parseMimePart($headers, $headerString, $bodyString, $result);

            $data = [
                'from' => $fromEmail,
                'to' => implode(', ', $toEmails),
                'subject' => $this->decodeMimeHeader($headers['subject'] ?? null),
                'raw' => $rawMessage,
                'received_at' => now(),
                'body_text' => $result['text'],
                'body_html' => $result['html'],
                'attachments' => $result['attachments'],
            ];

            Email::create($data);
            ChildProcess::message('Email created', 'smtp-catcher');
        } catch (Exception $e) {
            // Log and continue — do NOT rethrow, or one bad email kills the server.
            error_log(
                '[' . date('Y-m-d H:i:s') . "] Failed to handle email: " . $e->getMessage()
                    . "\nTrace: " . $e->getTraceAsString()
                    . "\nRaw message: " . substr($rawMessage ?? '', 0, 1000) . "\n",
                3,
                storage_path('logs/smtp.log')
            );
        }
    }

    /**
     * Parse a header block into a lowercase-keyed array, unfolding
     * continuation lines (RFC 5322 §2.2.3).
     */
    private function parseHeaders($headerString): array
    {
        // Unfold: a CRLF followed by whitespace is a continuation
        $headerString = preg_replace('/\r\n[ \t]+/', ' ', $headerString);

        $headers = [];
        foreach (explode("\r\n", $headerString) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $colonPos = strpos($line, ':');
            if ($colonPos !== false && $colonPos > 0) {
                $name = strtolower(trim(substr($line, 0, $colonPos)));
                $value = trim(substr($line, $colonPos + 1));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * Recursively walk a MIME part, filling $result with text/html
     * bodies and attachments.
     */
    private function parseMimePart(array $headers, $rawHeaders, $body, array &$result): void
    {
        $contentType = $headers['content-type'] ?? 'text/plain';

        if (preg_match('/multipart\/[a-z-]+.*boundary=(?:"([^"]+)"|([^;\s]+))/is', $contentType, $m)) {
            $boundary = $m[1] !== '' ? $m[1] : $m[2];
            $parts = preg_split('/\r\n--' . preg_quote($boundary, '/') . '/', "\r\n" . $body);

            foreach ($parts as $i => $part) {
                if ($i === 0) {
                    continue; // preamble
                }
                $part = ltrim($part, "\r\n");
                if ($part === '' || strpos($part, '--') === 0) {
                    continue; // closing marker / epilogue
                }

                if (strpos($part, "\r\n\r\n") !== false) {
                    list($partHeaderString, $partBody) = explode("\r\n\r\n", $part, 2);
                } else {
                    $partHeaderString = $part;
                    $partBody = '';
                }

                $partHeaders = $this->parseHeaders($partHeaderString);
                $this->parseMimePart($partHeaders, $partHeaderString, $partBody, $result);
            }

            return;
        }

        // Leaf part
        $disposition = $headers['content-disposition'] ?? '';
        $filename = null;
        if (preg_match('/filename=(?:"([^"]+)"|([^;\s]+))/i', $disposition . ' ' . $contentType, $m)) {
            $filename = $m[1] !== '' ? $m[1] : $m[2];
        }

        $decoded = $this->decodeContent($body, $headers['content-transfer-encoding'] ?? '');

        if ($filename !== null || stripos($disposition, 'attachment') !== false) {
            $result['attachments'][] = [
                'name' => $this->decodeMimeHeader($filename) ?? 'unnamed',
                'content_type' => trim(explode(';', $contentType)[0]),
                'size' => strlen($decoded),
                'content' => base64_encode($decoded),
            ];
        } elseif (stripos($contentType, 'text/html') !== false) {
            $result['html'] = $result['html'] ?? $decoded;
        } else {
            $result['text'] = $result['text'] ?? $decoded;
        }
    }

    /**
     * Decode RFC 2047 encoded-words in header values,
     * e.g. "=?utf-8?Q?=F0=9F=9A=80?= Hello" => "🚀 Hello".
     */
    private function decodeMimeHeader($value)
    {
        return MimeHeader::decode($value);
    }

    private function extractEmail($string)
    {
        if (empty($string)) {
            return null;
        }
        if (preg_match('/<([^>]+)>/', $string, $matches)) {
            return trim($matches[1]);
        }
        $string = trim($string);

        return $string === '' ? null : $string;
    }

    private function decodeContent($content, $encoding)
    {
        $encoding = strtolower(trim($encoding));
        if ($encoding === 'quoted-printable') {
            return quoted_printable_decode($content);
        }
        if ($encoding === 'base64') {
            return base64_decode($content) ?: $content;
        }

        return $content;
    }

    private function send($client, $message)
    {
        @socket_write($client, $message);
    }

    private function closeClient($clientId)
    {
        if (isset($this->clients[$clientId])) {
            @socket_close($this->clients[$clientId]['socket']);
            unset($this->clients[$clientId]);
        }
    }

    private function findClientIdBySocket($socket)
    {
        foreach ($this->clients as $id => $client) {
            if ($client['socket'] === $socket) {
                return $id;
            }
        }

        return null;
    }

    public function __destruct()
    {
        foreach ($this->clients as $id => $client) {
            if (is_array($client) && isset($client['socket'])) {
                @socket_close($client['socket']);
            }
        }
        if ($this->socket !== false && $this->socket !== null) {
            @socket_close($this->socket);
        }
    }
}

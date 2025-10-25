<?php

namespace App\Services;

use App\Models\Email;
use Exception;

class SmtpCatcher
{
    private $socket;
    private $clients = [];
    private $clientIdCounter = 0;
    private $timeout = 30;

    public function __construct()
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            throw new Exception('Socket creation failed: ' . socket_strerror(socket_last_error()));
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_nonblock($this->socket);

        if (!socket_bind($this->socket, '127.0.0.1', 1025)) {
            throw new Exception('Socket bind failed: ' . socket_strerror(socket_last_error()));
        }

        if (!socket_listen($this->socket, 5)) {
            throw new Exception('Socket listen failed: ' . socket_strerror(socket_last_error()));
        }
    }

    public function start()
    {
        echo "SMTP Catcher started on 127.0.0.1:1025\n";

        while (true) {
            $read = [$this->socket, ...array_column($this->clients, 'socket')];
            $write = $except = [];
            if (socket_select($read, $write, $except, 0, 100000) > 0) {
                if (in_array($this->socket, $read, true)) {
                    $client = socket_accept($this->socket);
                    if ($client !== false) {
                        socket_set_nonblock($client);
                        $clientId = ++$this->clientIdCounter;
                        $this->clients[$clientId] = [
                            'socket' => $client,
                            'state' => 'HELO',
                            'buffer' => '',
                            'raw' => ''
                        ];
                        $this->send($client, "220 localhost SMTP Catcher Ready\r\n");
                        $key = array_search($this->socket, $read, true);
                        if ($key !== false)
                            unset($read[$key]);
                    }
                }

                foreach ($read as $sock) {
                    if ($sock !== $this->socket) {
                        $clientId = $this->findClientIdBySocket($sock);
                        if ($clientId === null) {
                            continue;
                        }

                        $data = @socket_read($sock, 1024);
                        if ($data === false || $data === '') {
                            $this->closeClient($clientId);
                            continue;
                        }

                        $this->handleClient($clientId, $data);
                    }
                }
            }

            usleep(10000);
        }
    }

    private function handleClient($clientId, $data)
    {
        $client = $this->clients[$clientId]['socket'];
        $this->clients[$clientId]['buffer'] .= $data;

        while (($pos = strpos($this->clients[$clientId]['buffer'], "\r\n")) !== false) {
            $line = substr($this->clients[$clientId]['buffer'], 0, $pos);
            $this->clients[$clientId]['buffer'] = substr($this->clients[$clientId]['buffer'], $pos + 2);

            $line = trim($line);
            $state = $this->clients[$clientId]['state'];

            if ($state === 'HELO' && preg_match('/^(HELO|EHLO)/i', $line)) {
                $this->send($client, "250 localhost\r\n");
                $this->clients[$clientId]['state'] = 'MAIL';
            } elseif ($state === 'MAIL' && preg_match('/^MAIL FROM:/i', $line)) {
                $this->send($client, "250 OK\r\n");
                $this->clients[$clientId]['state'] = 'RCPT';
            } elseif ($state === 'RCPT' && preg_match('/^RCPT TO:/i', $line)) {
                $this->send($client, "250 OK\r\n");
                $this->clients[$clientId]['state'] = 'DATA';
            } elseif ($state === 'DATA' && strtoupper($line) === 'DATA') {
                $this->send($client, "354 Start mail input; end with <CRLF>.<CRLF>\r\n");
                $this->clients[$clientId]['state'] = 'DATA_BODY';
            } elseif ($state === 'DATA_BODY') {
                $this->clients[$clientId]['raw'] .= $line . "\r\n";
                if ($line === '.') {
                    $this->handleMessage($this->clients[$clientId]['raw']);
                    $this->send($client, "250 OK: Message accepted\r\n");
                    $this->clients[$clientId]['state'] = 'HELO';
                    $this->clients[$clientId]['raw'] = '';
                }
            } elseif (strtoupper($line) === 'QUIT') {
                $this->send($client, "221 Bye\r\n");
                // $this->closeClient($clientId);
            } else {
                $this->send($client, "500 Unknown command\r\n");
            }
        }
    }

    private function handleMessage($rawMessage)
    {
        try {
            // Remove the terminating dot if present
            $rawMessage = trim($rawMessage);
            if (substr($rawMessage, -3) === "\r\n.") {
                $rawMessage = substr($rawMessage, 0, -3);
            } elseif (substr($rawMessage, -1) === ".") {
                $rawMessage = substr($rawMessage, 0, -1);
            }

            // Normalize line endings properly - replace all variations with \r\n
            // First normalize everything to \n, then convert to \r\n
            $rawMessage = str_replace(["\r\n", "\r"], "\n", $rawMessage);
            $rawMessage = str_replace("\n", "\r\n", $rawMessage);

            // Split raw message into headers and body
            $headerString = '';
            $bodyString = '';

            // Try different separators
            if (strpos($rawMessage, "\r\n\r\n") !== false) {
                list($headerString, $bodyString) = explode("\r\n\r\n", $rawMessage, 2);
            } elseif (strpos($rawMessage, "\n\n") !== false) {
                list($headerString, $bodyString) = explode("\n\n", $rawMessage, 2);
            } else {
                // No separator found, treat entire message as headers
                $headerString = $rawMessage;
                $bodyString = '';
            }

            if (empty($headerString)) {
                throw new Exception('Invalid email format: Missing headers');
            }

            // Parse headers manually
            $headers = [];
            $headerLines = explode("\r\n", $headerString);
            foreach ($headerLines as $line) {
                // Skip empty lines
                if (empty(trim($line))) {
                    continue;
                }

                // Check if line contains a colon
                $colonPos = strpos($line, ':');
                if ($colonPos !== false && $colonPos > 0) {
                    $name = trim(substr($line, 0, $colonPos));
                    $value = trim(substr($line, $colonPos + 1));
                    $headers[strtolower($name)] = $value;
                }
            }

            // Extract from, to, subject manually
            $from = $headers['from'] ?? null;
            $to = $headers['to'] ?? null;
            $subject = $headers['subject'] ?? null;

            // Parse from and to to extract email addresses
            $fromEmail = $this->extractEmail($from);
            $toEmails = array_map([$this, 'extractEmail'], explode(', ', $to ?? ''));

            // Parse body (multipart/alternative handling)
            $bodyText = null;
            $bodyHtml = null;
            $attachments = [];

            $contentType = $headers['content-type'] ?? '';
            if (preg_match('/multipart\/alternative;\s*boundary=(["]?)([^ ;]+)\1/i', $contentType, $matches)) {
                $boundary = $matches[2];
                $bodyParts = explode("--" . $boundary, $bodyString);
                foreach ($bodyParts as $part) {
                    $part = trim($part);
                    if (empty($part) || $part === '--') {
                        continue;
                    }

                    // Split part into headers and content with safer array handling
                    $partLines = explode("\r\n\r\n", $part, 2);
                    if (count($partLines) !== 2) {
                        continue;
                    }

                    $partHeaders = $partLines[0];
                    $partContent = $partLines[1];

                    // Check part content type
                    if (preg_match('/Content-Type: text\/plain/i', $partHeaders)) {
                        $bodyText = $this->decodeContent($partContent, $partHeaders);
                    } elseif (preg_match('/Content-Type: text\/html/i', $partHeaders)) {
                        $bodyHtml = $this->decodeContent($partContent, $partHeaders);
                    }
                }
            } else {
                // Single-part email
                $bodyText = $this->decodeContent($bodyString, $headerString);
            }

            // Prepare data for storage
            $data = [
                'from' => $fromEmail ?? null,
                'to' => implode(', ', array_filter($toEmails)),
                'subject' => $subject,
                'raw' => $rawMessage,
                'received_at' => now(),
                'body_text' => $bodyText,
                'body_html' => $bodyHtml,
                'attachments' => $attachments,
            ];

            // Save
            Email::create($data);

            echo "Captured email from {$data['from']} to {$data['to']}\n";
        } catch (Exception $e) {
            error_log("Failed to handle email: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString() . "\nRaw message: " . substr($rawMessage ?? '', 0, 1000), 3, storage_path('logs/smtp.log'));
            throw $e;
        }
    }

    private function extractEmail($string)
    {
        if (empty($string))
            return null;
        if (preg_match('/<([^>]+)>/', $string, $matches)) {
            return $matches[1];
        }
        return $string;
    }

    private function decodeContent($content, $headers)
    {
        if (preg_match('/Content-Transfer-Encoding: quoted-printable/i', $headers)) {
            return quoted_printable_decode($content);
        } elseif (preg_match('/Content-Transfer-Encoding: base64/i', $headers)) {
            return base64_decode($content);
        }
        return $content;
    }

    private function send($client, $message)
    {
        socket_write($client, $message);
    }

    private function closeClient($clientId)
    {
        if (isset($this->clients[$clientId])) {
            socket_close($this->clients[$clientId]['socket']);
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
            if (is_array($client) && isset($client['socket']) && is_resource($client['socket'])) {
                @socket_close($client['socket']);
            }
        }
        if (is_resource($this->socket)) {
            @socket_close($this->socket);
        }
    }
}

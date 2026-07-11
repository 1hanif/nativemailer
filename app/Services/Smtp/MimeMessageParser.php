<?php

namespace App\Services\Smtp;

use App\Support\MimeHeader;
use Exception;

/**
 * Parses a raw RFC 5322 message into the attributes stored on the
 * Email model: from, to, subject, body_text, body_html, attachments.
 * Handles nested multipart (alternative/mixed/related), quoted-printable
 * and base64 transfer encodings, folded headers and RFC 2047
 * encoded-words.
 */
class MimeMessageParser
{
    /**
     * @throws Exception when the message has no parseable headers
     */
    public function parse(string $rawMessage, ?string $envelopeFrom = null, array $envelopeRecipients = []): array
    {
        $rawMessage = rtrim($rawMessage, "\r\n");

        // Normalize line endings to \r\n
        $rawMessage = str_replace(["\r\n", "\r"], "\n", $rawMessage);
        $rawMessage = str_replace("\n", "\r\n", $rawMessage);

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

        $fromEmail = self::extractAddress($headers['from'] ?? null) ?? $envelopeFrom;
        $toEmails = array_filter(array_map(
            [self::class, 'extractAddress'],
            preg_split('/\s*,\s*/', $headers['to'] ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: []
        ));
        if (empty($toEmails)) {
            $toEmails = $envelopeRecipients;
        }

        $result = ['text' => null, 'html' => null, 'attachments' => []];
        $this->parseMimePart($headers, $bodyString, $result);

        return [
            'from' => $fromEmail,
            'to' => implode(', ', $toEmails),
            'subject' => MimeHeader::decode($headers['subject'] ?? null),
            'raw' => $rawMessage,
            'received_at' => now(),
            'body_text' => $result['text'],
            'body_html' => $result['html'],
            'attachments' => $result['attachments'],
        ];
    }

    /**
     * Extract a bare address from an SMTP argument or header value,
     * e.g. "John <j@x.test>" => "j@x.test".
     */
    public static function extractAddress(?string $string): ?string
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

    /**
     * Parse a header block into a lowercase-keyed array, unfolding
     * continuation lines (RFC 5322 §2.2.3).
     */
    private function parseHeaders(string $headerString): array
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
    private function parseMimePart(array $headers, string $body, array &$result): void
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

                $this->parseMimePart($this->parseHeaders($partHeaderString), $partBody, $result);
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
                'name' => MimeHeader::decode($filename) ?? 'unnamed',
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

    private function decodeContent(string $content, string $encoding): string
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
}

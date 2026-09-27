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
        $toEmails = self::extractAddressList($headers['to'] ?? null);
        if (empty($toEmails)) {
            $toEmails = $envelopeRecipients;
        }
        $ccEmails = self::extractAddressList($headers['cc'] ?? null);

        // Bcc never appears in the delivered headers: it is whoever was on
        // the SMTP envelope but not in To/Cc.
        $visible = array_map('strtolower', [...$toEmails, ...$ccEmails]);
        $bccEmails = array_values(array_unique(array_filter(
            $envelopeRecipients,
            fn (string $rcpt) => !in_array(strtolower($rcpt), $visible, true)
        )));

        $result = ['text' => null, 'html' => null, 'attachments' => []];
        $this->parseMimePart($headers, $bodyString, $result);

        return [
            'from' => $fromEmail,
            'to' => implode(', ', $toEmails),
            'cc' => $ccEmails ? implode(', ', $ccEmails) : null,
            'bcc' => $bccEmails ? implode(', ', $bccEmails) : null,
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
        // No angle brackets: take the first token, dropping any trailing
        // ESMTP parameters (e.g. "a@x.test SIZE=123")
        $string = trim(preg_split('/\s+/', trim($string))[0] ?? '');

        return $string === '' ? null : $string;
    }

    /**
     * Extract bare addresses from an address-list header, splitting only
     * on commas outside quotes and angle brackets, so display names like
     * "Doe, John" <j@x.test> stay intact.
     *
     * @return list<string>
     */
    public static function extractAddressList(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            return [];
        }

        $parts = [];
        $current = '';
        $inQuotes = false;
        $inAngle = false;
        $length = strlen($header);

        for ($i = 0; $i < $length; $i++) {
            $char = $header[$i];

            if ($char === '\\' && $inQuotes && $i + 1 < $length) {
                $current .= $char . $header[++$i];
                continue;
            }
            if ($char === '"') {
                $inQuotes = !$inQuotes;
            } elseif (!$inQuotes && $char === '<') {
                $inAngle = true;
            } elseif (!$inQuotes && $char === '>') {
                $inAngle = false;
            } elseif ($char === ',' && !$inQuotes && !$inAngle) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = $current;

        // Unwrap RFC 5322 groups ("Team: a@x.test, b@x.test;") and drop
        // anything that isn't an address, e.g. "undisclosed-recipients:;"
        $parts = array_map(fn ($part) => rtrim(preg_replace('/^[^"<@]*:/', '', $part), '; '), $parts);

        return array_values(array_filter(
            array_map([self::class, 'extractAddress'], $parts),
            fn (?string $address) => $address !== null && str_contains($address, '@')
        ));
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
        $mimeType = strtolower(trim(explode(';', $contentType)[0]));
        $typeParams = self::parseParameters($contentType);
        $filename = self::parseParameters($disposition)['filename'] ?? $typeParams['name'] ?? null;
        $contentId = isset($headers['content-id']) ? trim($headers['content-id'], " <>") : null;

        $decoded = $this->decodeContent($body, $headers['content-transfer-encoding'] ?? '');

        $isBody = in_array($mimeType, ['text/plain', 'text/html'], true)
            && $filename === null
            && stripos($disposition, 'attachment') === false;

        if (!$isBody) {
            // Anything that isn't a plain/HTML body is kept as an attachment,
            // including inline images referenced from the HTML via cid:
            $result['attachments'][] = [
                'name' => MimeHeader::decode($filename) ?? ($contentId ?: 'unnamed'),
                'content_type' => $mimeType,
                'size' => strlen($decoded),
                'content_id' => $contentId ?: null,
                'inline' => $contentId && stripos($disposition, 'attachment') === false,
                'content' => base64_encode($decoded),
            ];
        } elseif ($mimeType === 'text/html') {
            $result['html'] = $result['html'] ?? self::toUtf8($decoded, $typeParams['charset'] ?? null);
        } else {
            $result['text'] = $result['text'] ?? self::toUtf8($decoded, $typeParams['charset'] ?? null);
        }
    }

    /**
     * Parse the parameters of a structured header value such as
     * Content-Type or Content-Disposition into a lowercase-keyed array.
     * Handles quoted values and RFC 2231 extended parameters, including
     * charset-encoded (name*=UTF-8''%E2%9C%93.pdf) and continuation
     * (name*0=..., name*1*=...) forms.
     *
     * @return array<string, string>
     */
    public static function parseParameters(string $value): array
    {
        preg_match_all(
            '/;\s*([^=\s;]+)\s*=\s*(?:"((?:[^"\\\\]|\\\\.)*)"|([^;\s]*))/',
            $value,
            $matches,
            PREG_SET_ORDER
        );

        $params = [];
        $extended = []; // name => [section => [value, isEncoded]]

        foreach ($matches as $m) {
            $name = strtolower($m[1]);
            $raw = isset($m[3]) && $m[3] !== '' ? $m[3] : stripslashes($m[2]);

            if (preg_match('/^(.+?)(?:\*(\d+))?(\*)?$/', $name, $parts) && (isset($parts[2]) && $parts[2] !== '' || !empty($parts[3]))) {
                $extended[$parts[1]][(int) ($parts[2] ?? 0)] = [$raw, !empty($parts[3])];
            } else {
                $params[$name] = $raw;
            }
        }

        foreach ($extended as $name => $sections) {
            ksort($sections);
            $charset = null;
            $decoded = '';

            foreach ($sections as $index => [$raw, $isEncoded]) {
                if ($isEncoded) {
                    // The first encoded section carries charset'language'
                    if ($index === array_key_first($sections) && preg_match("/^([^']*)'[^']*'(.*)$/s", $raw, $cm)) {
                        $charset = $cm[1] ?: null;
                        $raw = $cm[2];
                    }
                    $raw = rawurldecode($raw);
                }
                $decoded .= $raw;
            }

            // Extended parameters take precedence over plain ones
            $params[$name] = self::toUtf8($decoded, $charset);
        }

        return $params;
    }

    /**
     * Convert text to UTF-8 from the declared charset. With no charset
     * (or an unknown one), invalid UTF-8 is assumed to be Windows-1252,
     * the most common mislabelled encoding.
     */
    public static function toUtf8(string $text, ?string $charset): string
    {
        $charset = $charset !== null ? strtoupper(trim($charset)) : null;

        if (in_array($charset, ['UTF-8', 'UTF8', 'US-ASCII', 'ASCII'], true) || ($charset === null && mb_check_encoding($text, 'UTF-8'))) {
            return mb_check_encoding($text, 'UTF-8') ? $text : mb_scrub($text, 'UTF-8');
        }

        if ($charset !== null) {
            if (in_array($charset, array_map('strtoupper', mb_list_encodings()), true)) {
                return mb_convert_encoding($text, 'UTF-8', $charset);
            }
            $converted = @iconv($charset, 'UTF-8//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                return $converted;
            }
        }

        return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
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

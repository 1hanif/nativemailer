<?php

namespace App\Support;

class MimeHeader
{
    /**
     * Decode RFC 2047 encoded-words in a header value,
     * e.g. "=?utf-8?Q?=F0=9F=9A=80?= Hello" => "🚀 Hello".
     *
     * Safe to call on already-decoded values (no-op).
     */
    public static function decode(?string $value): ?string
    {
        if ($value === null || strpos($value, '=?') === false) {
            return $value;
        }

        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if ($decoded !== false && $decoded !== null) {
                return $decoded;
            }
        }

        if (function_exists('mb_decode_mimeheader')) {
            $decoded = @mb_decode_mimeheader($value);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        // Manual fallback. Whitespace between adjacent encoded-words
        // is not significant (RFC 2047 §6.2) — drop it first.
        $value = preg_replace('/(\?=)[ \t]+(=\?)/', '$1$2', $value);

        return preg_replace_callback(
            '/=\?([^?]+)\?([QqBb])\?([^?]*)\?=/',
            function ($m) {
                $text = strtoupper($m[2]) === 'B'
                    ? (base64_decode($m[3]) ?: '')
                    : quoted_printable_decode(str_replace('_', ' ', $m[3]));

                $charset = strtoupper($m[1]);
                if ($charset !== 'UTF-8' && $charset !== 'US-ASCII' && function_exists('mb_convert_encoding')) {
                    $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
                    if ($converted !== false) {
                        $text = $converted;
                    }
                }

                return $text;
            },
            $value
        );
    }
}

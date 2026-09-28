<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Prepares email HTML for the sandboxed preview iframe so its links open
 * in the system browser.
 *
 * The iframe runs in an opaque origin, which Chromium blocks from
 * reaching 127.0.0.1, so it can't call back to the app over HTTP, and
 * letting it navigate would load the target site inside the preview.
 * Instead a Content-Security-Policy allows exactly one script, ours (by
 * nonce), which forwards link clicks to the parent window with
 * postMessage. The email's own <script> tags, on*= handlers and
 * javascript: URLs stay blocked by the same policy.
 */
class PreviewLinks
{
    public const MESSAGE_TYPE = 'nativemailer:open-link';

    public static function prepare(string $html): string
    {
        $nonce = Str::random(24);
        $type = json_encode(self::MESSAGE_TYPE);

        $head = <<<HTML
            <meta http-equiv="Content-Security-Policy" content="script-src 'nonce-{$nonce}'; object-src 'none'; base-uri 'none'">
            <script nonce="{$nonce}">
            document.addEventListener('click', function (e) {
                var a = e.target.closest && e.target.closest('a[href]');
                if (!a) return;
                var href = a.getAttribute('href') || '';
                if (href.charAt(0) === '#') return;
                e.preventDefault();
                if (/^(https?|mailto):/i.test(a.href)) parent.postMessage({ type: {$type}, url: a.href }, '*');
            }, true);
            </script>
            HTML;

        // A CSP <meta> must come before any content to take effect, so it
        // goes first; the parser hoists it into <head>
        return $head.$html;
    }

    public static function isOpenable(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true)
            && ($scheme === 'mailto' || filter_var($url, FILTER_VALIDATE_URL) !== false);
    }
}

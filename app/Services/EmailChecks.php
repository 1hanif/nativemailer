<?php

namespace App\Services;

use App\Models\Email;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Static checks on a captured email's HTML: the mistakes that make an
 * email look broken, get clipped, or leak development URLs to users.
 * Nothing here touches the network.
 */
class EmailChecks
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    /** Gmail clips messages whose HTML is larger than this */
    public const GMAIL_CLIP_BYTES = 102 * 1024;

    /** Hosts that only exist on a developer's machine */
    private const DEV_HOST = '/^(localhost|127\.\d+\.\d+\.\d+|0\.0\.0\.0|\[::1\]|.+\.(test|local|localhost|invalid|example))$/i';

    /** CSS Outlook desktop (Word rendering engine) ignores */
    private const OUTLOOK_UNSUPPORTED_CSS = [
        'display: flex' => '/display\s*:\s*(inline-)?flex\b/i',
        'display: grid' => '/display\s*:\s*(inline-)?grid\b/i',
        'position' => '/(?<![-\w])position\s*:\s*(absolute|relative|fixed|sticky)\b/i',
    ];

    /** @return list<array{level: string, category: string, message: string, detail: ?string}> */
    public static function run(Email $email): array
    {
        $html = (string) $email->body_html;
        if (trim($html) === '') {
            return [];
        }

        $issues = [];
        $add = function (string $level, string $category, string $message, ?string $detail = null) use (&$issues) {
            $issues[] = compact('level', 'category', 'message', 'detail');
        };

        $xpath = self::parse($html);

        foreach ($xpath->query('//a') as $a) {
            self::checkLink($a, $add);
        }

        $cids = $email->attachments()->withoutContent()->whereNotNull('content_id')->pluck('content_id')
            ->map(fn ($cid) => strtolower($cid))->all();
        foreach ($xpath->query('//img') as $img) {
            self::checkImage($img, $cids, $add);
        }

        if (strlen($html) > self::GMAIL_CLIP_BYTES) {
            $add(self::WARNING, 'Size', 'HTML is over 102 KB, so Gmail will clip it behind "View entire message"',
                number_format(strlen($html) / 1024, 1).' KB');
        }

        if (blank($email->body_text)) {
            $add(self::WARNING, 'Structure', 'No plain-text part', 'Some clients and spam filters expect a text/plain alternative');
        }

        if ($xpath->query('//meta[translate(@name, "VIEWPORT", "viewport")="viewport"]')->length === 0) {
            $add(self::WARNING, 'Structure', 'No viewport meta tag', 'Mobile clients may render the email zoomed out');
        }

        $css = implode("\n", array_merge(
            array_map(fn ($node) => $node->textContent, iterator_to_array($xpath->query('//style'))),
            array_map(fn ($node) => $node->value, iterator_to_array($xpath->query('//@style')))
        ));
        foreach (self::OUTLOOK_UNSUPPORTED_CSS as $label => $pattern) {
            if (preg_match($pattern, $css)) {
                $add(self::WARNING, 'CSS', "Uses {$label}, which Outlook desktop ignores", 'Layouts built on it fall apart there; tables are the safe fallback');
            }
        }

        return $issues;
    }

    private static function checkLink(DOMElement $a, callable $add): void
    {
        $href = trim($a->getAttribute('href'));
        $text = trim(preg_replace('/\s+/', ' ', $a->textContent));
        $label = $text !== '' ? "\"{$text}\"" : 'A link';

        if (! $a->hasAttribute('href')) {
            return; // a named anchor, not a link
        }
        if ($href === '' || $href === '#') {
            $add(self::ERROR, 'Links', "{$label} goes nowhere", 'href="'.$href.'"');

            return;
        }
        if (preg_match('/^\s*javascript:/i', $href)) {
            $add(self::ERROR, 'Links', "{$label} uses javascript:, which email clients strip", $href);

            return;
        }
        if (str_starts_with($href, '#') || preg_match('/^(mailto|tel|sms):/i', $href)) {
            return;
        }
        if (! preg_match('#^https?://#i', $href)) {
            $add(self::ERROR, 'Links', "{$label} is a relative URL, which is broken outside your app", $href);

            return;
        }

        if (self::isDevHost($href)) {
            $add(self::ERROR, 'Links', "{$label} points at a development host", $href);
        } elseif (str_starts_with(strtolower($href), 'http://')) {
            $add(self::WARNING, 'Links', "{$label} uses http:// instead of https://", $href);
        }

        if ($text === '' && $a->getElementsByTagName('img')->length === 0 && trim($a->getAttribute('aria-label')) === '') {
            $add(self::WARNING, 'Accessibility', 'A link has no text or aria-label', $href);
        }
    }

    private static function checkImage(DOMElement $img, array $cids, callable $add): void
    {
        $src = trim($img->getAttribute('src'));
        $name = $src !== '' ? basename(parse_url($src, PHP_URL_PATH) ?: $src) : 'An image';

        if (! $img->hasAttribute('alt')) {
            $add(self::WARNING, 'Accessibility', "{$name} has no alt text", 'Shown when images are blocked, and read by screen readers');
        }

        if ($src === '') {
            $add(self::ERROR, 'Images', 'An image has no src');
        } elseif (preg_match('/^cid:(.+)$/i', $src, $m)) {
            if (! in_array(strtolower(rawurldecode($m[1])), $cids, true)) {
                $add(self::ERROR, 'Images', 'An inline image is missing its attachment', $src);
            }
        } elseif (preg_match('#^https?://#i', $src)) {
            if (self::isDevHost($src)) {
                $add(self::ERROR, 'Images', "{$name} is loaded from a development host", $src);
            } elseif (str_starts_with(strtolower($src), 'http://')) {
                $add(self::WARNING, 'Images', "{$name} is loaded over http://", $src);
            }
        } elseif (! str_starts_with($src, 'data:')) {
            $add(self::ERROR, 'Images', "{$name} uses a relative URL, which is broken outside your app", $src);
        }
    }

    private static function isDevHost(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return $host !== '' && preg_match(self::DEV_HOST, $host) === 1;
    }

    private static function parse(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // The XML prolog makes libxml read the markup as UTF-8
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }
}

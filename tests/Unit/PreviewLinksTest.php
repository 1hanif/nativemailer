<?php

use App\Support\PreviewLinks;

test('the CSP comes first and only allows the nonced forwarder script', function () {
    $html = PreviewLinks::prepare('<p>Hi</p><script>alert(1)</script>');

    preg_match("/script-src 'nonce-([A-Za-z0-9]+)'/", $html, $m);

    expect($html)->toStartWith('<meta http-equiv="Content-Security-Policy"')
        ->and($m[1] ?? null)->not->toBeNull()
        ->and($html)->toContain('<script nonce="'.$m[1].'">')
        // the email's own script is left in place but carries no nonce
        ->and($html)->toContain('<script>alert(1)</script>');
});

test('every render uses a fresh nonce', function () {
    $nonce = fn () => preg_match('/nonce-([A-Za-z0-9]+)/', PreviewLinks::prepare(''), $m) ? $m[1] : null;

    expect($nonce())->not->toBe($nonce());
});

test('only web and mailto links are openable', function (string $url, bool $openable) {
    expect(PreviewLinks::isOpenable($url))->toBe($openable);
})->with([
    ['https://shop.test/a?b=1', true],
    ['http://shop.test', true],
    ['mailto:help@x.test', true],
    ['javascript:alert(1)', false],
    ['file:///etc/passwd', false],
    ['data:text/html,hi', false],
    ['https://', false],
]);

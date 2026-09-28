<?php

use App\Models\Email;
use App\Services\EmailChecks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function checksFor(string $html, ?string $text = 'text part', array $inlineCids = []): array
{
    $email = Email::create(['from' => 'a@x.test', 'body_html' => $html, 'body_text' => $text]);
    foreach ($inlineCids as $cid) {
        $email->attachments()->create(['name' => $cid, 'content_type' => 'image/png', 'size' => 1, 'content_id' => $cid, 'inline' => true, 'content' => 'x']);
    }

    return EmailChecks::run($email);
}

function messages(array $issues): array
{
    return array_map(fn ($i) => $i['level'].': '.$i['message'], $issues);
}

const CLEAN_HEAD = '<meta name="viewport" content="width=device-width">';

test('a well-built email passes', function () {
    $issues = checksFor(CLEAN_HEAD.'<p><a href="https://shop.test.com/o/1">View order</a> <a href="mailto:help@shop.com">Help</a> <a href="#top">Top</a></p>'
        .'<img src="https://cdn.shop.com/logo.png" alt="Shop"><img src="cid:logo@x" alt="">', inlineCids: ['logo@x']);

    expect($issues)->toBe([]);
});

test('link problems are reported', function () {
    $issues = messages(checksFor(CLEAN_HEAD
        .'<a href="http://localhost:8000/verify?t=1">Verify email</a>'
        .'<a href="https://myapp.test/reset">Reset</a>'
        .'<a href="#">Click</a>'
        .'<a href="">Empty</a>'
        .'<a href="javascript:void(0)">JS</a>'
        .'<a href="/orders/1">Relative</a>'
        .'<a href="http://shop.com">Insecure</a>'
        .'<a href="https://shop.com/x"></a>'
        .'<a name="anchor">named anchors are ignored</a>'));

    expect($issues)->toBe([
        'error: "Verify email" points at a development host',
        'error: "Reset" points at a development host',
        'error: "Click" goes nowhere',
        'error: "Empty" goes nowhere',
        'error: "JS" uses javascript:, which email clients strip',
        'error: "Relative" is a relative URL, which is broken outside your app',
        'warning: "Insecure" uses http:// instead of https://',
        'warning: A link has no text or aria-label',
    ]);
});

test('image problems are reported', function () {
    $issues = messages(checksFor(CLEAN_HEAD
        .'<img src="https://cdn.shop.com/a.png">'
        .'<img src="cid:missing@x" alt="">'
        .'<img src="http://127.0.0.1:8000/storage/b.png" alt="">'
        .'<img src="http://cdn.shop.com/c.png" alt="">'
        .'<img src="/images/d.png" alt="">'));

    expect($issues)->toBe([
        'warning: a.png has no alt text',
        'error: An inline image is missing its attachment',
        'error: b.png is loaded from a development host',
        'warning: c.png is loaded over http://',
        'error: d.png uses a relative URL, which is broken outside your app',
    ]);
});

test('size, structure and Outlook CSS are reported', function () {
    $html = '<style>.row { display: flex; }</style><div style="position: absolute">x</div>'.str_repeat('<p>padding</p>', 8000);

    expect(messages(checksFor($html, text: null)))->toBe([
        'warning: HTML is over 102 KB, so Gmail will clip it behind "View entire message"',
        'warning: No plain-text part',
        'warning: No viewport meta tag',
        'warning: Uses display: flex, which Outlook desktop ignores',
        'warning: Uses position, which Outlook desktop ignores',
    ]);
});

test('non-ASCII link text is kept intact', function () {
    expect(messages(checksFor(CLEAN_HEAD.'<a href="#">Réinitialiser le mot de passe →</a>')))
        ->toBe(['error: "Réinitialiser le mot de passe →" goes nowhere']);
});

test('text-only emails have nothing to check', function () {
    expect(EmailChecks::run(Email::create(['from' => 'a@x.test', 'body_text' => 'hi'])))->toBe([]);
});

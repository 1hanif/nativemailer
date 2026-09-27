<?php

use App\Services\Smtp\MimeMessageParser;
use Tests\TestCase;

// parse() uses Laravel's now() helper, so boot the app
uses(TestCase::class);

function parseMessage(string $headers, array $envelope = []): array
{
    return (new MimeMessageParser())->parse($headers . "\r\n\r\nBody", 'env@x.test', $envelope);
}

test('address lists keep commas inside quoted display names', function () {
    expect(MimeMessageParser::extractAddressList('"Doe, John" <john@x.test>, jane@x.test'))
        ->toBe(['john@x.test', 'jane@x.test']);
});

test('address lists unwrap groups and drop undisclosed-recipients', function () {
    expect(MimeMessageParser::extractAddressList('Team: a@x.test, b@x.test;'))->toBe(['a@x.test', 'b@x.test'])
        ->and(MimeMessageParser::extractAddressList('undisclosed-recipients:;'))->toBe([]);
});

test('cc is captured from the header', function () {
    $data = parseMessage("To: to@x.test\r\nCc: \"Smith, A\" <a@x.test>, b@x.test", ['to@x.test', 'a@x.test', 'b@x.test']);

    expect($data['cc'])->toBe('a@x.test, b@x.test')
        ->and($data['bcc'])->toBeNull();
});

test('bcc is envelope recipients missing from To and Cc', function () {
    $data = parseMessage("To: to@x.test\r\nCc: cc@x.test", ['to@x.test', 'CC@x.test', 'secret@x.test', 'secret@x.test']);

    expect($data['to'])->toBe('to@x.test')
        ->and($data['bcc'])->toBe('secret@x.test');
});

test('missing To header falls back to the envelope without duplicating into bcc', function () {
    $data = parseMessage('Subject: No To', ['a@x.test', 'b@x.test']);

    expect($data['to'])->toBe('a@x.test, b@x.test')
        ->and($data['bcc'])->toBeNull();
});

test('undisclosed-recipients To header falls back to the envelope', function () {
    $data = parseMessage('To: undisclosed-recipients:;', ['a@x.test']);

    expect($data['to'])->toBe('a@x.test')
        ->and($data['bcc'])->toBeNull();
});

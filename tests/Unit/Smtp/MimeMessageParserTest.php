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

function multipart(string $parts): string
{
    return "From: a@x.test\r\nTo: b@x.test\r\nContent-Type: multipart/related; boundary=\"B\"\r\n\r\n"
        . $parts . "\r\n--B--\r\n";
}

test('ISO-8859-1 bodies are converted to UTF-8', function () {
    $raw = "From: a@x.test\r\nContent-Type: text/plain; charset=ISO-8859-1\r\n\r\n" . "Caf\xE9 cr\xE8me";

    expect((new MimeMessageParser())->parse($raw)['body_text'])->toBe('Café crème');
});

test('quoted-printable Windows-1252 HTML is converted to UTF-8', function () {
    $raw = "From: a@x.test\r\nContent-Type: text/html; charset=\"windows-1252\"\r\n"
        . "Content-Transfer-Encoding: quoted-printable\r\n\r\n<p>=93Smart quotes=94 =80</p>";

    expect((new MimeMessageParser())->parse($raw)['body_html'])->toBe('<p>“Smart quotes” €</p>');
});

test('unlabelled invalid UTF-8 falls back to Windows-1252', function () {
    $raw = "From: a@x.test\r\n\r\nna\xEFve";

    expect((new MimeMessageParser())->parse($raw)['body_text'])->toBe('naïve');
});

test('RFC 2231 encoded filenames are decoded', function () {
    $raw = multipart("--B\r\nContent-Type: text/plain\r\n\r\nhi\r\n"
        . "--B\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename*=UTF-8''r%C3%A9sum%C3%A9%20%E2%9C%93.pdf\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n" . base64_encode('PDF'));

    expect((new MimeMessageParser())->parse($raw)['attachments'][0]['name'])->toBe('résumé ✓.pdf');
});

test('RFC 2231 continuation filenames are joined', function () {
    expect(MimeMessageParser::parseParameters('attachment; filename*0="very-long-"; filename*1="name.txt"')['filename'])
        ->toBe('very-long-name.txt');
    expect(MimeMessageParser::parseParameters("attachment; filename*0*=UTF-8''caf; filename*1*=%C3%A9.txt")['filename'])
        ->toBe('café.txt');
});

test('inline images without a filename become attachments, not the text body', function () {
    $raw = multipart("--B\r\nContent-Type: text/html; charset=utf-8\r\n\r\n<img src=\"cid:logo@x\">\r\n"
        . "--B\r\nContent-Type: image/png\r\nContent-ID: <logo@x>\r\nContent-Disposition: inline\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n" . base64_encode('PNGDATA'));

    $data = (new MimeMessageParser())->parse($raw);

    expect($data['body_text'])->toBeNull()
        ->and($data['attachments'])->toHaveCount(1)
        ->and($data['attachments'][0])->toMatchArray([
            'content_type' => 'image/png',
            'content_id' => 'logo@x',
            'inline' => true,
        ]);
});

test('cid references are swapped for data URIs in the preview HTML', function () {
    $email = new App\Models\Email([
        'body_html' => '<img src="cid:Logo@x"><img src="cid:missing@x">',
        'attachments' => [['content_type' => 'image/png', 'content_id' => 'logo@x', 'content' => base64_encode('PNG')]],
    ]);

    expect($email->htmlWithInlineImages())
        ->toBe('<img src="data:image/png;base64,' . base64_encode('PNG') . '"><img src="cid:missing@x">');
});

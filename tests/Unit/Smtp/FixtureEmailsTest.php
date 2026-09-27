<?php

use App\Services\Smtp\MimeMessageParser;
use Tests\TestCase;

/*
 * Real-world messages in tests/Fixtures/emails, produced by Symfony Mime
 * (what Laravel's mailer uses), Python's email package, and hand-written
 * copies of Apple Mail / Thunderbird / Outlook / calendar patterns.
 */

uses(TestCase::class);

function parseFixture(string $name, array $envelope = []): array
{
    $raw = file_get_contents(base_path("tests/Fixtures/emails/{$name}.eml"));

    return (new MimeMessageParser())->parse($raw, 'envelope@x.test', $envelope);
}

function attachmentSummary(array $data): array
{
    return array_map(fn (array $att) => [
        'name' => $att['name'],
        'content_type' => $att['content_type'],
        'content_id' => $att['content_id'],
        'inline' => $att['inline'],
    ], $data['attachments']);
}

test('Symfony Mailable: headers, bodies, inline image and UTF-8 filename', function () {
    $data = parseFixture('symfony-mailable', ['jane@x.test', 'ops@x.test', 'manager@x.test', 'audit@x.test']);

    expect($data)
        ->from->toBe('orders@shop.test')
        ->to->toBe('jane@x.test, ops@x.test')
        ->cc->toBe('manager@x.test')
        ->bcc->toBe('audit@x.test')
        ->subject->toBe('Your order #1042 🚀 is confirmed')
        ->body_text->toContain('Total: €42.00')
        ->body_html->toContain('<img src="cid:logo@shop.test"');

    expect(attachmentSummary($data))->toBe([
        ['name' => 'logo.png', 'content_type' => 'image/png', 'content_id' => 'logo@shop.test', 'inline' => true],
        ['name' => 'facture n°1042 – été.pdf', 'content_type' => 'application/pdf', 'content_id' => null, 'inline' => false],
    ]);
    expect($data['attachments'][0]['content'])->toBe(file_get_contents(base_path('logo.png')))
        ->and($data['attachments'][1]['content'])->toBe('%PDF-1.4 invoice');
});

test('ISO-8859-1 quoted-printable text and encoded subject', function () {
    $data = parseFixture('iso-8859-1-quoted-printable');

    expect($data)
        ->subject->toBe('Résumé attached')
        ->body_text->toBe("Café crème, naïve façade.\r\nÀ bientôt!")
        ->body_html->toBeNull()
        ->attachments->toBe([]);
});

test('Windows-1252 base64 HTML', function () {
    $data = parseFixture('windows-1252-base64-html');

    expect($data['body_html'])->toBe('<p>“Smart quotes” cost €5 — that’s it.</p>');
});

test('Apple Mail nested mixed > alternative > related', function () {
    $data = parseFixture('apple-nested-multipart');

    expect($data)
        ->from->toBe('sam@mac.test')
        ->body_text->toBe('See the chart below.')
        ->body_html->toBe('<html><body><p>See the chart below.</p><img src="cid:chart-1@mac.test"></body></html>');

    expect(attachmentSummary($data))->toBe([
        ['name' => 'chart.png', 'content_type' => 'image/png', 'content_id' => 'chart-1@mac.test', 'inline' => true],
        ['name' => 'data.csv', 'content_type' => 'text/csv', 'content_id' => null, 'inline' => false],
    ]);
    expect($data['attachments'][0]['content'])->toBe(file_get_contents(base_path('logo.png')));
});

test('Thunderbird RFC 2231 continuations and RFC 2047 quoted filenames', function () {
    $data = parseFixture('thunderbird-rfc2231-filenames');

    expect($data)
        ->subject->toBe('Привет, мир!')
        ->body_text->toBe('Два файла во вложении.');

    expect(array_column($data['attachments'], 'name'))->toBe(['Очень длинное имя.pdf', 'résumé.txt'])
        ->and($data['attachments'][0]['content'])->toBe('%PDF-1.4')
        ->and($data['attachments'][1]['content'])->toBe('cv');
});

test('calendar invites keep the text body and store the .ics as an attachment', function () {
    $data = parseFixture('calendar-invite');

    expect($data['body_text'])->toBe('You have been invited to Standup.')
        ->and($data['attachments'])->toHaveCount(1)
        ->and($data['attachments'][0]['content_type'])->toBe('text/calendar')
        ->and($data['attachments'][0]['content'])->toContain('SUMMARY:Standup');
});

test('minimal message with bare LF line endings', function () {
    $data = parseFixture('minimal-lf-only', ['admin@x.test']);

    expect($data)
        ->from->toBe('cron@server.test')
        ->to->toBe('admin@x.test')
        ->subject->toBe('Backup finished')
        ->body_text->toBe('All 3 databases backed up.')
        ->bcc->toBeNull();
});

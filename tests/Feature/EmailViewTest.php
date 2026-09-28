<?php

use App\Models\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function emailWithAttachments(): Email
{
    $email = Email::create([
        'from' => 'a@x.test',
        'to' => 'b@x.test',
        'cc' => 'c@x.test',
        'bcc' => 'secret@x.test',
        'subject' => 'Inline test',
        'body_html' => '<p><img src="cid:Logo@x"><img src="cid:missing@x"></p>',
        'raw' => "From: a@x.test\r\n\r\n",
    ]);

    $email->attachments()->createMany([
        ['name' => 'logo.png', 'content_type' => 'image/png', 'size' => 7, 'content_id' => 'logo@x', 'inline' => true, 'content' => "\x89PNG\r\n\x1a\n"],
        ['name' => 'résumé.pdf', 'content_type' => 'application/pdf', 'size' => 3, 'content' => 'PDF'],
    ]);

    return $email;
}

test('only cid-referenced images are embedded, case-insensitively', function () {
    $email = emailWithAttachments();

    expect($email->htmlWithInlineImages())
        ->toBe('<p><img src="data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\n").'"><img src="cid:missing@x"></p>');
});

test('the view page embeds inline images and links other attachments', function () {
    $email = emailWithAttachments();
    $pdf = $email->attachments()->where('name', 'résumé.pdf')->first();

    $html = $this->get("/admin/emails/{$email->id}")->assertOk()->getContent();

    expect($html)
        ->toContain('&lt;img src=&quot;data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\n"))
        // the PDF is a download link, never inlined
        ->not->toContain('data:application/pdf')
        ->toContain(e($pdf->url()).'?download=1')
        ->toContain('secret@x.test')
        ->toContain('c@x.test')
        // the inline logo is shown in the HTML, so only the PDF gets a card
        ->and(substr_count($html, 'class="ev-attach-card"'))->toBe(1);
});

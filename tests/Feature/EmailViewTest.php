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

test('cid references point at the attachment URL, case-insensitively', function () {
    $email = emailWithAttachments();
    $logo = $email->attachments()->where('name', 'logo.png')->first();

    expect($email->htmlWithInlineImages())
        ->toBe('<p><img src="'.$logo->url().'"><img src="cid:missing@x"></p>');
});

test('the view page links attachments instead of embedding them', function () {
    $email = emailWithAttachments();
    $logo = $email->attachments()->where('name', 'logo.png')->first();

    $html = $this->get("/admin/emails/{$email->id}")->assertOk()->getContent();

    expect($html)
        ->toContain('srcdoc="&lt;p&gt;&lt;img src=&quot;'.e($logo->url()))
        ->not->toContain('base64,')
        ->toContain('secret@x.test')
        ->toContain('c@x.test')
        ->toContain('résumé.pdf')
        // the inline logo is shown in the HTML, so only the PDF gets a card
        ->and(substr_count($html, 'class="ev-attach-card"'))->toBe(1);
});

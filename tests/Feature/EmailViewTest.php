<?php

use App\Models\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the preview renders inline images and keeps them out of the attachment strip', function () {
    $png = base64_encode('PNGDATA');
    $email = Email::create([
        'from' => 'a@x.test',
        'to' => 'b@x.test',
        'cc' => 'c@x.test',
        'bcc' => 'secret@x.test',
        'subject' => 'Inline test',
        'body_html' => '<p><img src="cid:logo@x"></p>',
        'attachments' => [
            ['name' => 'logo.png', 'content_type' => 'image/png', 'size' => 7, 'content_id' => 'logo@x', 'inline' => true, 'content' => $png],
            ['name' => 'résumé.pdf', 'content_type' => 'application/pdf', 'size' => 3, 'content_id' => null, 'inline' => false, 'content' => base64_encode('PDF')],
        ],
        'raw' => "From: a@x.test\r\n\r\n",
    ]);

    $html = $this->get("/admin/emails/{$email->id}")->assertOk()->getContent();

    expect($html)
        ->toContain('srcdoc="&lt;p&gt;&lt;img src=&quot;data:image/png;base64,' . $png)
        ->toContain('secret@x.test')
        ->toContain('c@x.test')
        ->and(substr_count($html, 'class="ev-attach-card"'))->toBe(1)
        ->and($html)->toContain('résumé.pdf');
});

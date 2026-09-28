<?php

use App\Filament\Resources\Emails\Pages\ListEmails;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Services\Smtp\PersistCapturedEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function captureWithPdf(string $pdfBytes): Email
{
    $raw = "From: a@x.test\r\nTo: b@x.test\r\nSubject: Report\r\nContent-Type: multipart/mixed; boundary=\"B\"\r\n\r\n"
        ."--B\r\nContent-Type: text/plain\r\n\r\nSee attached\r\n"
        ."--B\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename=\"report.pdf\"\r\n"
        ."Content-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($pdfBytes))."\r\n--B--\r\n";

    (new PersistCapturedEmail)($raw, 'a@x.test', ['b@x.test']);

    return Email::latest('id')->firstOrFail();
}

test('captured attachments are stored as raw bytes in their own table', function () {
    $bytes = random_bytes(2048);
    $email = captureWithPdf($bytes);

    $attachment = $email->attachments()->sole();

    expect($attachment->name)->toBe('report.pdf')
        ->and($attachment->size)->toBe(2048)
        ->and($attachment->content)->toBe($bytes);
});

test('deleting an email deletes its attachments', function () {
    $email = captureWithPdf('PDF');

    Email::query()->whereKey($email->id)->delete();

    expect(EmailAttachment::count())->toBe(0);
});

test('non-image attachments download with sandboxing headers', function () {
    $attachment = captureWithPdf('PDFBYTES')->attachments()->sole();

    $this->get($attachment->url())
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', 'attachment; filename=report.pdf')
        ->assertSee('PDFBYTES');
});

test('images render inline unless a download is requested', function () {
    $email = Email::create(['from' => 'a@x.test']);
    $image = $email->attachments()->create(['name' => 'ünï.png', 'content_type' => 'image/png', 'size' => 3, 'content' => 'PNG']);

    $inline = $this->get($image->url())->assertOk();
    expect($inline->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->and($inline->headers->get('Content-Security-Policy'))->toContain('sandbox');

    expect($this->get($image->url().'?download=1')->headers->get('Content-Disposition'))
        ->toStartWith('attachment;')
        ->toContain("filename*=utf-8''%C3%BCn%C3%AF.png");
});

test('the inbox list query skips the heavy columns', function () {
    captureWithPdf('PDF');

    DB::enableQueryLog();
    Livewire\Livewire::test(ListEmails::class)->assertSee('Report');
    $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'from "emails"') && str_contains($q, 'order by "received_at"'));

    expect($queries)->not->toBeEmpty();

    foreach ($queries as $query) {
        expect($query)
            ->toStartWith('select "id", "from", "to", "subject", "received_at", "is_read"')
            ->not->toContain('"raw"')
            ->not->toContain('"body_html"');
    }
});

<?php

use App\Filament\Resources\Emails\Pages\ListEmails;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\Setting;
use App\Services\SmtpCatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function emailReceived(string $subject, string $ago): Email
{
    return Email::create(['from' => 'a@x.test', 'subject' => $subject, 'received_at' => now()->sub($ago)]);
}

test('nothing is pruned when retention is off', function () {
    emailReceived('ancient', '5 years');

    expect(Email::pruneNow())->toBe(0)
        ->and(Email::count())->toBe(1);
});

test('emails older than retention_days are pruned with their attachments', function () {
    $old = emailReceived('old', '10 days');
    $old->attachments()->create(['name' => 'a.txt', 'content_type' => 'text/plain', 'size' => 1, 'content' => 'x']);
    emailReceived('recent', '1 day');
    Setting::set('retention_days', 7);

    expect(Email::pruneNow())->toBe(1)
        ->and(Email::pluck('subject')->all())->toBe(['recent'])
        ->and(EmailAttachment::count())->toBe(0);
});

test('only the newest retention_max_emails are kept', function () {
    foreach (range(1, 5) as $i) {
        emailReceived("mail {$i}", (10 - $i).' minutes');
    }
    Setting::set('retention_max_emails', 2);

    expect(Email::pruneNow())->toBe(3)
        ->and(Email::orderBy('received_at')->pluck('subject')->all())->toBe(['mail 4', 'mail 5']);
});

test('model:prune applies the retention settings', function () {
    emailReceived('old', '10 days');
    Setting::set('retention_days', 7);

    $this->artisan('model:prune', ['--model' => [Email::class]])->assertSuccessful();

    expect(Email::count())->toBe(0);
});

test('Delete all removes every email and attachment', function () {
    emailReceived('one', '1 minute')->attachments()->create(['name' => 'a', 'content_type' => 'text/plain', 'size' => 1, 'content' => 'x']);
    emailReceived('two', '2 minutes');

    Livewire::test(ListEmails::class)->callAction('deleteAll');

    expect(Email::count())->toBe(0)
        ->and(EmailAttachment::count())->toBe(0);
});

test('saving retention settings prunes immediately without restarting the catcher', function () {
    emailReceived('old', '30 days');
    emailReceived('new', '1 day');

    Livewire::test(ListEmails::class)
        ->callAction('settings', data: ['port' => SmtpCatcher::port(), 'retention_days' => 7]);

    expect(Setting::get('retention_days'))->toBe('7')
        ->and(Email::pluck('subject')->all())->toBe(['new']);
});

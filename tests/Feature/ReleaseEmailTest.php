<?php

use App\Filament\Resources\Emails\Pages\ViewEmail;
use App\Models\Email;
use App\Models\Setting;
use App\Services\ReleaseEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

uses(RefreshDatabase::class);

function recordingTransport(): TransportInterface
{
    return new class implements TransportInterface
    {
        public array $sent = [];

        public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
        {
            $this->sent[] = [$message->toString(), $envelope];

            return null;
        }

        public function __toString(): string
        {
            return 'recording://';
        }
    };
}

test('the relay password is stored encrypted and kept when left blank', function () {
    ReleaseEmail::saveSettings(['relay_host' => 'smtp.example.com', 'relay_password' => 's3cret!']);

    expect(Setting::get('relay_password'))->not->toContain('s3cret')
        ->and(Crypt::decryptString(Setting::get('relay_password')))->toBe('s3cret!');

    ReleaseEmail::saveSettings(['relay_host' => 'smtp.example.com', 'relay_password' => '']);
    expect(Crypt::decryptString(Setting::get('relay_password')))->toBe('s3cret!');

    ReleaseEmail::saveSettings(['relay_host' => '']);
    expect(Setting::get('relay_password'))->toBeNull()
        ->and(ReleaseEmail::isConfigured())->toBeFalse();
});

test('send uses the raw message and an envelope for the chosen recipients', function () {
    $email = Email::create(['from' => 'app@x.test', 'raw' => "From: app@x.test\r\nTo: user@x.test\r\nSubject: Hi\r\n\r\nBody"]);
    $transport = recordingTransport();

    ReleaseEmail::send($email, ['qa@team.test', 'lead@team.test'], $transport);

    [$raw, $envelope] = $transport->sent[0];
    expect($raw)->toBe($email->raw)
        ->and($envelope->getSender()->getAddress())->toBe('app@x.test')
        ->and(array_map(fn ($a) => $a->getAddress(), $envelope->getRecipients()))->toBe(['qa@team.test', 'lead@team.test']);
});

test('a configured envelope sender overrides the original', function () {
    ReleaseEmail::saveSettings(['relay_host' => 'smtp.example.com', 'relay_from' => 'verified@company.com']);
    $transport = recordingTransport();

    ReleaseEmail::send(Email::create(['from' => 'app@x.test', 'raw' => "Subject: x\r\n\r\ny"]), ['qa@team.test'], $transport);

    expect($transport->sent[0][1]->getSender()->getAddress())->toBe('verified@company.com');
});

test('recipient lists are split and validated', function () {
    expect(ReleaseEmail::parseRecipients("a@x.test, b@x.test;c@x.test\n a@x.test"))->toBe(['a@x.test', 'b@x.test', 'c@x.test'])
        ->and(ReleaseEmail::parseRecipients('not-an-email, b@x.test'))->toBe(['b@x.test']);
});

test('the transport is built from the saved settings', function (string $encryption, string $expected) {
    ReleaseEmail::saveSettings(['relay_host' => 'smtp.example.com', 'relay_port' => 2525, 'relay_encryption' => $encryption, 'relay_username' => 'u', 'relay_password' => 'p@ss:word/']);

    expect((string) ReleaseEmail::transport())->toBe($expected);
})->with([
    'starttls' => [ReleaseEmail::ENCRYPTION_STARTTLS, 'smtp://smtp.example.com:2525'],
    'implicit tls' => [ReleaseEmail::ENCRYPTION_TLS, 'smtps://smtp.example.com:2525'],
    'none' => [ReleaseEmail::ENCRYPTION_NONE, 'smtp://smtp.example.com:2525'],
]);

test('the Release form rejects invalid recipients', function () {
    ReleaseEmail::saveSettings(['relay_host' => 'smtp.example.com']);
    $email = Email::create(['from' => 'a@x.test', 'raw' => "Subject: x\r\n\r\ny"]);

    Livewire::test(ViewEmail::class, ['record' => $email->id])
        ->callAction('release', data: ['recipients' => 'qa@team.test, nope'])
        ->assertHasActionErrors(['recipients']);
});

test('a failed release is reported instead of crashing', function () {
    // Nothing listens on port 1, so the connection is refused
    ReleaseEmail::saveSettings(['relay_host' => '127.0.0.1', 'relay_port' => 1, 'relay_encryption' => ReleaseEmail::ENCRYPTION_NONE]);
    $email = Email::create(['from' => 'a@x.test', 'raw' => "Subject: x\r\n\r\ny"]);

    Livewire::test(ViewEmail::class, ['record' => $email->id])
        ->callAction('release', data: ['recipients' => 'qa@team.test'])
        ->assertHasNoActionErrors()
        ->assertNotified('Release failed');
});

test('without a relay the Release form cannot send', function () {
    $email = Email::create(['from' => 'a@x.test', 'raw' => "Subject: x\r\n\r\ny"]);

    Livewire::test(ViewEmail::class, ['record' => $email->id])
        ->callAction('release', data: ['recipients' => 'qa@team.test'])
        ->assertNotified('No release relay configured');
});

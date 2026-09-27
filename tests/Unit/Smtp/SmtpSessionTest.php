<?php

use App\Services\Smtp\SmtpSession;

/**
 * Drive a session with the given client lines; returns the replies and
 * any captured messages as [raw, from, recipients].
 */
function runSession(array $lines): array
{
    $replies = [];
    $messages = [];

    $session = new SmtpSession(
        send: function (string $reply) use (&$replies) {
            $replies[] = $reply;
        },
        onMessage: function (string $raw, ?string $from, array $recipients) use (&$messages) {
            $messages[] = [$raw, $from, $recipients];
        },
        close: fn () => null,
    );

    $session->greet();
    $session->feed(implode("\r\n", $lines) . "\r\n");

    return [$replies, $messages];
}

test('EHLO advertises AUTH and other extensions as a multi-line reply', function () {
    [$replies] = runSession(['EHLO client.test']);

    expect($replies[1])->toBe("250-localhost\r\n250-AUTH PLAIN LOGIN\r\n250-8BITMIME\r\n250 SMTPUTF8\r\n");
});

test('HELO keeps the single-line reply', function () {
    [$replies] = runSession(['HELO client.test']);

    expect($replies[1])->toBe("250 localhost\r\n");
});

test('AUTH PLAIN with an initial response is accepted', function () {
    [$replies] = runSession(['EHLO x', 'AUTH PLAIN ' . base64_encode("\0user\0secret")]);

    expect($replies[2])->toStartWith('235 ');
});

test('AUTH PLAIN without an initial response prompts for credentials', function () {
    [$replies] = runSession(['EHLO x', 'AUTH PLAIN', base64_encode("\0user\0secret")]);

    expect($replies[2])->toBe("334 \r\n")
        ->and($replies[3])->toStartWith('235 ');
});

test('AUTH LOGIN walks through username and password prompts', function () {
    [$replies] = runSession(['EHLO x', 'AUTH LOGIN', base64_encode('user'), base64_encode('secret')]);

    expect($replies[2])->toBe("334 VXNlcm5hbWU6\r\n")
        ->and($replies[3])->toBe("334 UGFzc3dvcmQ6\r\n")
        ->and($replies[4])->toStartWith('235 ');
});

test('AUTH LOGIN with the username inline skips straight to the password', function () {
    [$replies] = runSession(['EHLO x', 'AUTH LOGIN ' . base64_encode('user'), base64_encode('secret')]);

    expect($replies[2])->toBe("334 UGFzc3dvcmQ6\r\n")
        ->and($replies[3])->toStartWith('235 ');
});

test('AUTH can be cancelled with *', function () {
    [$replies] = runSession(['EHLO x', 'AUTH LOGIN', '*', 'NOOP']);

    expect($replies[3])->toStartWith('501 ')
        ->and($replies[4])->toBe("250 OK\r\n");
});

test('unknown AUTH mechanisms are rejected', function () {
    [$replies] = runSession(['EHLO x', 'AUTH CRAM-MD5']);

    expect($replies[2])->toStartWith('504 ');
});

test('AUTH before EHLO is rejected', function () {
    [$replies] = runSession(['AUTH PLAIN abc']);

    expect($replies[1])->toStartWith('503 ');
});

test('an authenticated session delivers mail with every envelope recipient', function () {
    [, $messages] = runSession([
        'EHLO x',
        'AUTH PLAIN ' . base64_encode("\0user\0secret"),
        'MAIL FROM:<sender@x.test> SIZE=120 BODY=8BITMIME',
        'RCPT TO:<to@x.test>',
        'RCPT TO:<hidden@x.test>',
        'DATA',
        'Subject: Hi',
        '',
        'Body',
        '.',
    ]);

    expect($messages)->toHaveCount(1)
        ->and($messages[0][1])->toBe('sender@x.test')
        ->and($messages[0][2])->toBe(['to@x.test', 'hidden@x.test']);
});

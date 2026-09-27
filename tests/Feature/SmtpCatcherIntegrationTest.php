<?php

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Process\Process;

/*
 * Runs the real `php artisan smtp:start` process on a free port with its
 * own SQLite file, and talks to it over a socket with Symfony Mailer —
 * the same client Laravel apps use — to cover the full path from TCP to
 * the database.
 */

beforeEach(function () {
    $this->dbPath = tempnam(sys_get_temp_dir(), 'catcher').'.sqlite';
    touch($this->dbPath);

    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $this->port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);

    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $this->dbPath,
        'SMTP_CATCHER_PORT' => (string) $this->port,
        'SMTP_CATCHER_MAX_SIZE' => '100000',
    ];

    (new Process([PHP_BINARY, 'artisan', 'migrate', '--force'], base_path(), $env))->mustRun();

    $this->catcher = new Process([PHP_BINARY, 'artisan', 'smtp:start'], base_path(), $env);
    $this->catcher->start();

    $deadline = microtime(true) + 10;
    while (! ($conn = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.1))) {
        if (microtime(true) > $deadline || ! $this->catcher->isRunning()) {
            $this->fail('Catcher did not start: '.$this->catcher->getErrorOutput().$this->catcher->getOutput());
        }
        usleep(50_000);
    }
    fclose($conn);

    $this->db = new PDO('sqlite:'.$this->dbPath);
});

afterEach(function () {
    $this->catcher?->stop(1);
    $this->db = null;
    @unlink($this->dbPath);
});

function catcherTransport(int $port, bool $auth = true)
{
    return Transport::fromDsn($auth ? "smtp://user:secret@127.0.0.1:{$port}" : "smtp://127.0.0.1:{$port}");
}

test('an authenticated Symfony Mailer message is captured with cc, bcc and attachments', function () {
    $message = (new Email)
        ->from(new Address('app@x.test', 'App'))
        ->to(new Address('john@x.test', 'Doe, John'))
        ->cc('cc@x.test')
        ->bcc('hidden@x.test')
        ->subject('Integration ✓')
        ->text('Plain body')
        ->html('<p>HTML body</p>')
        ->attach('%PDF-1.4', 'report.pdf', 'application/pdf');

    catcherTransport($this->port)->send($message);

    $email = $this->db->query('select * from emails')->fetch(PDO::FETCH_ASSOC);
    expect($email)->toMatchArray([
        'from' => 'app@x.test',
        'to' => 'john@x.test',
        'cc' => 'cc@x.test',
        'bcc' => 'hidden@x.test',
        'subject' => 'Integration ✓',
        'body_html' => '<p>HTML body</p>',
    ]);

    $attachment = $this->db->query('select name, content from email_attachments')->fetch(PDO::FETCH_ASSOC);
    expect($attachment)->toBe(['name' => 'report.pdf', 'content' => '%PDF-1.4']);
});

test('fixture messages survive a real SMTP round trip, dot-stuffing included', function () {
    $raw = file_get_contents(base_path('tests/Fixtures/emails/apple-nested-multipart.eml'))
        .".leading dot line\r\n";

    catcherTransport($this->port, auth: false)->send(
        new RawMessage($raw),
        new Envelope(new Address('sam@mac.test'), [new Address('team@x.test')])
    );

    $stored = $this->db->query('select raw from emails')->fetchColumn();
    $sent = str_replace(["\r\n", "\r"], "\n", rtrim($raw, "\r\n"));
    expect(str_replace("\r\n", "\n", $stored))->toBe($sent)
        ->and($this->db->query('select count(*) from email_attachments')->fetchColumn())->toBe(2);
});

test('messages over SMTP_CATCHER_MAX_SIZE are refused and not stored', function () {
    $message = (new Email)->from('a@x.test')->to('b@x.test')->subject('huge')->text(str_repeat('x', 200_000));

    expect(fn () => catcherTransport($this->port)->send($message))
        ->toThrow(UnexpectedResponseException::class, '552');

    expect($this->db->query('select count(*) from emails')->fetchColumn())->toBe(0);
});

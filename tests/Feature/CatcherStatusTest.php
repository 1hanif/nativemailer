<?php

use App\Filament\Resources\Emails\Pages\ListEmails;
use App\Models\Setting;
use App\Services\CatcherStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

function freePort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);

    return $port;
}

/** A throwaway TCP server in its own process that greets every client */
function fakeServer(int $port, string $greeting): Process
{
    $code = sprintf(
        '$s = stream_socket_server("tcp://127.0.0.1:%d"); while ($c = @stream_socket_accept($s, 10)) { fwrite($c, %s); fclose($c); }',
        $port,
        var_export($greeting."\r\n", true)
    );
    $process = new Process([PHP_BINARY, '-r', $code]);
    $process->start();

    $deadline = microtime(true) + 5;
    while (! CatcherStatus::portInUse($port) && microtime(true) < $deadline) {
        usleep(20_000);
    }

    return $process;
}

test('our catcher is recognised by its greeting', function () {
    $port = freePort();
    $server = fakeServer($port, '220 localhost SMTP Catcher Ready');

    expect(CatcherStatus::check($port))->toMatchArray(['state' => CatcherStatus::RUNNING, 'port' => $port]);

    $server->stop(0);
});

test('another program on the port is reported as taken', function () {
    $port = freePort();
    $server = fakeServer($port, '220 mail.example ESMTP Postfix');

    expect(CatcherStatus::check($port))
        ->state->toBe(CatcherStatus::PORT_TAKEN)
        ->message->toContain("port {$port}");

    $server->stop(0);
});

test('a recorded start-up failure explains why the catcher is down', function () {
    $port = freePort();
    CatcherStatus::recordStartFailure('Socket bind failed: Address already in use');

    expect(CatcherStatus::check($port))
        ->state->toBe(CatcherStatus::DOWN)
        ->message->toBe('Not running: Socket bind failed: Address already in use');

    CatcherStatus::clearStartFailure();
    expect(CatcherStatus::check($port)['message'])->toStartWith('Nothing is listening');
});

test('the inbox shows the catcher status under the title', function () {
    config(['mail.catcher.port' => freePort()]);

    Livewire::test(ListEmails::class)
        ->assertSeeHtml('data-state="down"')
        ->assertSee('Nothing is listening');
});

test('smtp:start records why it failed to bind', function () {
    $port = freePort();
    $blocker = stream_socket_server("tcp://127.0.0.1:{$port}");
    config(['mail.catcher.port' => $port]);

    $this->artisan('smtp:start')->assertFailed();

    expect(Setting::get('catcher_error'))->toContain('bind');

    fclose($blocker);
});

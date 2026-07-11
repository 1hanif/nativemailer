<?php

namespace App\Services\Smtp;

use Exception;

/**
 * Non-blocking socket server. Owns the listen socket, the select loop
 * and client lifecycles (accept, read, idle timeout, close). The SMTP
 * protocol itself lives in SmtpSession.
 */
class SmtpServer
{
    private $socket;

    /** @var array<int, array{socket: resource|\Socket, session: SmtpSession, last_activity: int}> */
    private array $clients = [];

    private int $clientIdCounter = 0;

    public function __construct(
        private string $host,
        private int $port,
        private int $timeout,
        /** @var callable(string $raw, ?string $from, array $recipients): void */
        private $onMessage,
    ) {}

    public function start(): void
    {
        $this->listen();

        // NOTE: never write to stdout here. Child-process stdout is piped to
        // Electron's console.log; if the launching terminal is gone, that
        // write throws EPIPE and crashes the whole app.

        while (true) {
            $read = [$this->socket, ...array_column($this->clients, 'socket')];
            $write = $except = [];

            if (socket_select($read, $write, $except, 0, 100000) > 0) {
                if (in_array($this->socket, $read, true)) {
                    $this->acceptClient();
                    $key = array_search($this->socket, $read, true);
                    if ($key !== false) {
                        unset($read[$key]);
                    }
                }

                foreach ($read as $sock) {
                    $this->readClient($sock);
                }
            }

            $this->reapIdleClients();

            usleep(10000);
        }
    }

    private function listen(): void
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            throw new Exception('Socket creation failed: ' . socket_strerror(socket_last_error()));
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_nonblock($this->socket);

        if (!socket_bind($this->socket, $this->host, $this->port)) {
            throw new Exception('Socket bind failed: ' . socket_strerror(socket_last_error()));
        }

        if (!socket_listen($this->socket, 5)) {
            throw new Exception('Socket listen failed: ' . socket_strerror(socket_last_error()));
        }
    }

    private function acceptClient(): void
    {
        $sock = socket_accept($this->socket);
        if ($sock === false) {
            return;
        }

        socket_set_nonblock($sock);
        $clientId = ++$this->clientIdCounter;

        $session = new SmtpSession(
            send: function (string $message) use ($sock) {
                @socket_write($sock, $message);
            },
            onMessage: $this->onMessage,
            close: function () use ($clientId) {
                $this->closeClient($clientId);
            },
        );

        $this->clients[$clientId] = [
            'socket' => $sock,
            'session' => $session,
            'last_activity' => time(),
        ];

        $session->greet();
    }

    private function readClient($sock): void
    {
        $clientId = $this->findClientIdBySocket($sock);
        if ($clientId === null) {
            return;
        }

        $data = @socket_read($sock, 8192);
        if ($data === false || $data === '') {
            $this->closeClient($clientId);

            return;
        }

        $this->clients[$clientId]['last_activity'] = time();
        $this->clients[$clientId]['session']->feed($data);
    }

    private function reapIdleClients(): void
    {
        $now = time();
        foreach ($this->clients as $clientId => $client) {
            if ($now - $client['last_activity'] > $this->timeout) {
                @socket_write($client['socket'], "421 4.4.2 Idle timeout, closing connection\r\n");
                $this->closeClient($clientId);
            }
        }
    }

    private function closeClient(int $clientId): void
    {
        if (isset($this->clients[$clientId])) {
            @socket_close($this->clients[$clientId]['socket']);
            unset($this->clients[$clientId]);
        }
    }

    private function findClientIdBySocket($socket): ?int
    {
        foreach ($this->clients as $id => $client) {
            if ($client['socket'] === $socket) {
                return $id;
            }
        }

        return null;
    }

    public function __destruct()
    {
        foreach ($this->clients as $client) {
            @socket_close($client['socket']);
        }
        if ($this->socket !== null && $this->socket !== false) {
            @socket_close($this->socket);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Http\Controllers\Trading\TradeController;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;
use WebSocket\Message\Text;
use WebSocket\Server;

class DashboardWebsocket extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'dashboard:ws {--port=} {--interval=}';

    /**
     * The console command description.
     */
    protected $description = 'Stream dashboard snapshots over WebSocket using CCXT snapshot builder';

    public function handle(): int
    {
        $port = (int) ($this->option('port') ?? config('trading.websocket.port', 6001));
        $interval = (float) ($this->option('interval') ?? config('trading.websocket.interval', 5));

        $this->info("Starting dashboard websocket on ws://0.0.0.0:{$port} (interval {$interval}s)");

        $controller = app(TradeController::class);
        $server = new Server($port);

        $nextPushAt = microtime(true);

        $sendSnapshot = function () use ($server, $controller) {
            try {
                $snapshot = $controller->getCachedDashboardSnapshot(); // uses short cache for speed
                if (!empty($snapshot)) {
                    $message = [
                        'type' => 'snapshot',
                        'data' => $snapshot,
                        'ts' => now()->toIso8601String(),
                    ];
                    $server->send(new Text(json_encode($message)));
                }
            } catch (Throwable $e) {
                Log::error('dashboard:ws failed to build snapshot', ['exception' => $e]);
            }
        };

        $server->onHandshake(function (Server $server, $connection, $request, $response) {
            // Accept all connections; path is not restricted.
            Log::info('dashboard:ws handshake', ['remote' => $connection->getRemoteName()]);
        });

        $server->onText(function (Server $server, $connection, Text $message) use ($sendSnapshot) {
            $payload = json_decode((string) $message, true);
            $type = Arr::get($payload, 'type');
            if ($type === 'ping') {
                $connection->send(new Text(json_encode(['type' => 'pong'])));
                return;
            }
            if ($type === 'snapshot') {
                $sendSnapshot();
            }
        });

        $server->onError(function (Server $server, $connection, $exception) {
            $remote = $connection?->getRemoteName();
            Log::warning('dashboard:ws error', ['remote' => $remote, 'exception' => $exception]);
        });

        $server->onDisconnect(function (Server $server, $connection) {
            Log::info('dashboard:ws disconnect', ['remote' => $connection->getRemoteName()]);
        });

        $server->onTick(function (Server $server) use (&$nextPushAt, $interval, $sendSnapshot) {
            $now = microtime(true);
            if ($server->getConnectionCount() === 0) {
                $nextPushAt = $now + $interval; // reset timer until a client connects
                return;
            }
            if ($now >= $nextPushAt) {
                $sendSnapshot();
                $nextPushAt = $now + $interval;
            }
        });

        $server->start();

        return self::SUCCESS;
    }
}

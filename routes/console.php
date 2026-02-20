<?php

use App\Console\Commands\DashboardWebsocket;
use App\Console\Commands\RunLorentzian;
use App\Console\Commands\RunTrade;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('run:trade')
    ->everyMinute();

// Proxy definitions to register class-based commands without a Kernel class
Artisan::command('dashboard:ws {--port=} {--interval=}', function () {
    return $this->call(DashboardWebsocket::class, [
        '--port' => $this->option('port'),
        '--interval' => $this->option('interval'),
    ]);
})->purpose('Stream dashboard snapshots over WebSocket');

Artisan::command('run:trade', function () {
    return $this->call(RunTrade::class);
});

Artisan::command('run:lorentzian', function () {
    return $this->call(RunLorentzian::class);
});

<?php

namespace App\Plugins\LorentzianClassification;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ScriptsRunner
{
    /**
     * The filesystem disk for storing output and PID files.
     */
    private Filesystem $storage;

    /**
     * Configuration values.
     */
    private array $config = [
        'pid_filename'       => 'lorentzian.pid',
        'db_filename'        => 'results.db',
        'log_filename'      => 'lorentzian.log',
        'venv_path'          => 'Plugins/LorentzianClassification/advanced-ta/venv/Scripts/python.exe',
        'lenv_path'          => 'Plugins/LorentzianClassification/advanced-ta/lenv/bin/activate',
        'script_path'        => 'Plugins/LorentzianClassification/advanced-ta/app/controller/lorentzian.py',
    ];

    public function __construct()
    {
        $this->storage = Storage::disk('lorentzian_data');
    }

    /**
     * Run the Python script and stream output live.
     */
    public function run(string $symbol, string $interval, int $limit): void
    {
        $command = $this->buildCommand($symbol, $interval, $limit);

        $process = Process::forever()
            ->start($command, function ($type, $output) {
                $line = trim($output);

                // Color based on keywords
                if (str_contains($line, 'Error') || str_contains($line, 'Exception')) {
                    echo "\033[31m{$line}\033[0m\n"; // Red
                } elseif (str_contains($line, 'Received kline')) {
                    echo "\033[33m{$line}\033[0m\n"; // Yellow
                } elseif (str_contains($line, 'WebSocket connected')) {
                    echo "\033[32m{$line}\033[0m\n"; // Green
                } else {
                    echo $line . "\n";
                }

                flush();
            });

        // Wait for the process to finish
        $process->wait();
    }

    public function getDbPath(): string
    {
        return $this->storage->path($this->config['db_filename']);
    }

    private function buildCommand(string $symbol, string $interval, int $limit): array
    {
        $pythonExecutable = $this->isWindows()
            ? app_path($this->config['venv_path'])
            :  app_path($this->config['lenv_path']);

        return [
            $pythonExecutable,
            '-u',
            app_path($this->config['script_path']),
            $symbol,
            $interval,
            $this->getDbPath(),
        ];
    }

    private function isWindows(): bool
    {
        return str_starts_with(strtoupper(PHP_OS), 'WIN');
    }


    public function lorentzianTable()
    {
        // Dynamically set a SQLite connection
        config([
            'database.connections.sqlite_lorentzian' => [
                'driver' => 'sqlite',
                'database' => $this->getDbPath(),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        // Use the dynamically configured connection
        return \DB::connection('sqlite_lorentzian')->table('lorentzian_results');
    }
}

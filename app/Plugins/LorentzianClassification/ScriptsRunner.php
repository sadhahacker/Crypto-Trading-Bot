<?php

namespace App\Plugins\LorentzianClassification;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
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
        'output_csv_filename'=> 'results.csv',
        'venv_path'          => 'advanced-ta/.venv/Scripts/python.exe', // For Windows
        'script_path'        => 'advanced-ta/app/controller/lorentzian.py',
    ];

    public function __construct()
    {
        $this->storage = Storage::disk('lorentzian_data');
    }

    /**
     * Start the Python script as a background process.
     */
    public function run(string $symbol, string $interval, int $limit): array
    {
        if ($this->isRunning()) {
            return [
                'status' => 'already_running',
                'pid'    => (int) $this->storage->get($this->config['pid_filename']),
            ];
        }

        $command = $this->buildCommand($symbol, $interval, $limit);

        $process = Process::command($command)->start();

        if (!$process->running()) {
            throw new RuntimeException("Failed to start the Lorentzian Python process.");
        }

        $pid = $process->id();
        $this->storage->put($this->config['pid_filename'], $pid);

        return [
            'status'      => 'started',
            'pid'         => $pid,
            'db_path'     => $this->getDbPath(),
            'output_path' => $this->getOutputPath(),
        ];
    }

    public function stop(): array
    {
        $pidFilename = $this->config['pid_filename'];

        if (!$this->storage->exists($pidFilename)) {
            return ['status' => 'not_running'];
        }

        $pid = (int) $this->storage->get($pidFilename);

        if ($pid > 0) {
            $this->killProcess($pid);
        }

        $this->storage->delete($pidFilename);

        return ['status' => 'stopped', 'pid' => $pid];
    }

    public function getStatus(): array
    {
        if ($this->isRunning()) {
            return [
                'status' => 'running',
                'pid'    => (int) $this->storage->get($this->config['pid_filename']),
            ];
        }

        return ['status' => 'stopped'];
    }

    public function isRunning(): bool
    {
        $pidFilename = $this->config['pid_filename'];

        if (!$this->storage->exists($pidFilename)) {
            return false;
        }

        $pid = (int) $this->storage->get($pidFilename);

        if ($pid <= 0) {
            return false;
        }

        $result = $this->isWindows()
            ? Process::run("tasklist /FI \"PID eq {$pid}\"")
            : Process::run("ps -p {$pid}");

        if (!$result->successful()) {
            $this->storage->delete($pidFilename);
            return false;
        }

        return true;
    }

    public function getDbPath(): string
    {
        return $this->storage->path($this->config['db_filename']);
    }

    public function getOutputPath(): string
    {
        return $this->storage->path($this->config['output_csv_filename']);
    }

    private function buildCommand(string $symbol, string $interval, int $limit): array
    {
        $pythonExecutable = $this->isWindows()
            ? base_path($this->config['venv_path'])
            : 'python3';

        return [
            $pythonExecutable,
            base_path($this->config['script_path']),
            $symbol,
            $interval,
            $limit,
            $this->getOutputPath(),
        ];
    }

    private function killProcess(int $pid): void
    {
        $command = $this->isWindows()
            ? "taskkill /F /PID {$pid}"
            : "kill {$pid}";

        Process::run($command);
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Common\DataFrame;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategies\EmaRsiVolumeStrategy;
use App\Http\Controllers\Trading\TradeController;
use App\Models\BacktestData;
use App\Models\ExchangeSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class BacktestController extends Controller
{
    /**
     * Backtest module entry page: only strategy listing.
     */
    public function index()
    {
        $strategyCatalog = array_values($this->getStrategyCatalog());

        return view('Settings.backtest', compact('strategyCatalog'));
    }

    /**
     * Backward compatibility route for old "signals/tester" URL.
     */
    public function legacySignalTester()
    {
        return redirect()->to(url('backtest'));
    }

    /**
     * Historical data module: table only.
     */
    public function historicalData()
    {
        return view('Settings.historicalData');
    }

    /**
     * Strategy detail page for running backtests.
     */
    public function strategy(string $strategy)
    {
        $strategyCatalog = $this->getStrategyCatalog();

        if (!isset($strategyCatalog[$strategy])) {
            abort(404, 'Strategy not found.');
        }

        $selectedStrategy = $strategyCatalog[$strategy];

        $datasets = BacktestData::query()
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn (BacktestData $dataset) => $this->mapDataset($dataset))
            ->values()
            ->all();

        $exchangeDefaults = $this->buildExchangeBacktestDefaults();
        $exchangeConfig = $this->getExchangeConfigSnapshot();

        return view('Settings.backtestStrategy', compact(
            'selectedStrategy',
            'datasets',
            'exchangeDefaults',
            'exchangeConfig'
        ));
    }

    public function getGeneratedFiles()
    {
        $query = BacktestData::query()->orderByDesc('created_at');

        return DataTables::of($query)
            ->addColumn('size_kb', fn ($row) => round(((float) $row->file_size) / 1024, 2) . ' KB')
            ->addColumn('action', function ($row) {
                $downloadUrl = url('backtest/files/' . $row->id . '/download');

                return '<a href="' . $downloadUrl . '" class="btn btn-sm btn-primary" target="_blank">Download</a>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function listDatasets()
    {
        $datasets = BacktestData::query()
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn (BacktestData $dataset) => $this->mapDataset($dataset))
            ->values()
            ->all();

        return successResponse('', $datasets);
    }

    public function generateBackTestData(Request $request)
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:30'],
            'timeframe' => ['required', 'string', 'max:10'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        try {
            $file = $this->generateBacktestDataRange(
                strtoupper((string) $validated['symbol']),
                (string) $validated['timeframe'],
                (string) $validated['from'],
                (string) $validated['to']
            );

            return successResponse('Backtest data generated successfully.', [
                'id' => $file->id,
                'file_name' => $file->file_name,
                'file_size' => $file->file_size,
                'dataset' => $this->mapDataset($file),
            ]);
        } catch (\Throwable $e) {
            return errorResponse('Failed to generate backtest data: ' . $e->getMessage());
        }
    }

    public function downloadGeneratedFile(BacktestData $backtestData)
    {
        $absolutePath = $this->resolveBacktestFileAbsolutePath($backtestData);

        if ($absolutePath === null || !File::exists($absolutePath)) {
            return errorResponse('Backtest file not found.', 404);
        }

        return response()->download($absolutePath, $backtestData->file_name);
    }

    /**
     * Run advanced strategy backtest against a generated CSV file.
     */
    public function runBacktest(Request $request)
    {
        $exchangeDefaults = $this->buildExchangeBacktestDefaults();
        $strategyCatalog = $this->getStrategyCatalog();

        $validated = $request->validate([
            'strategy' => ['required', 'string'],
            'backtest_data_id' => ['nullable', 'integer', 'exists:backtest_data,id'],
            'initial_balance' => ['nullable', 'numeric', 'min:1'],
            'take_profit_percent' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'stop_loss_percent' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'risk_per_trade_percent' => ['nullable', 'numeric', 'min:0.1', 'max:30'],
            'fee_rate' => ['nullable', 'numeric', 'min:0', 'max:0.02'],
            'slippage_rate' => ['nullable', 'numeric', 'min:0', 'max:0.02'],
            'leverage' => ['nullable', 'numeric', 'min:1', 'max:125'],
            'allow_short' => ['nullable', 'boolean'],
            'max_open_bars' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        $strategyKey = (string) $validated['strategy'];
        if (!isset($strategyCatalog[$strategyKey])) {
            return errorResponse('Selected strategy is not available.');
        }

        $record = isset($validated['backtest_data_id'])
            ? BacktestData::query()->find($validated['backtest_data_id'])
            : BacktestData::query()->latest('created_at')->first();

        if (!$record) {
            return errorResponse('No backtest dataset found. Generate data first.', 404);
        }

        try {
            $rows = $this->readCandlesFromFile($record);
            if (count($rows) < 30) {
                return errorResponse('Not enough candles to run backtest. Minimum 30 required.');
            }

            $dataFrame = new DataFrame($rows);
            $strategyClass = $strategyCatalog[$strategyKey]['class'];
            $strategy = app($strategyClass);

            if (!method_exists($strategy, 'runBacktest')) {
                return errorResponse('Selected strategy does not support backtesting.');
            }

            $riskPercent = (float) ($validated['risk_per_trade_percent'] ?? $exchangeDefaults['risk_per_trade_percent']);
            $riskPercent = max(min($riskPercent, 30.0), 0.1);

            $result = $strategy->runBacktest($dataFrame, [
                'initial_balance' => (float) ($validated['initial_balance'] ?? $exchangeDefaults['initial_balance']),
                'risk_per_trade' => $riskPercent / 100,
                'take_profit_percent' => (float) ($validated['take_profit_percent'] ?? $exchangeDefaults['take_profit_percent']),
                'stop_loss_percent' => (float) ($validated['stop_loss_percent'] ?? $exchangeDefaults['stop_loss_percent']),
                'fee_rate' => (float) ($validated['fee_rate'] ?? $exchangeDefaults['fee_rate']),
                'slippage_rate' => (float) ($validated['slippage_rate'] ?? $exchangeDefaults['slippage_rate']),
                'leverage' => (float) ($validated['leverage'] ?? $exchangeDefaults['leverage']),
                'allow_short' => array_key_exists('allow_short', $validated)
                    ? (bool) $validated['allow_short']
                    : (bool) $exchangeDefaults['allow_short'],
                'max_open_bars' => (int) ($validated['max_open_bars'] ?? $exchangeDefaults['max_open_bars']),
            ]);

            $result['dataset'] = $this->mapDataset($record);
            $result['strategy'] = [
                'key' => $strategyKey,
                'name' => $strategyCatalog[$strategyKey]['name'],
                'description' => $strategyCatalog[$strategyKey]['description'],
            ];

            return successResponse('Backtest completed successfully.', $result);
        } catch (\Throwable $e) {
            return errorResponse('Backtest failed: ' . $e->getMessage());
        }
    }

    /**
     * Backward-compatible alias.
     */
    public function testRunner(Request $request)
    {
        $catalog = $this->getStrategyCatalog();
        $defaultStrategy = array_key_first($catalog);

        if (!$request->filled('strategy') && $defaultStrategy !== null) {
            $request->merge(['strategy' => $defaultStrategy]);
        }

        return $this->runBacktest($request);
    }

    /**
     * Generate backtest CSV file for a symbol within a date range.
     */
    private function generateBacktestDataRange(
        string $symbol,
        string $timeframe = '1h',
        string $from = '2024-01-01',
        string $to = '2025-02-01'
    ): BacktestData {
        $since = Carbon::parse($from)->startOfDay()->getTimestampMs();
        $end = Carbon::parse($to)->endOfDay()->getTimestampMs();
        $limit = 1000;

        $tradeController = new TradeController();
        $allCandles = [];

        while ($since < $end) {
            $candles = $tradeController->getOHLCV($symbol, $timeframe, $since, $limit) ?? [];

            if (empty($candles)) {
                break;
            }

            foreach ($candles as $candle) {
                if (!is_array($candle) || count($candle) < 6) {
                    continue;
                }

                $timestamp = (int) $candle[0];
                if ($timestamp <= 0 || $timestamp > $end) {
                    continue;
                }

                $allCandles[(string) $timestamp] = [
                    $timestamp,
                    (float) $candle[1],
                    (float) $candle[2],
                    (float) $candle[3],
                    (float) $candle[4],
                    (float) $candle[5],
                ];
            }

            $last = end($candles);
            $lastTimestamp = is_array($last) && isset($last[0]) ? (int) $last[0] : 0;

            if ($lastTimestamp <= $since || $lastTimestamp >= $end) {
                break;
            }

            $since = $lastTimestamp + 1;
            usleep(500000);
        }

        if (empty($allCandles)) {
            throw new \RuntimeException('No candles fetched for selected period.');
        }

        ksort($allCandles, SORT_NUMERIC);

        $folder = storage_path('app/BackTestData');
        if (!File::exists($folder)) {
            File::makeDirectory($folder, 0775, true);
        }

        $fileName = "binance_{$symbol}_{$timeframe}_" . now()->format('Ymd_His') . '.csv';
        $absolutePath = $folder . DIRECTORY_SEPARATOR . $fileName;
        $relativePath = 'BackTestData/' . $fileName;

        $handle = fopen($absolutePath, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create backtest CSV file.');
        }

        foreach ($allCandles as $candle) {
            fputcsv($handle, $candle);
        }

        fclose($handle);

        return BacktestData::create([
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'from_date' => $from,
            'to_date' => $to,
            'file_path' => $relativePath,
            'file_name' => $fileName,
            'file_size' => File::size($absolutePath),
        ]);
    }

    /**
     * @return array<int, array{0:int,1:float,2:float,3:float,4:float,5:float}>
     */
    private function readCandlesFromFile(BacktestData $backtestData): array
    {
        $absolutePath = $this->resolveBacktestFileAbsolutePath($backtestData);
        if ($absolutePath === null || !File::exists($absolutePath)) {
            throw new \RuntimeException('Backtest file could not be located.');
        }

        $rows = [];

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open backtest file.');
        }

        while (($line = fgetcsv($handle)) !== false) {
            if (!is_array($line) || count($line) < 6) {
                continue;
            }

            if (!is_numeric($line[0])) {
                continue;
            }

            $timestamp = (int) $line[0];
            if ($timestamp <= 0) {
                continue;
            }

            $rows[(string) $timestamp] = [
                $timestamp,
                (float) $line[1],
                (float) $line[2],
                (float) $line[3],
                (float) $line[4],
                (float) $line[5],
            ];
        }

        fclose($handle);

        ksort($rows, SORT_NUMERIC);

        return array_values($rows);
    }

    private function resolveBacktestFileAbsolutePath(BacktestData $backtestData): ?string
    {
        $filePath = (string) ($backtestData->file_path ?? '');
        $fileName = (string) ($backtestData->file_name ?? '');

        $candidates = [];

        if ($filePath !== '') {
            if (str_starts_with($filePath, '/')) {
                $candidates[] = $filePath;
            }

            $candidates[] = storage_path('app/' . ltrim($filePath, '/'));
            $candidates[] = storage_path('app/BackTestData/' . basename($filePath));
        }

        if ($fileName !== '') {
            $candidates[] = storage_path('app/BackTestData/' . $fileName);
        }

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{key:string,name:string,class:string,description:string,config_fields:array<int,array<string,mixed>>}>
     */
    private function getStrategyCatalog(): array
    {
        static $catalog = null;

        if (is_array($catalog)) {
            return $catalog;
        }

        $catalog = [];
        $strategyDirectory = app_path('Http/Controllers/Strategies');

        if (File::isDirectory($strategyDirectory)) {
            foreach (File::files($strategyDirectory) as $file) {
                $classBase = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $class = 'App\\Http\\Controllers\\Strategies\\' . $classBase;

                if (!class_exists($class)) {
                    continue;
                }

                if (!method_exists($class, 'runBacktest')) {
                    continue;
                }

                $key = Str::snake($classBase);
                $catalog[$key] = [
                    'key' => $key,
                    'name' => Str::headline($classBase),
                    'class' => $class,
                    'description' => 'Run this strategy on selected historical dataset with configurable risk controls.',
                    'config_fields' => [],
                ];
            }
        }

        if (empty($catalog)) {
            $key = 'ema_rsi_volume_strategy';
            $catalog[$key] = [
                'key' => $key,
                'name' => 'EMA RSI Volume Strategy',
                'class' => EmaRsiVolumeStrategy::class,
                'description' => 'EMA trend + RSI momentum + volume confirmation strategy.',
                'config_fields' => [],
            ];
        }

        ksort($catalog);

        $defaults = $this->buildExchangeBacktestDefaults();
        foreach ($catalog as $key => $item) {
            $catalog[$key]['config_fields'] = $this->buildStrategyConfigFields($defaults);
        }

        return $catalog;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildStrategyConfigFields(array $defaults): array
    {
        return [
            [
                'name' => 'take_profit_percent',
                'label' => 'Take Profit (%)',
                'type' => 'number',
                'step' => '0.1',
                'min' => '0.1',
                'max' => '100',
                'default' => $defaults['take_profit_percent'],
                'required' => true,
            ],
            [
                'name' => 'stop_loss_percent',
                'label' => 'Stop Loss (%)',
                'type' => 'number',
                'step' => '0.1',
                'min' => '0.1',
                'max' => '100',
                'default' => $defaults['stop_loss_percent'],
                'required' => true,
            ],
            [
                'name' => 'initial_balance',
                'label' => 'Initial Balance',
                'type' => 'number',
                'step' => '0.01',
                'min' => '1',
                'max' => '100000000',
                'default' => $defaults['initial_balance'],
                'required' => true,
            ],
            [
                'name' => 'risk_per_trade_percent',
                'label' => 'Capital Use Per Trade (%)',
                'type' => 'number',
                'step' => '0.1',
                'min' => '0.1',
                'max' => '30',
                'default' => $defaults['risk_per_trade_percent'],
                'required' => true,
            ],
            [
                'name' => 'leverage',
                'label' => 'Leverage',
                'type' => 'number',
                'step' => '0.1',
                'min' => '1',
                'max' => '125',
                'default' => $defaults['leverage'],
                'required' => true,
            ],
            [
                'name' => 'fee_rate',
                'label' => 'Fee Rate (decimal)',
                'type' => 'number',
                'step' => '0.0001',
                'min' => '0',
                'max' => '0.02',
                'default' => $defaults['fee_rate'],
                'required' => true,
            ],
            [
                'name' => 'slippage_rate',
                'label' => 'Slippage Rate (decimal)',
                'type' => 'number',
                'step' => '0.0001',
                'min' => '0',
                'max' => '0.02',
                'default' => $defaults['slippage_rate'],
                'required' => true,
            ],
            [
                'name' => 'max_open_bars',
                'label' => 'Max Holding Bars',
                'type' => 'number',
                'step' => '1',
                'min' => '1',
                'max' => '5000',
                'default' => $defaults['max_open_bars'],
                'required' => true,
            ],
            [
                'name' => 'allow_short',
                'label' => 'Allow Short Trades',
                'type' => 'checkbox',
                'default' => (bool) $defaults['allow_short'],
                'required' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildExchangeBacktestDefaults(): array
    {
        $exchangeSetting = ExchangeSetting::query()->first();

        $stopLossCoin = (float) ($exchangeSetting?->stoploss_from_coin ?? config('trading.stoploss_from_coin', 0.03));
        $takeProfitCoin = (float) ($exchangeSetting?->takeprofit_from_coin ?? config('trading.takeprofit_from_coin', 0.023));

        if ($stopLossCoin <= 0) {
            $stopLossCoin = 0.03;
        }
        if ($takeProfitCoin <= 0) {
            $takeProfitCoin = 0.023;
        }

        $stopLossPercent = $stopLossCoin <= 1 ? $stopLossCoin * 100 : $stopLossCoin;
        $takeProfitPercent = $takeProfitCoin <= 1 ? $takeProfitCoin * 100 : $takeProfitCoin;

        $riskAccount = (float) ($exchangeSetting?->stoploss_from_account_balance ?? config('trading.stoploss_from_account_balance', 0.23));
        $riskPercent = $riskAccount <= 1 ? ($riskAccount * 100) : $riskAccount;
        if ($riskPercent <= 0) {
            $riskPercent = 2.0;
        }

        return [
            'initial_balance' => 1000.0,
            'take_profit_percent' => round($takeProfitPercent, 4),
            'stop_loss_percent' => round($stopLossPercent, 4),
            'risk_per_trade_percent' => round(min(max($riskPercent, 0.1), 30), 4),
            'leverage' => $this->calculateSuggestedLeverage($exchangeSetting),
            'fee_rate' => 0.0006,
            'slippage_rate' => 0.0004,
            'max_open_bars' => 72,
            'allow_short' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getExchangeConfigSnapshot(): array
    {
        $exchangeSetting = ExchangeSetting::query()->first();

        return [
            'exchange_name' => $exchangeSetting?->exchange_name ?? config('trading.exchange_name', 'binance'),
            'default_type' => $exchangeSetting?->default_type ?? (config('trading.options.defaultType', 'future')),
            'stoploss_from_account_balance' => (float) ($exchangeSetting?->stoploss_from_account_balance ?? config('trading.stoploss_from_account_balance', 0.23)),
            'takeprofit_from_account_balance' => (float) ($exchangeSetting?->takeprofit_from_account_balance ?? config('trading.takeprofit_from_account_balance', 0.30)),
            'stoploss_from_coin' => (float) ($exchangeSetting?->stoploss_from_coin ?? config('trading.stoploss_from_coin', 0.03)),
            'takeprofit_from_coin' => (float) ($exchangeSetting?->takeprofit_from_coin ?? config('trading.takeprofit_from_coin', 0.023)),
            'display_currency' => strtoupper((string) ($exchangeSetting?->display_currency ?? 'USD')),
        ];
    }

    private function calculateSuggestedLeverage(?ExchangeSetting $exchangeSetting): float
    {
        $stoplossFromAccount = (float) ($exchangeSetting?->stoploss_from_account_balance ?? config('trading.stoploss_from_account_balance', 0.23));
        $takeprofitFromAccount = (float) ($exchangeSetting?->takeprofit_from_account_balance ?? config('trading.takeprofit_from_account_balance', 0.30));
        $stoplossFromCoin = (float) ($exchangeSetting?->stoploss_from_coin ?? config('trading.stoploss_from_coin', 0.03));
        $takeprofitFromCoin = (float) ($exchangeSetting?->takeprofit_from_coin ?? config('trading.takeprofit_from_coin', 0.023));

        if ($stoplossFromCoin <= 0 || $takeprofitFromCoin <= 0) {
            return 1.0;
        }

        $leverageFromSl = $stoplossFromAccount / $stoplossFromCoin;
        $leverageFromTp = $takeprofitFromAccount / $takeprofitFromCoin;
        $leverage = max($leverageFromSl, $leverageFromTp);

        if (!is_finite($leverage) || $leverage <= 0) {
            return 1.0;
        }

        return round(min(max($leverage, 1.0), 125.0), 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDataset(BacktestData $dataset): array
    {
        return [
            'id' => $dataset->id,
            'symbol' => $dataset->symbol,
            'timeframe' => $dataset->timeframe,
            'from_date' => (string) $dataset->from_date,
            'to_date' => (string) $dataset->to_date,
            'file_name' => $dataset->file_name,
            'file_size' => (int) $dataset->file_size,
            'created_at' => (string) $dataset->created_at,
            'label' => sprintf(
                '%s | %s | %s -> %s | %s',
                $dataset->symbol,
                $dataset->timeframe,
                $dataset->from_date,
                $dataset->to_date,
                $dataset->file_name
            ),
        ];
    }
}

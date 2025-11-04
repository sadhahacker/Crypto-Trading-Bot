<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Common\DataFrame;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategies\EmaRsiVolumeStrategy;
use App\Http\Controllers\Trading\AccountSetupController;
use App\Http\Controllers\Trading\TradeController;
use App\Models\BacktestData;
use App\Models\BotConfiguration;
use App\Plugins\Lorentzian\src\Classifier\Classifier;
use Carbon\Carbon;
use File;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class BacktestController extends Controller
{
    public function index()
    {
        return view('Settings.backtest');
    }

    public function getGeneratedFiles()
    {
        $query = BacktestData::query()->orderByDesc('created_at');

        return DataTables::of($query)
            ->addColumn('size_kb', fn($row) => round($row->file_size / 1024, 2) . ' KB')
            ->addColumn('action', function ($row) {
                return '<a href="' . url('storage/' . $row->file_path) . '" target="_blank" class="btn btn-sm btn-primary">Download</a>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }


    public function generateBackTestData(Request $request)
    {
        $symbol = $request->input('symbol');
        $timeframe = $request->input('timeframe');
        $from = $request->input('from');
        $to = $request->input('to');

        $generatedData =  $this->generateBacktestDataRange($symbol, $timeframe, $from, $to);

        return $generatedData === 'Success' ?
            successResponse('Backtest data generated successfully.') :
            errorResponse('Failed to generate backtest data.');
    }


    /**
     * Generate backtest CSV file for a symbol within a date range.
     *
     * @param string $symbol Trading pair, e.g. "BNBUSDT"
     * @param string $timeframe Timeframe, e.g. "1h", "1d", "5m"
     * @param string $from Start date (Y-m-d)
     * @param string $to End date (Y-m-d)
     * @param string $exchangeId Exchange name (default: "binance")
     * @return string
     */
    private function generateBacktestDataRange($symbol, $timeframe = '1h', $from = '2024-01-01', $to = '2025-02-01')
    {
        $since = Carbon::parse($from)->startOfDay()->getTimestampMs();
        $end = Carbon::parse($to)->endOfDay()->getTimestampMs();
        $limit = 1000;

        $tradeController = new TradeController();
        $allCandles = [];

        while ($since < $end) {
            $candles = $tradeController->getOHLCV($symbol, $timeframe, $since, $limit);

            if (empty($candles)) {
                break;
            }

            $allCandles = array_merge($allCandles, $candles);

            $lastTimestamp = end($candles)[0];

            // Prevent infinite loops
            if ($lastTimestamp <= $since || $lastTimestamp >= $end) {
                break;
            }

            $since = $lastTimestamp + 1;
            usleep(500000); // avoid rate limit
        }

        $folder = storage_path('app/BackTestData');
        if (!File::exists($folder)) {
            File::makeDirectory($folder, 0775, true);
        }

        $fileName = "binance_{$symbol}_{$timeframe}_" . now()->format('Ymd_His') . ".csv";
        $filePath = "{$folder}/{$fileName}";
        $file = fopen($filePath, 'w');

        foreach ($allCandles as $candle) {
            fputcsv($file, $candle);
        }

        fclose($file);

        BacktestData::create([
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'from_date' => $from,
            'to_date' => $to,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => File::size($filePath),
        ]);

        return "Success";
    }


    public function testRunner()
    {
        $file = BacktestData::first();

        $contents = \Storage::disk('BackTestData')->get($file->file_name);

        $rows = array_map('str_getcsv', explode("\n", trim($contents)));

//        $rows = (new TradeController())->getOHLCV('BTCUSDT');

        $df = new DataFrame($rows);

        $data = (new EmaRsiVolumeStrategy())->generateSignals($df);

        return successResponse('', $data);
    }


    public function getSignalPoints($df, float $tpPercent = 3.0, float $slPercent = 1.0, float $initialBalance = 1000.0): array
    {
        $trades = [];
        $inTrade = false;
        $currentTrade = null;
        $balance = $initialBalance;

        $count = count($df['close']);

        for ($i = 0; $i < $count; $i++) {
            $timestamp = $df['timestamp'][$i] ?? null;
            $prediction = $df['prediction'];
            $price = $df['close'][$i];

            // 📈 BUY ENTRY
            if (!$inTrade && ($df['isNewBuySignal'][$i] ?? false) && $prediction >= 6) {
                $inTrade = true;
                $currentTrade = [
                    'type' => 'BUY',
                    'entry_price' => $price,
                    'entry_time' => Carbon::createFromTimestampMs($timestamp)->toDateTimeString(),
                    'tp' => $price * (1 + $tpPercent / 100),
                    'sl' => $price * (1 - $slPercent / 100),
                ];
            }

            // 📉 SELL ENTRY
            if (!$inTrade && ($df['isNewSellSignal'][$i] ?? false) && $prediction <= -6) {
                $inTrade = true;
                $currentTrade = [
                    'type' => 'SELL',
                    'entry_price' => $price,
                    'entry_time' => Carbon::createFromTimestampMs($timestamp)->toDateTimeString(),
                    'tp' => $price * (1 - $tpPercent / 100),
                    'sl' => $price * (1 + $slPercent / 100),
                ];
            }

            // ✅ Check for exit if in trade
            if ($inTrade && $currentTrade) {
                $hitTP = false;
                $hitSL = false;

                if ($currentTrade['type'] === 'BUY') {
                    $hitTP = $df['high'][$i] >= $currentTrade['tp'];
                    $hitSL = $df['low'][$i] <= $currentTrade['sl'];
                } else { // SELL
                    $hitTP = $df['low'][$i] <= $currentTrade['tp'];
                    $hitSL = $df['high'][$i] >= $currentTrade['sl'];
                }

                // 💰 If hit TP or SL, close the trade
                if ($hitTP || $hitSL) {
                    $exitPrice = $hitTP ? $currentTrade['tp'] : $currentTrade['sl'];
                    $exitTime = Carbon::createFromTimestampMs($timestamp)->toDateTimeString();
                    $pnl = $currentTrade['type'] === 'BUY'
                        ? round($exitPrice - $currentTrade['entry_price'], 2)
                        : round($currentTrade['entry_price'] - $exitPrice, 2);

                    $balance += $pnl;

                    $trades[] = [
                        ...$currentTrade,
                        'exit_price' => $exitPrice,
                        'exit_time' => $exitTime,
                        'pnl' => $pnl,
                        'status' => $hitTP ? 'TP Hit' : 'SL Hit',
                        'balance_after' => round($balance, 2),
                    ];

                    $inTrade = false;
                    $currentTrade = null;
                }

                // 🔁 Otherwise close if opposite signal appears
                elseif (
                    ($currentTrade['type'] === 'BUY' && ($df['isNewSellSignal'][$i] ?? false)) ||
                    ($currentTrade['type'] === 'SELL' && ($df['isNewBuySignal'][$i] ?? false))
                ) {
                    $exitPrice = $price;
                    $exitTime = Carbon::createFromTimestampMs($timestamp)->toDateTimeString();
                    $pnl = $currentTrade['type'] === 'BUY'
                        ? round($exitPrice - $currentTrade['entry_price'], 2)
                        : round($currentTrade['entry_price'] - $exitPrice, 2);

                    $balance += $pnl;

                    $trades[] = [
                        ...$currentTrade,
                        'exit_price' => $exitPrice,
                        'exit_time' => $exitTime,
                        'pnl' => $pnl,
                        'status' => 'Signal Exit',
                        'balance_after' => round($balance, 2),
                    ];

                    $inTrade = false;
                    $currentTrade = null;
                }
            }
        }

        // 🕓 Still open trade
        if ($inTrade && $currentTrade) {
            $currentTrade['exit_price'] = null;
            $currentTrade['exit_time'] = null;
            $currentTrade['pnl'] = null;
            $currentTrade['status'] = 'OPEN';
            $currentTrade['balance_after'] = round($balance, 2);
            $trades[] = $currentTrade;
        }

        // 📊 Compute statistics
        $closedTrades = array_filter($trades, fn($t) => isset($t['pnl']) && is_numeric($t['pnl']));
        $profits = array_sum(array_column($closedTrades, 'pnl'));
        $wins = count(array_filter($closedTrades, fn($t) => $t['pnl'] > 0));
        $losses = count(array_filter($closedTrades, fn($t) => $t['pnl'] < 0));
        $totalTrades = count($closedTrades);

        $stats = [
            'initial_balance' => $initialBalance,
            'final_balance' => round($balance, 2),
            'total_trades' => $totalTrades,
            'winning_trades' => $wins,
            'losing_trades' => $losses,
            'win_rate' => $totalTrades > 0 ? round(($wins / $totalTrades) * 100, 2) : 0,
            'net_profit' => round($profits, 2),
            'avg_profit_per_trade' => $totalTrades > 0 ? round($profits / $totalTrades, 2) : 0,
            'max_profit' => $totalTrades > 0 ? max(array_column($closedTrades, 'pnl')) : 0,
            'max_loss' => $totalTrades > 0 ? min(array_column($closedTrades, 'pnl')) : 0,
            'profit_factor' => $losses > 0
                ? round(abs(array_sum(array_filter(array_column($closedTrades, 'pnl'), fn($p) => $p > 0))) /
                    abs(array_sum(array_filter(array_column($closedTrades, 'pnl'), fn($p) => $p < 0))), 2)
                : 0,
        ];

        return [
            'trades' => array_values($trades),
            'stats' => $stats,
        ];
    }


}

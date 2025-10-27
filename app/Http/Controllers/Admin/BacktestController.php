<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Common\DataFrame;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategies\EmaRsiVolumeStrategy;
use App\Http\Controllers\Trading\TradeController;
use App\Models\BotConfiguration;
use Illuminate\Http\Request;

class BacktestController extends Controller
{
    public function index()
    {
        return view('Settings.backtest');
    }

    public function runTesting()
    {
        $symbol = BotConfiguration::getValue('DEFAULT_SYMBOL');

        // Fetch OHLCV candles
        $candles = (new TradeController())->getOHLCV($symbol, '1h', null, 1000);
        $df = new DataFrame($candles);

        $strategy = new EmaRsiVolumeStrategy();
        $results = [];

        // Take only the last 100 candles
        $last100 = array_slice($candles, -100);

        // Loop through each candle progressively
        foreach ($last100 as $i => $candle) {
            // Simulate real-time data (0 → i)
            $subset = array_slice($last100, 0, $i + 1);
            $subDf = new DataFrame($subset);

            // Check for signal
            $signal = $strategy->checkForSignal($subDf);

            if ($signal) {
                $results[] = [
                    'index' => $i,
                    'time' => $candle['timestamp'] ?? null,
                    'signal' => $signal['signal'],
                    'entry' => $signal['entry'],
                ];
            }
        }

        // Show results (last 100)
        dd($results);
    }

}

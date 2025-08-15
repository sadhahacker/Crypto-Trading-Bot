<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Plugins\LorentzianClassification\ScriptsRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IndicatorController extends Controller{

    private ScriptsRunner $scriptManager;

    // Global constants for symbol, interval, and limit
    private const DEFAULT_SYMBOL   = 'BTCUSDT';
    private const DEFAULT_INTERVAL = '1h';
    private const DEFAULT_LIMIT    = 1000;
    private const PREDICTION_THRESHOLD = 6;

    public function __construct(ScriptsRunner $scriptManager)
    {
        $this->scriptManager = $scriptManager;
    }

    public function start(Request $request): JsonResponse
    {
        try {
            $result = $this->scriptManager->run(self::DEFAULT_SYMBOL,self::DEFAULT_INTERVAL, self::DEFAULT_LIMIT);
            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function stop(): JsonResponse
    {
        $result = $this->scriptManager->stop();
        return response()->json($result);
    }

    public function status(): JsonResponse
    {
        $result = $this->scriptManager->getStatus();
        return response()->json($result);
    }


    public function tradeStater()
    {
        $latest = $this->scriptManager->lorentzianTable()
            ->orderBy('timestamp', 'desc')
            ->first();

        if (!$latest) return;

        $last = \Cache::get('last_lorentzian_timestamp');

        if (!$last || $latest->timestamp != $last) {

            $signal = $this->lorentzianUpdated($latest);

            if ($signal['status'] !== 'no_signal') {

                (new ExecuteTradeController())->executeTrade(
                    self::DEFAULT_SYMBOL,
                    $signal['entry'],
                    $signal['status']
                );

                \Cache::put('last_lorentzian_timestamp', $latest->timestamp);
            }
        }
    }

    public function lorentzianUpdated($data, $predictionThreshold = self::PREDICTION_THRESHOLD)
    {
        // BUY signal
        if (
            $data->isNewBuySignal == 1 &&
            $data->isSmaUptrend == 1 &&
            $data->isEmaUptrend == 1 &&
            $data->prediction >= $predictionThreshold
        ) {
            return [
                'status' => 'buy',
                'entry' => $data->startLongTrade,
            ];
        }

        // SELL signal
        if (
            $data->isNewSellSignal == 1 &&
            $data->isSmaDowntrend == 1 &&
            $data->isEmaDowntrend == 1 &&
            $data->prediction <= -$predictionThreshold
        ) {
            return [
                'status' => 'sell',
                'entry' => $data->startShortTrade,
            ];
        }

        // No clear signal
        return ['status' => 'no_signal'];
    }

}

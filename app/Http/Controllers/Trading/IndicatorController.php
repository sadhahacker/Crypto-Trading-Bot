<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\BotConfiguration;
use App\Plugins\LorentzianClassification\ScriptsRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IndicatorController extends Controller{

    public function tradeStater()
    {
        $symbol = BotConfiguration::getValue('DEFAULT_SYMBOL');
        $threshold = BotConfiguration::getValue('PREDICTION_THRESHOLD');
        $latest = (new ScriptsRunner())->lorentzianTable()
            ->orderBy('timestamp', 'desc')
            ->first();

        if (!$latest) return;

        $last = \Cache::get('last_lorentzian_timestamp');

        if (!$last || $latest->timestamp != $last) {

            $signal = $this->lorentzianUpdated($latest, $threshold);

            if ($signal['status'] !== 'no_signal') {

                (new ExecuteTradeController())->executeTrade(
                    $symbol,
                    $signal['entry'],
                    $signal['status']
                );

                \Cache::put('last_lorentzian_timestamp', $latest->timestamp);
            }
        }
    }

    public function lorentzianUpdated($data, $predictionThreshold = 6)
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

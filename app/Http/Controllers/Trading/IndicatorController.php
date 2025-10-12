<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Common\DataFrame;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategies\EmaRsiVolumeStrategy;
use App\Models\BotConfiguration;
use App\Plugins\LorentzianClassification\ScriptsRunner;

class IndicatorController extends Controller{

    public function tradeStater()
    {
        $symbol = BotConfiguration::getValue('DEFAULT_SYMBOL');

        $candles = (new TradeController())->getOHLCV($symbol, '1h');
        $signal = (new EmaRsiVolumeStrategy())->checkForSignal((new DataFrame($candles)));

        if(is_null($signal)){
            return;
        }

        (new ExecuteTradeController())->executeTrade(
            $symbol,
            $signal['entry'],
            strtolower($signal['signal'])
        );
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

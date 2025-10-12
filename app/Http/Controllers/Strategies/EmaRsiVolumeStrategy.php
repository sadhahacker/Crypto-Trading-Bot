<?php

namespace App\Http\Controllers\Strategies;

use App\Http\Controllers\Common\DataFrame;
use App\Http\Controllers\Controller;
use LupeCode\phpTraderNative\Trader;

class EmaRsiVolumeStrategy extends Controller
{
    // ===================================================================
    // STRATEGY PARAMETERS
    // ===================================================================

    private $fastEmaPeriod = 20;       // Fast EMA
    private $slowEmaPeriod = 50;       // Slow EMA
    private $rsiPeriod = 14;           // RSI period
    private $rsiOverbought = 70;       // RSI overbought threshold
    private $rsiOversold = 30;         // RSI oversold threshold
    private $tradeDirection = "BOTH";  // "LONG", "SHORT", "BOTH"

    // ===================================================================
    // SIGNAL DETECTION
    // ===================================================================


    /**
     * Check for EMA + RSI + MACD signals
     * @param DataFrame $df
     * @return array|null ['signal' => "BUY"/"SELL", 'entry' => price] or null
     */
    public function checkForSignal(DataFrame $df)
    {
        if ($df->count() < max($this->slowEmaPeriod, $this->rsiPeriod, 26) + 2) {
            return null;
        }

        try {
            $close = $df->getColumn('close');

            // --- Calculate EMAs ---
            $fastEma = Trader::ema($close, $this->fastEmaPeriod);
            $slowEma = Trader::ema($close, $this->slowEmaPeriod);

            // --- Calculate RSI ---
            $rsi = Trader::rsi($close, $this->rsiPeriod);

            // --- Calculate MACD ---
            [$macdLine, $signalLine, $hist] = Trader::macd($close, 12, 26, 9);

            $prevIdx = $df->getLastIndex(3);
            $lastIdx = $df->getLastIndex(2);

            // Ensure values exist
            if (!isset($fastEma[$lastIdx], $slowEma[$lastIdx], $rsi[$lastIdx], $macdLine[$lastIdx], $signalLine[$lastIdx], $close[$lastIdx])) {
                return null;
            }

            $entryPrice = $close[$lastIdx]; // Entry is the close price of the last candle

            // --- BUY Signal ---
            if (
                $fastEma[$prevIdx] <= $slowEma[$prevIdx] &&
                $fastEma[$lastIdx] > $slowEma[$lastIdx] &&
                $rsi[$lastIdx] < $this->rsiOversold &&
                $macdLine[$lastIdx] > $signalLine[$lastIdx] &&
                in_array($this->tradeDirection, ["LONG", "BOTH"])
            ) {
                return ['signal' => 'BUY', 'entry' => $entryPrice];
            }

            // --- SELL Signal ---
            if (
                $fastEma[$prevIdx] >= $slowEma[$prevIdx] &&
                $fastEma[$lastIdx] < $slowEma[$lastIdx] &&
                $rsi[$lastIdx] > $this->rsiOverbought &&
                $macdLine[$lastIdx] < $signalLine[$lastIdx] &&
                in_array($this->tradeDirection, ["SHORT", "BOTH"])
            ) {
                return ['signal' => 'SELL', 'entry' => $entryPrice];
            }

        } catch (\Exception $e) {
            return null;
        }

        return null;
    }
}

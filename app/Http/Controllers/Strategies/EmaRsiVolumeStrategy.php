<?php

namespace App\Http\Controllers\Strategies;

use App\Http\Controllers\Common\DataFrame;
use App\Http\Controllers\Controller;
use LupeCode\phpTraderNative\Trader;

class EmaRsiVolumeStrategy extends Controller
{
    private $fastEmaPeriod = 20;
    private $slowEmaPeriod = 50;
    private $rsiPeriod = 14;
    private $rsiOverbought = 70;
    private $rsiOversold = 30;
    private $tradeDirection = "BOTH";

    /**
     * Generate signal for every candle: 1 (BUY), -1 (SELL), 0 (NONE)
     * @param DataFrame $df
     * @return array ['signals' => [...], 'prices' => [...]]
     */
    public function generateSignals(DataFrame $df)
    {
        try {
            $close = $df->getColumn('close');
            $count = count($close);

            // Initialize an array of 0s (Neutral) for all signals
            $signals = array_fill(0, $count, 0);

            // 2. Calculate all indicators
            $fastEma = Trader::ema($close, $this->fastEmaPeriod);
            $slowEma = Trader::ema($close, $this->slowEmaPeriod);
            $rsi = Trader::rsi($close, $this->rsiPeriod);
            $macdResult = Trader::macd($close, 12, 26, 9);

            // --- ⬇️ NEW VALIDATION BLOCK ⬇️ ---
            // Check if any indicator calculation failed (returned false).
            // This happens if the $close array has insufficient data for the periods.
            if ($fastEma === false || $slowEma === false || $rsi === false || $macdResult === false)
            {
                // Not enough data to calculate indicators.
                // We can log this as a warning if needed.
                // Log::warning('Could not calculate signals: insufficient candle data.');

                // Return the initialized array of all 0s.
                return $signals;
            }

            // --- ⬆️ END NEW VALIDATION BLOCK ⬆️ ---

            // Now it's safe to access the MACD array
            $macdLine = $macdResult['MACD'];
            $signalLine = $macdResult['MACDSignal'];

            // 3. Loop through each candle to check for signals
            for ($i = 0; $i < $count; $i++) {

                // --- Data Validation ---
                // (This check is still good, as it handles the beginning
                // of the array where values are `false` before the period is met)
                if (!is_numeric($fastEma[$i]) || !is_numeric($slowEma[$i]) ||
                    !is_numeric($rsi[$i]) || !is_numeric($macdLine[$i]) || !is_numeric($signalLine[$i]))
                {
                    continue;
                }

                // --- Define Strategy Logic ---

                // **Buy Signal (1) Conditions:**
                $isBuy = ($fastEma[$i] > $slowEma[$i]) &&
                    ($macdLine[$i] > $signalLine[$i]) &&
                    ($rsi[$i] > 50);

                // **Sell Signal (-1) Conditions:**
                $isSell = ($fastEma[$i] < $slowEma[$i]) &&
                    ($macdLine[$i] < $signalLine[$i]) &&
                    ($rsi[$i] < 50);

                // --- Assign Signals ---
                if ($isBuy) {
                    $signals[$i] = 1;
                } elseif ($isSell) {
                    $signals[$i] = -1;
                }
            }

            return $signals;

        } catch (\Exception $e) {
            dd($e);
            // Log::error('Signal generation failed: ' . $e->getMessage());
            return []; // Return an empty array on failure
        }
    }
}

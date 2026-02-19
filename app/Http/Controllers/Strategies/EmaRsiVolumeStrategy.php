<?php

namespace App\Http\Controllers\Strategies;

use App\Http\Controllers\Common\DataFrame;
use Carbon\Carbon;
use Stochastix\Domain\Common\Enum\AppliedPriceEnum;
use Stochastix\Domain\Common\Enum\DirectionEnum;
use Stochastix\Domain\Common\Enum\TALibFunctionEnum;
use Stochastix\Domain\Common\Model\MultiTimeframeOhlcvSeries;
use Stochastix\Domain\Indicator\Model\TALibIndicator;
use Stochastix\Domain\Order\Enum\OrderTypeEnum;
use Stochastix\Domain\Strategy\AbstractStrategy;

class EmaRsiVolumeStrategy extends AbstractStrategy
{
    private int $emaFastPeriod = 9;
    private int $emaSlowPeriod = 21;
    private int $rsiPeriod = 14;
    private int $volumeSmaPeriod = 20;
    private int $atrPeriod = 14;

    private float $rsiLongMin = 52.0;
    private float $rsiLongMax = 78.0;
    private float $rsiShortMin = 22.0;
    private float $rsiShortMax = 48.0;

    private float $volumeMultiplier = 1.10;
    private float $minAtrPercent = 0.0015;
    private float $atrStopLossMultiplier = 1.5;
    private float $atrTakeProfitMultiplier = 2.4;

    private float $riskPerTrade = 0.02;
    private float $fallbackQuantity = 0.50;
    private int $cooldownBars = 2;
    private int $maxHoldingBars = 72;

    protected function defineIndicators(): void
    {
        $this
            ->addIndicator(
                'ema_fast',
                new TALibIndicator(
                    TALibFunctionEnum::Ema,
                    ['timePeriod' => $this->emaFastPeriod]
                )
            )
            ->addIndicator(
                'ema_slow',
                new TALibIndicator(
                    TALibFunctionEnum::Ema,
                    ['timePeriod' => $this->emaSlowPeriod]
                )
            )
            ->addIndicator(
                'rsi',
                new TALibIndicator(
                    TALibFunctionEnum::Rsi,
                    ['timePeriod' => $this->rsiPeriod],
                    AppliedPriceEnum::Close
                )
            );
    }

    public function onBar(MultiTimeframeOhlcvSeries $bars): void
    {
        $currentClose = $this->toFloat($bars->close[0]);
        $currentVolume = $this->toFloat($bars->volume[0]);

        if ($currentClose === null || $currentClose <= 0 || $currentVolume === null) {
            return;
        }

        $fastEma = $this->getIndicatorSeries('ema_fast');
        $slowEma = $this->getIndicatorSeries('ema_slow');
        $rsiSeries = $this->getIndicatorSeries('rsi');

        $fastCurrent = $this->toFloat($fastEma[0]);
        $slowCurrent = $this->toFloat($slowEma[0]);
        $rsiCurrent = $this->toFloat($rsiSeries[0]);
        $atrCurrent = $this->calculateCurrentAtrFromBars($bars, $this->atrPeriod);
        $volumeSmaCurrent = $this->calculateCurrentSmaFromSeries($bars->volume->toArray(), $this->volumeSmaPeriod);

        if (
            $fastCurrent === null ||
            $slowCurrent === null ||
            $rsiCurrent === null ||
            $atrCurrent === null ||
            $volumeSmaCurrent === null ||
            $volumeSmaCurrent <= 0
        ) {
            return;
        }

        $atrPercent = $atrCurrent / $currentClose;
        $volumeOk = $currentVolume >= ($volumeSmaCurrent * $this->volumeMultiplier);
        $volatilityOk = $atrPercent >= $this->minAtrPercent;

        $isLongSignal = $fastEma->crossesOver($slowEma)
            && $rsiCurrent >= $this->rsiLongMin
            && $rsiCurrent <= $this->rsiLongMax
            && $volumeOk
            && $volatilityOk;

        $isShortSignal = $fastEma->crossesUnder($slowEma)
            && $rsiCurrent <= $this->rsiShortMax
            && $rsiCurrent >= $this->rsiShortMin
            && $volumeOk
            && $volatilityOk;

        $quantity = $this->calculateLiveQuantity($currentClose, $atrCurrent);
        if ($quantity <= 0) {
            return;
        }

        $quantityString = $this->toDecimalString($quantity, 8);

        if (!$this->isInPosition()) {
            if ($isLongSignal) {
                $stopLoss = $currentClose - ($atrCurrent * $this->atrStopLossMultiplier);
                $takeProfit = $currentClose + ($atrCurrent * $this->atrTakeProfitMultiplier);

                $this->entry(
                    direction: DirectionEnum::Long,
                    orderType: OrderTypeEnum::Market,
                    quantity: $quantityString,
                    stopLossPrice: $this->toDecimalString($stopLoss),
                    takeProfitPrice: $this->toDecimalString($takeProfit),
                    clientOrderId: 'ema-rsi-vol-long-' . time()
                );
            }

            if ($isShortSignal) {
                $stopLoss = $currentClose + ($atrCurrent * $this->atrStopLossMultiplier);
                $takeProfit = $currentClose - ($atrCurrent * $this->atrTakeProfitMultiplier);

                $this->entry(
                    direction: DirectionEnum::Short,
                    orderType: OrderTypeEnum::Market,
                    quantity: $quantityString,
                    stopLossPrice: $this->toDecimalString($stopLoss),
                    takeProfitPrice: $this->toDecimalString($takeProfit),
                    clientOrderId: 'ema-rsi-vol-short-' . time()
                );
            }
        } else {
            $currentSymbol = $this->context?->getCurrentSymbol();
            if ($currentSymbol === null) {
                return;
            }

            $openPosition = $this->orderManager?->getPortfolioManager()?->getOpenPosition($currentSymbol);
            if ($openPosition === null) {
                return;
            }

            if ($openPosition->direction === DirectionEnum::Long && $isShortSignal) {
                $this->exit($openPosition->quantity);
            }

            if ($openPosition->direction === DirectionEnum::Short && $isLongSignal) {
                $this->exit($openPosition->quantity);
            }
        }
    }

    /**
     * Return only the latest actionable live signal.
     *
     * @return array{
     *     signal:string,
     *     entry:float,
     *     stop_loss:float,
     *     take_profit:float,
     *     confidence:int,
     *     prediction:int,
     *     timestamp:string|null
     * }|null
     */
    public function checkForSignal(DataFrame $dataFrame): ?array
    {
        $series = $this->prepareSeries($dataFrame);
        $count = count($series['close']);
        if ($count === 0) {
            return null;
        }

        $lastIndex = $count - 1;
        $evaluation = $this->evaluateSignalAtIndex($series, $lastIndex);
        if (!$evaluation['is_buy'] && !$evaluation['is_sell']) {
            return null;
        }

        $close = $this->toFloat($series['close'][$lastIndex]);
        if ($close === null || $close <= 0) {
            return null;
        }

        $signal = $evaluation['is_buy'] ? 'BUY' : 'SELL';
        $timestamp = $series['timestamp'][$lastIndex] ?? null;

        return [
            'signal' => $signal,
            'entry' => round($close, 8),
            'stop_loss' => round($evaluation['stop_loss'] ?? 0.0, 8),
            'take_profit' => round($evaluation['take_profit'] ?? 0.0, 8),
            'confidence' => (int) $evaluation['confidence'],
            'prediction' => (int) $evaluation['prediction'],
            'timestamp' => $this->formatTimestamp($timestamp),
        ];
    }

    /**
     * Generate full indicator and signal arrays for charting/debugging.
     */
    public function generateSignals(DataFrame $dataFrame): array
    {
        $series = $this->prepareSeries($dataFrame);
        $count = count($series['close']);

        $result = [
            'timestamp' => $series['timestamp'],
            'open' => $series['open'],
            'high' => $series['high'],
            'low' => $series['low'],
            'close' => $series['close'],
            'volume' => $series['volume'],
            'emaFast' => $series['ema_fast'],
            'emaSlow' => $series['ema_slow'],
            'rsi' => $series['rsi'],
            'volumeSma' => $series['volume_sma'],
            'atr' => $series['atr'],
            'isNewBuySignal' => [],
            'isNewSellSignal' => [],
            'prediction' => [],
            'confidence' => [],
            'signal' => [],
            'stopLoss' => [],
            'takeProfit' => [],
            'rows' => [],
        ];

        for ($i = 0; $i < $count; $i++) {
            $evaluation = $this->evaluateSignalAtIndex($series, $i);
            $signal = 'HOLD';
            if ($evaluation['is_buy']) {
                $signal = 'BUY';
            }
            if ($evaluation['is_sell']) {
                $signal = 'SELL';
            }

            $result['isNewBuySignal'][$i] = $evaluation['is_buy'];
            $result['isNewSellSignal'][$i] = $evaluation['is_sell'];
            $result['prediction'][$i] = (int) $evaluation['prediction'];
            $result['confidence'][$i] = (int) $evaluation['confidence'];
            $result['signal'][$i] = $signal;
            $result['stopLoss'][$i] = $evaluation['stop_loss'];
            $result['takeProfit'][$i] = $evaluation['take_profit'];

            $result['rows'][] = [
                'timestamp' => $this->formatTimestamp($series['timestamp'][$i] ?? null),
                'close' => $series['close'][$i] ?? null,
                'ema_fast' => $series['ema_fast'][$i] ?? null,
                'ema_slow' => $series['ema_slow'][$i] ?? null,
                'rsi' => $series['rsi'][$i] ?? null,
                'volume' => $series['volume'][$i] ?? null,
                'volume_sma' => $series['volume_sma'][$i] ?? null,
                'atr' => $series['atr'][$i] ?? null,
                'signal' => $signal,
                'prediction' => (int) $evaluation['prediction'],
                'confidence' => (int) $evaluation['confidence'],
                'stop_loss' => $evaluation['stop_loss'],
                'take_profit' => $evaluation['take_profit'],
            ];
        }

        return $result;
    }

    /**
     * Advanced backtest runner for this strategy.
     */
    public function runBacktest(DataFrame $dataFrame, array $options = []): array
    {
        $series = $this->prepareSeries($dataFrame);
        $count = count($series['close']);

        $initialBalance = max((float) ($options['initial_balance'] ?? 1000.0), 1.0);
        $balance = $initialBalance;
        $riskPerTrade = max(min((float) ($options['risk_per_trade'] ?? $this->riskPerTrade), 0.30), 0.001);
        $feeRate = max((float) ($options['fee_rate'] ?? 0.0006), 0.0);
        $slippageRate = max((float) ($options['slippage_rate'] ?? 0.0004), 0.0);
        $leverage = max((float) ($options['leverage'] ?? 1.0), 1.0);
        $allowShort = (bool) ($options['allow_short'] ?? true);
        $maxOpenBars = max((int) ($options['max_open_bars'] ?? $this->maxHoldingBars), 1);
        $takeProfitPercent = max((float) ($options['take_profit_percent'] ?? 0.0), 0.0);
        $stopLossPercent = max((float) ($options['stop_loss_percent'] ?? 0.0), 0.0);

        $warmup = $this->getWarmupBars();
        $lastExitIndex = -PHP_INT_MAX;
        $position = null;
        $trades = [];
        $equityCurve = [];

        $grossProfit = 0.0;
        $grossLoss = 0.0;
        $winCount = 0;
        $lossCount = 0;
        $winStreak = 0;
        $lossStreak = 0;
        $maxWinStreak = 0;
        $maxLossStreak = 0;
        $maxBalance = $balance;
        $maxDrawdownPct = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $timestamp = $series['timestamp'][$i] ?? null;
            $close = $this->toFloat($series['close'][$i] ?? null);
            $high = $this->toFloat($series['high'][$i] ?? null);
            $low = $this->toFloat($series['low'][$i] ?? null);

            if ($close === null || $high === null || $low === null || $close <= 0) {
                $equityCurve[] = [
                    'timestamp' => $timestamp,
                    'equity' => round($balance, 6),
                ];
                continue;
            }

            $evaluation = $this->evaluateSignalAtIndex($series, $i);
            $isBuySignal = $evaluation['is_buy'];
            $isSellSignal = $evaluation['is_sell'];

            if ($position === null && $i >= $warmup && ($i - $lastExitIndex) > $this->cooldownBars) {
                $entrySide = null;
                if ($isBuySignal) {
                    $entrySide = 'BUY';
                } elseif ($isSellSignal && $allowShort) {
                    $entrySide = 'SELL';
                }

                if ($entrySide !== null) {
                    $entryPriceRaw = $close;
                    $entryPrice = $this->applySlippage($entryPriceRaw, $entrySide, $slippageRate, true);

                    if ($takeProfitPercent > 0 && $stopLossPercent > 0) {
                        if ($entrySide === 'BUY') {
                            $stopLoss = $entryPrice * (1 - ($stopLossPercent / 100));
                            $takeProfit = $entryPrice * (1 + ($takeProfitPercent / 100));
                        } else {
                            $stopLoss = $entryPrice * (1 + ($stopLossPercent / 100));
                            $takeProfit = $entryPrice * (1 - ($takeProfitPercent / 100));
                        }
                    } else {
                        $stopLoss = $evaluation['stop_loss'];
                        $takeProfit = $evaluation['take_profit'];
                    }

                    if ($stopLoss !== null && $takeProfit !== null) {
                        $stopDistance = abs($entryPrice - $stopLoss);

                        if ($stopDistance > 0) {
                            $riskAmount = $balance * $riskPerTrade;
                            $quantityByRisk = $riskAmount / $stopDistance;
                            $maxQuantityByLeverage = ($balance * $leverage) / $entryPrice;
                            $quantity = min($quantityByRisk, $maxQuantityByLeverage);

                            if ($quantity > 0) {
                                $entryFee = $entryPrice * $quantity * $feeRate;
                                $position = [
                                    'side' => $entrySide,
                                    'entry_index' => $i,
                                    'entry_time' => $this->formatTimestamp($timestamp),
                                    'entry_price' => $entryPrice,
                                    'quantity' => $quantity,
                                    'stop_loss' => $stopLoss,
                                    'take_profit' => $takeProfit,
                                    'entry_fee' => $entryFee,
                                    'prediction' => (int) $evaluation['prediction'],
                                    'confidence' => (int) $evaluation['confidence'],
                                ];
                            }
                        }
                    }
                }
            }

            if ($position !== null) {
                $exitReason = null;
                $exitReferencePrice = null;

                if ($position['side'] === 'BUY') {
                    $hitStop = $low <= $position['stop_loss'];
                    $hitTarget = $high >= $position['take_profit'];
                    if ($hitStop && $hitTarget) {
                        $exitReason = 'SL/TP Same Candle (Conservative)';
                        $exitReferencePrice = $position['stop_loss'];
                    } elseif ($hitStop) {
                        $exitReason = 'Stop Loss';
                        $exitReferencePrice = $position['stop_loss'];
                    } elseif ($hitTarget) {
                        $exitReason = 'Take Profit';
                        $exitReferencePrice = $position['take_profit'];
                    } elseif ($isSellSignal) {
                        $exitReason = 'Opposite Signal';
                        $exitReferencePrice = $close;
                    }
                } else {
                    $hitStop = $high >= $position['stop_loss'];
                    $hitTarget = $low <= $position['take_profit'];
                    if ($hitStop && $hitTarget) {
                        $exitReason = 'SL/TP Same Candle (Conservative)';
                        $exitReferencePrice = $position['stop_loss'];
                    } elseif ($hitStop) {
                        $exitReason = 'Stop Loss';
                        $exitReferencePrice = $position['stop_loss'];
                    } elseif ($hitTarget) {
                        $exitReason = 'Take Profit';
                        $exitReferencePrice = $position['take_profit'];
                    } elseif ($isBuySignal) {
                        $exitReason = 'Opposite Signal';
                        $exitReferencePrice = $close;
                    }
                }

                if ($exitReason === null && ($i - $position['entry_index']) >= $maxOpenBars) {
                    $exitReason = 'Max Holding Bars';
                    $exitReferencePrice = $close;
                }

                if ($exitReason === null && $i === ($count - 1)) {
                    $exitReason = 'End Of Data';
                    $exitReferencePrice = $close;
                }

                if ($exitReason !== null && $exitReferencePrice !== null) {
                    $exitPrice = $this->applySlippage($exitReferencePrice, $position['side'], $slippageRate, false);
                    $quantity = $position['quantity'];
                    $entryPrice = $position['entry_price'];
                    $grossPnl = $position['side'] === 'BUY'
                        ? (($exitPrice - $entryPrice) * $quantity)
                        : (($entryPrice - $exitPrice) * $quantity);
                    $exitFee = $exitPrice * $quantity * $feeRate;
                    $netPnl = $grossPnl - $position['entry_fee'] - $exitFee;
                    $balance += $netPnl;

                    if ($netPnl > 0) {
                        $grossProfit += $netPnl;
                        $winCount++;
                        $winStreak++;
                        $lossStreak = 0;
                        $maxWinStreak = max($maxWinStreak, $winStreak);
                    } elseif ($netPnl < 0) {
                        $grossLoss += abs($netPnl);
                        $lossCount++;
                        $lossStreak++;
                        $winStreak = 0;
                        $maxLossStreak = max($maxLossStreak, $lossStreak);
                    } else {
                        $winStreak = 0;
                        $lossStreak = 0;
                    }

                    $reward = abs($exitPrice - $entryPrice);
                    $risk = abs($entryPrice - $position['stop_loss']);
                    $rr = $risk > 0 ? ($reward / $risk) : null;

                    $trades[] = [
                        'side' => $position['side'],
                        'entry_time' => $position['entry_time'],
                        'entry_price' => round($entryPrice, 8),
                        'exit_time' => $this->formatTimestamp($timestamp),
                        'exit_price' => round($exitPrice, 8),
                        'quantity' => round($quantity, 8),
                        'stop_loss' => round((float) $position['stop_loss'], 8),
                        'take_profit' => round((float) $position['take_profit'], 8),
                        'gross_pnl' => round($grossPnl, 8),
                        'fees' => round($position['entry_fee'] + $exitFee, 8),
                        'net_pnl' => round($netPnl, 8),
                        'pnl_percent' => $entryPrice > 0 ? round((($netPnl / ($entryPrice * $quantity)) * 100), 4) : 0.0,
                        'holding_bars' => $i - $position['entry_index'],
                        'rr' => $rr !== null ? round($rr, 4) : null,
                        'exit_reason' => $exitReason,
                        'prediction' => $position['prediction'],
                        'confidence' => $position['confidence'],
                        'balance_after' => round($balance, 8),
                    ];

                    $position = null;
                    $lastExitIndex = $i;
                }
            }

            $equity = $balance;
            if ($position !== null) {
                $unrealized = $position['side'] === 'BUY'
                    ? (($close - $position['entry_price']) * $position['quantity'])
                    : (($position['entry_price'] - $close) * $position['quantity']);
                $equity += $unrealized;
            }

            $maxBalance = max($maxBalance, $equity);
            if ($maxBalance > 0) {
                $drawdownPct = (($maxBalance - $equity) / $maxBalance) * 100;
                $maxDrawdownPct = max($maxDrawdownPct, $drawdownPct);
            }

            $equityCurve[] = [
                'timestamp' => $timestamp,
                'equity' => round($equity, 6),
            ];
        }

        $totalTrades = count($trades);
        $winRate = $totalTrades > 0 ? (($winCount / $totalTrades) * 100) : 0.0;
        $netProfit = $balance - $initialBalance;
        $avgTrade = $totalTrades > 0 ? ($netProfit / $totalTrades) : 0.0;
        $avgWin = $winCount > 0 ? ($grossProfit / $winCount) : 0.0;
        $avgLoss = $lossCount > 0 ? ($grossLoss / $lossCount) : 0.0;
        $profitFactor = $grossLoss > 0 ? ($grossProfit / $grossLoss) : null;
        $expectancy = ($winRate / 100 * $avgWin) - ((1 - ($winRate / 100)) * $avgLoss);
        $avgHoldingBars = $totalTrades > 0
            ? (array_sum(array_column($trades, 'holding_bars')) / $totalTrades)
            : 0.0;

        return [
            'trades' => $trades,
            'equity_curve' => $equityCurve,
            'stats' => [
                'initial_balance' => round($initialBalance, 2),
                'final_balance' => round($balance, 2),
                'net_profit' => round($netProfit, 2),
                'net_profit_percent' => round(($netProfit / $initialBalance) * 100, 2),
                'total_trades' => $totalTrades,
                'winning_trades' => $winCount,
                'losing_trades' => $lossCount,
                'win_rate' => round($winRate, 2),
                'gross_profit' => round($grossProfit, 2),
                'gross_loss' => round($grossLoss, 2),
                'profit_factor' => $profitFactor !== null ? round($profitFactor, 4) : null,
                'average_trade' => round($avgTrade, 4),
                'average_win' => round($avgWin, 4),
                'average_loss' => round($avgLoss, 4),
                'expectancy' => round($expectancy, 4),
                'average_holding_bars' => round($avgHoldingBars, 2),
                'max_drawdown_percent' => round($maxDrawdownPct, 2),
                'max_win_streak' => $maxWinStreak,
                'max_loss_streak' => $maxLossStreak,
                'settings' => [
                    'risk_per_trade' => $riskPerTrade,
                    'fee_rate' => $feeRate,
                    'slippage_rate' => $slippageRate,
                    'leverage' => $leverage,
                    'allow_short' => $allowShort,
                    'max_open_bars' => $maxOpenBars,
                    'take_profit_percent' => $takeProfitPercent,
                    'stop_loss_percent' => $stopLossPercent,
                ],
            ],
            'signals' => $this->generateSignals($dataFrame),
        ];
    }

    private function calculateLiveQuantity(float $price, float $atr): float
    {
        $stopDistance = $atr * $this->atrStopLossMultiplier;
        if ($price <= 0 || $stopDistance <= 0) {
            return $this->fallbackQuantity;
        }

        try {
            $availableCash = $this->toFloat($this->orderManager?->getPortfolioManager()?->getAvailableCash());
        } catch (\Throwable) {
            $availableCash = null;
        }

        if ($availableCash === null || $availableCash <= 0) {
            return $this->fallbackQuantity;
        }

        $riskAmount = $availableCash * $this->riskPerTrade;
        $quantity = $riskAmount / $stopDistance;

        if (!is_finite($quantity) || $quantity <= 0) {
            return $this->fallbackQuantity;
        }

        return $quantity;
    }

    private function applySlippage(float $price, string $side, float $slippageRate, bool $isEntry): float
    {
        $slippageRate = max($slippageRate, 0.0);

        if ($isEntry) {
            if ($side === 'BUY') {
                return $price * (1 + $slippageRate);
            }

            return $price * (1 - $slippageRate);
        }

        if ($side === 'BUY') {
            // Closing a long is a sell.
            return $price * (1 - $slippageRate);
        }

        // Closing a short is a buy.
        return $price * (1 + $slippageRate);
    }

    private function getWarmupBars(): int
    {
        return max(
            $this->emaSlowPeriod,
            $this->rsiPeriod,
            $this->volumeSmaPeriod,
            $this->atrPeriod
        ) + 1;
    }

    private function prepareSeries(DataFrame $dataFrame): array
    {
        $raw = $dataFrame->getKeysWithData();

        $open = $this->toFloatArray($raw['open'] ?? []);
        $high = $this->toFloatArray($raw['high'] ?? []);
        $low = $this->toFloatArray($raw['low'] ?? []);
        $close = $this->toFloatArray($raw['close'] ?? []);
        $volume = $this->toFloatArray($raw['volume'] ?? []);
        $timestamp = $raw['timestamp'] ?? [];

        $count = min(
            count($open),
            count($high),
            count($low),
            count($close),
            count($volume),
            count($timestamp)
        );

        if ($count <= 0) {
            return [
                'timestamp' => [],
                'open' => [],
                'high' => [],
                'low' => [],
                'close' => [],
                'volume' => [],
                'ema_fast' => [],
                'ema_slow' => [],
                'rsi' => [],
                'volume_sma' => [],
                'atr' => [],
            ];
        }

        $timestamp = array_slice($timestamp, 0, $count);
        $open = array_slice($open, 0, $count);
        $high = array_slice($high, 0, $count);
        $low = array_slice($low, 0, $count);
        $close = array_slice($close, 0, $count);
        $volume = array_slice($volume, 0, $count);

        return [
            'timestamp' => $timestamp,
            'open' => $open,
            'high' => $high,
            'low' => $low,
            'close' => $close,
            'volume' => $volume,
            'ema_fast' => $this->calculateEma($close, $this->emaFastPeriod),
            'ema_slow' => $this->calculateEma($close, $this->emaSlowPeriod),
            'rsi' => $this->calculateRsi($close, $this->rsiPeriod),
            'volume_sma' => $this->calculateSma($volume, $this->volumeSmaPeriod),
            'atr' => $this->calculateAtr($high, $low, $close, $this->atrPeriod),
        ];
    }

    /**
     * @return array{
     *     is_buy:bool,
     *     is_sell:bool,
     *     prediction:int,
     *     confidence:int,
     *     stop_loss:float|null,
     *     take_profit:float|null
     * }
     */
    private function evaluateSignalAtIndex(array $series, int $index): array
    {
        $close = $this->toFloat($series['close'][$index] ?? null);
        $emaFast = $this->toFloat($series['ema_fast'][$index] ?? null);
        $emaSlow = $this->toFloat($series['ema_slow'][$index] ?? null);
        $rsi = $this->toFloat($series['rsi'][$index] ?? null);
        $volume = $this->toFloat($series['volume'][$index] ?? null);
        $volumeSma = $this->toFloat($series['volume_sma'][$index] ?? null);
        $atr = $this->toFloat($series['atr'][$index] ?? null);

        if (
            $close === null ||
            $close <= 0 ||
            $emaFast === null ||
            $emaSlow === null ||
            $rsi === null ||
            $volume === null ||
            $volumeSma === null ||
            $volumeSma <= 0 ||
            $atr === null ||
            $atr <= 0
        ) {
            return [
                'is_buy' => false,
                'is_sell' => false,
                'prediction' => 0,
                'confidence' => 0,
                'stop_loss' => null,
                'take_profit' => null,
            ];
        }

        $crossUp = $this->crossesOver($series['ema_fast'], $series['ema_slow'], $index);
        $crossDown = $this->crossesUnder($series['ema_fast'], $series['ema_slow'], $index);
        $volumeRatio = $volumeSma > 0 ? ($volume / $volumeSma) : 0.0;
        $volumeOk = $volumeRatio >= $this->volumeMultiplier;
        $atrPercent = $atr / $close;
        $volatilityOk = $atrPercent >= $this->minAtrPercent;

        $rsiLongOk = $rsi >= $this->rsiLongMin && $rsi <= $this->rsiLongMax;
        $rsiShortOk = $rsi <= $this->rsiShortMax && $rsi >= $this->rsiShortMin;

        $buySignal = $crossUp && $rsiLongOk && $volumeOk && $volatilityOk;
        $sellSignal = $crossDown && $rsiShortOk && $volumeOk && $volatilityOk;

        $longScore = 0;
        $shortScore = 0;

        if ($crossUp) {
            $longScore += 5;
        }
        if ($crossDown) {
            $shortScore += 5;
        }
        if ($rsiLongOk) {
            $longScore += 2;
        }
        if ($rsiShortOk) {
            $shortScore += 2;
        }
        if ($volumeOk) {
            $longScore += 2;
            $shortScore += 2;
        }
        if ($volatilityOk) {
            $longScore += 1;
            $shortScore += 1;
        }

        $prediction = $longScore - $shortScore;
        $prediction = max(-10, min(10, $prediction));
        $confidence = max($longScore, $shortScore);

        $stopLoss = null;
        $takeProfit = null;
        if ($buySignal) {
            $stopLoss = $close - ($atr * $this->atrStopLossMultiplier);
            $takeProfit = $close + ($atr * $this->atrTakeProfitMultiplier);
        }
        if ($sellSignal) {
            $stopLoss = $close + ($atr * $this->atrStopLossMultiplier);
            $takeProfit = $close - ($atr * $this->atrTakeProfitMultiplier);
        }

        return [
            'is_buy' => $buySignal,
            'is_sell' => $sellSignal,
            'prediction' => $prediction,
            'confidence' => $confidence,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
        ];
    }

    private function crossesOver(array $fast, array $slow, int $index): bool
    {
        if ($index < 1) {
            return false;
        }

        $currentFast = $this->toFloat($fast[$index] ?? null);
        $currentSlow = $this->toFloat($slow[$index] ?? null);
        $previousFast = $this->toFloat($fast[$index - 1] ?? null);
        $previousSlow = $this->toFloat($slow[$index - 1] ?? null);

        if ($currentFast === null || $currentSlow === null || $previousFast === null || $previousSlow === null) {
            return false;
        }

        return $previousFast <= $previousSlow && $currentFast > $currentSlow;
    }

    private function crossesUnder(array $fast, array $slow, int $index): bool
    {
        if ($index < 1) {
            return false;
        }

        $currentFast = $this->toFloat($fast[$index] ?? null);
        $currentSlow = $this->toFloat($slow[$index] ?? null);
        $previousFast = $this->toFloat($fast[$index - 1] ?? null);
        $previousSlow = $this->toFloat($slow[$index - 1] ?? null);

        if ($currentFast === null || $currentSlow === null || $previousFast === null || $previousSlow === null) {
            return false;
        }

        return $previousFast >= $previousSlow && $currentFast < $currentSlow;
    }

    private function calculateEma(array $values, int $period): array
    {
        $count = count($values);
        if ($count === 0 || $period <= 0) {
            return [];
        }

        if (function_exists('trader_ema')) {
            $result = @trader_ema($values, $period);
            if (is_array($result) && !empty($result)) {
                return $this->padIndicatorResult($result, $count);
            }
        }

        $output = array_fill(0, $count, null);
        if ($count < $period) {
            return $output;
        }

        $seed = array_slice($values, 0, $period);
        $ema = array_sum($seed) / $period;
        $output[$period - 1] = $ema;
        $multiplier = 2 / ($period + 1);

        for ($i = $period; $i < $count; $i++) {
            $ema = (($values[$i] - $ema) * $multiplier) + $ema;
            $output[$i] = $ema;
        }

        return $output;
    }

    private function calculateSma(array $values, int $period): array
    {
        $count = count($values);
        if ($count === 0 || $period <= 0) {
            return [];
        }

        if (function_exists('trader_sma')) {
            $result = @trader_sma($values, $period);
            if (is_array($result) && !empty($result)) {
                return $this->padIndicatorResult($result, $count);
            }
        }

        $output = array_fill(0, $count, null);
        if ($count < $period) {
            return $output;
        }

        $running = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $running += $values[$i];
            if ($i >= $period) {
                $running -= $values[$i - $period];
            }

            if ($i >= $period - 1) {
                $output[$i] = $running / $period;
            }
        }

        return $output;
    }

    private function calculateRsi(array $values, int $period): array
    {
        $count = count($values);
        if ($count === 0 || $period <= 0) {
            return [];
        }

        if (function_exists('trader_rsi')) {
            $result = @trader_rsi($values, $period);
            if (is_array($result) && !empty($result)) {
                return $this->padIndicatorResult($result, $count);
            }
        }

        $output = array_fill(0, $count, null);
        if ($count <= $period) {
            return $output;
        }

        $gain = 0.0;
        $loss = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $diff = $values[$i] - $values[$i - 1];
            if ($diff >= 0) {
                $gain += $diff;
            } else {
                $loss += abs($diff);
            }
        }

        $avgGain = $gain / $period;
        $avgLoss = $loss / $period;
        $output[$period] = $this->calculateRsiValue($avgGain, $avgLoss);

        for ($i = $period + 1; $i < $count; $i++) {
            $diff = $values[$i] - $values[$i - 1];
            $currentGain = $diff > 0 ? $diff : 0.0;
            $currentLoss = $diff < 0 ? abs($diff) : 0.0;

            $avgGain = (($avgGain * ($period - 1)) + $currentGain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $currentLoss) / $period;

            $output[$i] = $this->calculateRsiValue($avgGain, $avgLoss);
        }

        return $output;
    }

    private function calculateRsiValue(float $avgGain, float $avgLoss): float
    {
        if ($avgLoss <= 0.0) {
            return 100.0;
        }

        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }

    private function calculateAtr(array $high, array $low, array $close, int $period): array
    {
        $count = min(count($high), count($low), count($close));
        if ($count === 0 || $period <= 0) {
            return [];
        }

        $tr = array_fill(0, $count, null);
        for ($i = 0; $i < $count; $i++) {
            $highLow = $high[$i] - $low[$i];
            if ($i === 0) {
                $tr[$i] = $highLow;
                continue;
            }

            $highClose = abs($high[$i] - $close[$i - 1]);
            $lowClose = abs($low[$i] - $close[$i - 1]);
            $tr[$i] = max($highLow, $highClose, $lowClose);
        }

        $atr = array_fill(0, $count, null);
        if ($count < $period) {
            return $atr;
        }

        $seed = array_slice($tr, 0, $period);
        $atrValue = array_sum($seed) / $period;
        $atr[$period - 1] = $atrValue;

        for ($i = $period; $i < $count; $i++) {
            $atrValue = (($atrValue * ($period - 1)) + $tr[$i]) / $period;
            $atr[$i] = $atrValue;
        }

        return $atr;
    }

    private function padIndicatorResult(array $result, int $count): array
    {
        $values = array_values($result);
        $pad = max($count - count($values), 0);

        if ($pad > 0) {
            return array_merge(array_fill(0, $pad, null), $values);
        }

        return array_slice($values, -$count);
    }

    private function calculateCurrentAtrFromBars(MultiTimeframeOhlcvSeries $bars, int $period): ?float
    {
        $high = array_values($bars->high->toArray());
        $low = array_values($bars->low->toArray());
        $close = array_values($bars->close->toArray());
        $atr = $this->calculateAtr($this->toFloatArray($high), $this->toFloatArray($low), $this->toFloatArray($close), $period);

        return $this->toFloat(end($atr));
    }

    private function calculateCurrentSmaFromSeries(array $values, int $period): ?float
    {
        $sma = $this->calculateSma($this->toFloatArray($values), $period);
        return $this->toFloat(end($sma));
    }

    private function toFloatArray(array $values): array
    {
        return array_map(function ($value) {
            return (float) $value;
        }, $values);
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function toDecimalString(float $value, int $decimals = 8): string
    {
        return number_format($value, $decimals, '.', '');
    }

    private function formatTimestamp(mixed $timestamp): ?string
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        if (is_numeric($timestamp)) {
            $intTimestamp = (int) $timestamp;
            if ($intTimestamp > 1000000000000) {
                return Carbon::createFromTimestampMs($intTimestamp)->toDateTimeString();
            }

            return Carbon::createFromTimestamp($intTimestamp)->toDateTimeString();
        }

        try {
            return Carbon::parse((string) $timestamp)->toDateTimeString();
        } catch (\Throwable) {
            return (string) $timestamp;
        }
    }
}

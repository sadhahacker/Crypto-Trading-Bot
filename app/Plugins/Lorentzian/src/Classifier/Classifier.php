<?php

namespace App\Plugins\Lorentzian\src\Classifier;

use App\Plugins\Lorentzian\src\Direction;
use App\Plugins\Lorentzian\src\MLExtensions as ml;
use App\Plugins\Lorentzian\src\KernelFunctions as kernels;
use App\Plugins\Lorentzian\src\Feature;
use App\Plugins\Lorentzian\src\Filter;
use App\Plugins\Lorentzian\src\FilterSettings;
use App\Plugins\Lorentzian\src\Settings;
use App\Plugins\Lorentzian\src\Utils;

class Classifier
{
    private array $df;
    private array $featuresSeries = [];
    private Settings $settings;
    private FilterSettings $filterSettings;
    private Filter $filter;

    public array $yhat1 = [];
    public array $yhat2 = [];

    public function __construct(array $data, ?array $features = null, ?array $settings = null, ?array $filterSettings = null)
    {
        $this->df = $data; // expects keys: open, high, low, close (arrays)

        if ($features === null) {
            $features = [
                new Feature("RSI", 14, 2),
                new Feature("WT", 10, 11),
                new Feature("CCI", 20, 2),
                new Feature("ADX", 20, 2),
                new Feature("RSI", 9, 2),
            ];
        }
        $src = $data['close'] ?? [];
        $this->settings = new Settings($settings ?? ['source' => $src]);
        $this->filterSettings = new FilterSettings(($filterSettings['kernelFilter'] ?? null) ?? null);

        foreach ($features as $f) {
            if ($f instanceof Feature) {
                $this->featuresSeries[] = $this->seriesFrom($f->type, $f->param1, $f->param2);
            } elseif (is_array($f)) {
                $this->featuresSeries[] = $f;
            }
        }

        $ohlc4 = self::avg4($data['open'], $data['high'], $data['low'], $data['close']);
        $this->filter = new Filter();
        $this->filter->volatility = ml::filter_volatility($data['high'], $data['low'], $data['close'], $this->filterSettings->useVolatilityFilter, 1, 10);
        $this->filter->regime = ml::regime_filter($ohlc4, $data['high'], $data['low'], $this->filterSettings->useRegimeFilter, $this->filterSettings->regimeThreshold);
        $this->filter->adx = ml::filter_adx($this->settings->source, $data['high'], $data['low'], $this->filterSettings->adxThreshold, $this->filterSettings->useAdxFilter, 14);

        $this->classify();
    }

    private function seriesFrom(string $feature, int $a, int $b): array
    {
        $d = $this->df;
        return match ($feature) {
            'RSI' => ml::n_rsi($d['close'], $a, $b),
            'WT' => ml::n_wt(self::avg3($d['high'], $d['low'], $d['close']), $a, $b),
            'CCI' => ml::n_cci($d['high'], $d['low'], $d['close'], $a, $b),
            'ADX' => ml::n_adx($d['high'], $d['low'], $d['close'], $a),
            default => []
        };
    }

    private function classify(): void
    {
        $n = count($this->df['close']);
        $maxBarsBackIndex = ($n >= $this->settings->maxBarsBack) ? ($n - $this->settings->maxBarsBack) : 0;

        $src = $this->settings->source;
        $yTrain = [];
        for ($i = 0; $i < $n; $i++) {
            $a = $src[$i - 4] ?? $src[0];
            $b = $src[$i] ?? $src[0];
            $yTrain[$i] = ($a < $b) ? Direction::SHORT->value : (($a > $b) ? Direction::LONG->value : Direction::NEUTRAL->value);
        }

        $prediction = [];
        $distancesArr = new class($this->featuresSeries, $src, $maxBarsBackIndex) {
            private array $features;
            private array $src;
            private int $maxBarsBackIndex;
            private int $batchSize = 50;
            private int $lastBatch = 0;
            private array $dists;
            public function __construct(array $features, array $src, int $maxBarsBackIndex)
            {
                $this->features = $features;
                $this->src = $src;
                $this->maxBarsBackIndex = $maxBarsBackIndex;
                $size = count($src) - $maxBarsBackIndex;
                $this->dists = array_fill(0, $this->batchSize, array_fill(0, $size, 0.0));
            }
            public function getRow(int $item): array
            {
                $size = count($this->src) - $this->maxBarsBackIndex;
                $batch = (int)ceil(($item + 1) / $this->batchSize) * $this->batchSize;
                if ($batch > $this->lastBatch) {
                    for ($r = 0; $r < $this->batchSize; $r++) {
                        for ($c = 0; $c < $size; $c++) $this->dists[$r][$c] = 0.0;
                    }
                    foreach ($this->features as $feature) {
                        $rows = array_fill(0, $this->batchSize, 0.0);
                        $fBatch = array_slice($feature, $this->maxBarsBackIndex + $this->lastBatch, $batch - $this->lastBatch);
                        $copySize = min(count($fBatch), $this->batchSize);
                        for ($i = 0; $i < $copySize; $i++) $rows[$i] = $fBatch[$i];
                        for ($i = 0; $i < $copySize; $i++) {
                            for ($j = 0; $j < $size; $j++) {
                                $this->dists[$i][$j] += log(1 + abs($rows[$i] - $feature[$j]));
                            }
                        }
                    }
                    $this->lastBatch = $batch;
                }
                return $this->dists[$item % $this->batchSize];
            }
        };

        for ($barIndex = 0; $barIndex < $maxBarsBackIndex; $barIndex++) $prediction[$barIndex] = 0;
        for ($barIndex = $maxBarsBackIndex; $barIndex < $n; $barIndex++) {
            $lastDistance = -1.0;
            $span = min($this->settings->maxBarsBack, $barIndex + 1);
            $distances = [];
            $preds = [];
            $row = $distancesArr->getRow($barIndex - $maxBarsBackIndex);
            for ($i = 0; $i < $span; $i++) {
                $d = $row[$i] ?? 0.0;
                if ($d >= $lastDistance && ($i % 4) != 0) {
                    $lastDistance = $d;
                    $distances[] = $d;
                    $preds[] = (int)round($yTrain[$i]);
                    if (count($preds) > $this->settings->neighborsCount) {
                        $q = (int)round($this->settings->neighborsCount * 3 / 4);
                        $lastDistance = $distances[$q] ?? $lastDistance;
                        array_shift($distances);
                        array_shift($preds);
                    }
                }
            }
            $prediction[$barIndex] = array_sum($preds);
        }

        $filterAll = self::andAll([$this->filter->volatility, $this->filter->regime, $this->filter->adx]);
        $signal = [];
        for ($i = 0; $i < $n; $i++) {
            $sig = null;
            if ($prediction[$i] > 0 && ($filterAll[$i] ?? true)) $sig = Direction::LONG->value;
            elseif ($prediction[$i] < 0 && ($filterAll[$i] ?? true)) $sig = Direction::SHORT->value;
            $signal[$i] = $sig;
        }
        if ($n > 0) $signal[0] = $signal[0] ?? 0;
        for ($i = 0; $i < $n; $i++) if ($signal[$i] === null) $signal[$i] = $signal[$i - 1] ?? $signal[0] ?? 0;

        $change = function (array $ser, int $i) use ($signal) {
            $a = Utils::shift($ser, $i, $ser[0] ?? 0);
            $b = Utils::shift($ser, $i + 1, $ser[0] ?? 0);
            $res = [];
            $m = count($ser);
            for ($k = 0; $k < $m; $k++) $res[$k] = ($a[$k] ?? null) !== ($b[$k] ?? null);
            return $res;
        };

        $isDifferentSignalType = [];
        for ($i = 0; $i < $n; $i++) $isDifferentSignalType[$i] = ($signal[$i] ?? 0) !== ($signal[$i - 1] ?? $signal[0] ?? 0);
        $sigFlipIdx = [];
        for ($i = 0; $i < $n; $i++) if ($isDifferentSignalType[$i]) $sigFlipIdx[] = $i;
        if (!in_array($n, $sigFlipIdx, true)) $sigFlipIdx[] = $n;
        $barsHeld = [];
        foreach ($sigFlipIdx as $idx => $x) {
            if ($idx > 0) $barsHeld[] = 0;
            $start = ($idx === 0) ? 0 : $sigFlipIdx[$idx - 1];
            for ($v = 1; $v <= $x - $start; $v++) $barsHeld[] = $v;
        }
        $isHeldFourBars = array_map(fn($v) => $v === 4, $barsHeld);
        $isHeldLessThanFourBars = array_map(fn($v) => $v < 4, $barsHeld);

        $isEarlySignalFlip = self::andAll([$change($signal, 0), $change($signal, 1), $change($signal, 2), $change($signal, 3)]);

        $emaUp = $this->settings->useEmaFilter ? self::gt($this->df['close'], ml::ema($this->df['close'], $this->settings->emaPeriod)) : array_fill(0, $n, true);
        $emaDown = $this->settings->useEmaFilter ? self::lt($this->df['close'], ml::ema($this->df['close'], $this->settings->emaPeriod)) : array_fill(0, $n, true);
        $smaUp = $this->settings->useSmaFilter ? self::gt($this->df['close'], ml::sma($this->df['close'], $this->settings->smaPeriod)) : array_fill(0, $n, true);
        $smaDown = $this->settings->useSmaFilter ? self::lt($this->df['close'], ml::sma($this->df['close'], $this->settings->smaPeriod)) : array_fill(0, $n, true);

        $isBuySignal = self::andAll([self::eq($signal, Direction::LONG->value), $emaUp, $smaUp]);
        $isSellSignal = self::andAll([self::eq($signal, Direction::SHORT->value), $emaDown, $smaDown]);
        $isLastSignalBuy = self::andAll([self::eq(Utils::shift($signal, 4, $signal[0] ?? 0), Direction::LONG->value), Utils::shift($emaUp, 4, $emaUp[0] ?? true), Utils::shift($smaUp, 4, $smaUp[0] ?? true)]);
        $isLastSignalSell = self::andAll([self::eq(Utils::shift($signal, 4, $signal[0] ?? 0), Direction::SHORT->value), Utils::shift($emaDown, 4, $emaDown[0] ?? true), Utils::shift($smaDown, 4, $smaDown[0] ?? true)]);
        $isNewBuySignal = self::andAll([$isBuySignal, $isDifferentSignalType]);
        $isNewSellSignal = self::andAll([$isSellSignal, $isDifferentSignalType]);

        $kFilter = $this->filterSettings->kernelFilter;
        $this->yhat1 = kernels::rationalQuadratic($src, $kFilter->lookbackWindow, $kFilter->relativeWeight, $kFilter->regressionLevel);
        $this->yhat2 = kernels::gaussian($src, $kFilter->lookbackWindow - $kFilter->crossoverLag, $kFilter->regressionLevel);
        $wasBearishRate = self::gt(Utils::shift($this->yhat1, 2, $this->yhat1[0] ?? 0.0), Utils::shift($this->yhat1, 1, $this->yhat1[0] ?? 0.0));
        $wasBullishRate = self::lt(Utils::shift($this->yhat1, 2, $this->yhat1[0] ?? 0.0), Utils::shift($this->yhat1, 1, $this->yhat1[0] ?? 0.0));
        $isBearishRate = self::gt(Utils::shift($this->yhat1, 1, $this->yhat1[0] ?? 0.0), $this->yhat1);
        $isBullishRate = self::lt(Utils::shift($this->yhat1, 1, $this->yhat1[0] ?? 0.0), $this->yhat1);
        $isBearishChange = self::andAll([$isBearishRate, $wasBullishRate]);
        $isBullishChange = self::andAll([$isBullishRate, $wasBearishRate]);
        $isBullishSmooth = self::ge($this->yhat2, $this->yhat1);
        $isBearishSmooth = self::le($this->yhat2, $this->yhat1);
        $isBullish = $isBearish = array_fill(0, $n, true);
        if ($kFilter) {
            $isBullish = $kFilter->useKernelSmoothing ? $isBullishSmooth : $isBullishRate;
            $isBearish = $kFilter->useKernelSmoothing ? $isBearishSmooth : $isBearishRate;
        }

        $startLongTrade = self::andAll([$isNewBuySignal, $isBullish, $emaUp, $smaUp]);
        $startShortTrade = self::andAll([$isNewSellSignal, $isBearish, $emaDown, $smaDown]);

        $barsSinceRedEntry = Utils::barsSince($startShortTrade);
        $barsSinceRedExit = Utils::barsSince($isBullishChange);
        $barsSinceGreenEntry = Utils::barsSince($startLongTrade);
        $barsSinceGreenExit = Utils::barsSince($isBearishChange);
        $isValidShortExit = self::gt($barsSinceRedExit, $barsSinceRedEntry);
        $isValidLongExit = self::gt($barsSinceGreenExit, $barsSinceGreenEntry);
        $endLongTradeDynamic = self::andAll([$isBullishChange, Utils::shift($isValidLongExit, 1, $isValidLongExit[0] ?? false)]);
        $endShortTradeDynamic = self::andAll([$isBearishChange, Utils::shift($isValidShortExit, 1, $isValidShortExit[0] ?? false)]);

        $endLongTradeStrict = self::andAll([
            self::orAny([
                self::andAll([$isHeldFourBars, $isLastSignalBuy]),
                self::andAll([$isHeldLessThanFourBars, $isNewSellSignal, $isLastSignalBuy])
            ]),
            Utils::shift($startLongTrade, 4, false)
        ]);
        $endShortTradeStrict = self::andAll([
            self::orAny([
                self::andAll([$isHeldFourBars, $isLastSignalSell]),
                self::andAll([$isHeldLessThanFourBars, $isNewBuySignal, $isLastSignalSell])
            ]),
            Utils::shift($startShortTrade, 4, false)
        ]);

        $isDynamicExitValid = array_fill(0, $n, (!$this->settings->useEmaFilter && !$this->settings->useSmaFilter && !$kFilter->useKernelSmoothing));
        $endLongTrade = self::orAny([
            self::andAll([array_fill(0, $n, $this->settings->useDynamicExits), $isDynamicExitValid, $endLongTradeDynamic]),
            $endLongTradeStrict
        ]);
        $endShortTrade = self::orAny([
            self::andAll([array_fill(0, $n, $this->settings->useDynamicExits), $isDynamicExitValid, $endShortTradeDynamic]),
            $endShortTradeStrict
        ]);

        $this->df['prediction'] = $prediction;
        $this->df['signal'] = $signal;
        $this->df['barsHeld'] = $barsHeld;
        $this->df['isEarlySignalFlip'] = $isEarlySignalFlip;
        $this->df['isLastSignalBuy'] = $isLastSignalBuy;
        $this->df['isLastSignalSell'] = $isLastSignalSell;
        $this->df['isNewBuySignal'] = $isNewBuySignal;
        $this->df['isNewSellSignal'] = $isNewSellSignal;
        $this->df['startLongTrade'] = self::mask($this->df['low'], $startLongTrade);
        $this->df['startShortTrade'] = self::mask($this->df['high'], $startShortTrade);
        $this->df['endLongTrade'] = self::mask($this->df['high'], $endLongTrade);
        $this->df['endShortTrade'] = self::mask($this->df['low'], $endShortTrade);
    }

    public function data(): array
    {
        return $this->df;
    }

    public function dump(string $path): void
    {
        $fp = fopen($path, 'w');
        $n = count($this->df['close']);
        $headers = array_keys($this->df);
        fputcsv($fp, $headers);
        for ($i = 0; $i < $n; $i++) {
            $row = [];
            foreach ($headers as $h) $row[] = $this->df[$h][$i] ?? null;
            fputcsv($fp, $row);
        }
        fclose($fp);
    }

    private static function avg3(array $a, array $b, array $c): array
    {
        $n = count($a);
        $o = [];
        for ($i = 0; $i < $n; $i++) $o[$i] = ($a[$i] + $b[$i] + $c[$i]) / 3.0;
        return $o;
    }

    private static function avg4(array $o, array $h, array $l, array $c): array
    {
        $n = count($o);
        $out = [];
        for ($i = 0; $i < $n; $i++) $out[$i] = ($o[$i] + $h[$i] + $l[$i] + $c[$i]) / 4.0;
        return $out;
    }

    private static function andAll(array $boolArrays): array
    {
        $n = count($boolArrays[0] ?? []);
        $out = array_fill(0, $n, true);
        foreach ($boolArrays as $arr) {
            for ($i = 0; $i < $n; $i++) $out[$i] = $out[$i] && (bool)($arr[$i] ?? false);
        }
        return $out;
    }

    private static function orAny(array $boolArrays): array
    {
        $n = count($boolArrays[0] ?? []);
        $out = array_fill(0, $n, false);
        foreach ($boolArrays as $arr) {
            for ($i = 0; $i < $n; $i++) $out[$i] = $out[$i] || (bool)($arr[$i] ?? false);
        }
        return $out;
    }

    private static function gt(array $a, array $b): array
    {
        $n = count($a);
        $o = [];
        for ($i = 0; $i < $n; $i++) $o[$i] = ($a[$i] ?? 0) > ($b[$i] ?? 0);
        return $o;
    }
    private static function lt(array $a, array $b): array
    {
        $n = count($a);
        $o = [];
        for ($i = 0; $i < $n; $i++) $o[$i] = ($a[$i] ?? 0) < ($b[$i] ?? 0);
        return $o;
    }
    private static function ge(array $a, array $b): array
    {
        $n = count($a);
        $o = [];
        for ($i = 0; $i < $n; $i++) $o[$i] = ($a[$i] ?? 0) >= ($b[$i] ?? 0);
        return $o;
    }
    private static function le(array $a, array $b): array
    {
        $n = count($a);
        $o = [];
        for ($i = 0; $i < $n; $i++) $o[$i] = ($a[$i] ?? 0) <= ($b[$i] ?? 0);
        return $o;
    }
    private static function eq(array $a, int $value): array
    {
        $n = count($a);
        $o = [];
        for ($i = 0; $i < $n; $i++) $o[$i] = (int)($a[$i] ?? 0) === $value;
        return $o;
    }
    private static function mask(array $base, array $bools): array
    {
        $n = count($base);
        $o = array_fill(0, $n, null);
        for ($i = 0; $i < $n; $i++) $o[$i] = ($bools[$i] ?? false) ? $base[$i] : null;
        return $o;
    }

    public function getSignals(): array
    {
        return [
            'signal' => $this->df['signal'] ?? [],
            'startLongTrade' => $this->df['startLongTrade'] ?? [],
            'startShortTrade' => $this->df['startShortTrade'] ?? [],
            'endLongTrade' => $this->df['endLongTrade'] ?? [],
            'endShortTrade' => $this->df['endShortTrade'] ?? [],
            'isNewBuySignal' => $this->df['isNewBuySignal'] ?? [],
            'isNewSellSignal' => $this->df['isNewSellSignal'] ?? [],
        ];
    }

}



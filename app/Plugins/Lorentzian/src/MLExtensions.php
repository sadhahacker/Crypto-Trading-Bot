<?php

namespace App\Plugins\Lorentzian\src;

class MLExtensions
{
    public static function normalize(array $src, float $rangeMin = 0.0, float $rangeMax = 1.0): array
    {
        $min = min($src);
        $max = max($src);
        $den = max($max - $min, 1e-10);
        return array_map(fn($v) => $rangeMin + ($rangeMax - $rangeMin) * (($v - $min) / $den), $src);
    }

    public static function rescale(array $src, float $oldMin, float $oldMax, float $newMin = 0.0, float $newMax = 1.0): array
    {
        $den = max($oldMax - $oldMin, 1e-10);
        return array_map(fn($v) => $newMin + ($newMax - $newMin) * (($v - $oldMin) / $den), $src);
    }

    public static function sma(array $src, int $period): array
    {
        $n = count($src);
        $out = array_fill(0, $n, 0.0);
        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sum += $src[$i];
            if ($i >= $period) $sum -= $src[$i - $period];
            $out[$i] = ($i + 1 >= $period) ? ($sum / $period) : ($sum / max(1, $i + 1));
        }
        return $out;
    }

    public static function ema(array $src, int $period): array
    {
        $n = count($src);
        $out = array_fill(0, $n, 0.0);
        if ($n === 0) return $out;
        $k = 2.0 / (max(1, $period) + 1.0);
        $out[0] = $src[0];
        for ($i = 1; $i < $n; $i++) {
            $out[$i] = $src[$i] * $k + $out[$i - 1] * (1 - $k);
        }
        return $out;
    }

    public static function rsi(array $src, int $length): array
    {
        $n = count($src);
        $rsis = array_fill(0, $n, 50.0);
        if ($n < 2) return $rsis;
        $gains = array_fill(0, $n, 0.0);
        $losses = array_fill(0, $n, 0.0);
        for ($i = 1; $i < $n; $i++) {
            $delta = $src[$i] - $src[$i - 1];
            $gains[$i] = max($delta, 0.0);
            $losses[$i] = max(-$delta, 0.0);
        }
        $avgGain = self::sma($gains, $length);
        $avgLoss = self::sma($losses, $length);
        for ($i = 0; $i < $n; $i++) {
            $den = max($avgLoss[$i], 1e-10);
            $rs = $avgGain[$i] / $den;
            $rsis[$i] = 100.0 - (100.0 / (1.0 + $rs));
        }
        return $rsis;
    }

    public static function cci(array $high, array $low, array $close, int $length): array
    {
        $n = count($close);
        $tp = [];
        for ($i = 0; $i < $n; $i++) $tp[$i] = ($high[$i] + $low[$i] + $close[$i]) / 3.0;
        $sma = self::sma($tp, $length);
        $md = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $n; $i++) {
            $start = max(0, $i - $length + 1);
            $count = $i - $start + 1;
            $sum = 0.0;
            for ($j = $start; $j <= $i; $j++) $sum += abs($tp[$j] - $sma[$i]);
            $md[$i] = $sum / max(1, $count);
        }
        $cci = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $n; $i++) {
            $den = max($md[$i] * 0.015, 1e-10);
            $cci[$i] = ($tp[$i] - $sma[$i]) / $den;
        }
        return $cci;
    }

    public static function atr(array $high, array $low, array $close, int $length): array
    {
        $n = count($close);
        $tr = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $n; $i++) {
            if ($i === 0) {
                $tr[$i] = $high[$i] - $low[$i];
            } else {
                $tr[$i] = max(
                    $high[$i] - $low[$i],
                    max(abs($high[$i] - $close[$i - 1]), abs($low[$i] - $close[$i - 1]))
                );
            }
        }
        return self::ema($tr, $length);
    }

    public static function adx(array $high, array $low, array $close, int $length): array
    {
        $n = count($close);
        $plusDM = array_fill(0, $n, 0.0);
        $minusDM = array_fill(0, $n, 0.0);
        for ($i = 1; $i < $n; $i++) {
            $upMove = $high[$i] - $high[$i - 1];
            $downMove = $low[$i - 1] - $low[$i];
            $plusDM[$i] = ($upMove > $downMove && $upMove > 0) ? $upMove : 0.0;
            $minusDM[$i] = ($downMove > $upMove && $downMove > 0) ? $downMove : 0.0;
        }
        $atr = self::atr($high, $low, $close, $length);
        $plusDI = array_fill(0, $n, 0.0);
        $minusDI = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $n; $i++) {
            $den = max($atr[$i], 1e-10);
            $plusDI[$i] = 100.0 * (self::ema($plusDM, $length)[$i] / $den);
            $minusDI[$i] = 100.0 * (self::ema($minusDM, $length)[$i] / $den);
        }
        $dx = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $n; $i++) {
            $sum = $plusDI[$i] + $minusDI[$i];
            $den = max($sum, 1e-10);
            $dx[$i] = 100.0 * (abs($plusDI[$i] - $minusDI[$i]) / $den);
        }
        return self::ema($dx, $length);
    }

    public static function n_rsi(array $src, int $n1, int $n2): array
    {
        $rsi = self::rsi($src, $n1);
        $ema = self::ema($rsi, $n2);
        return self::rescale($ema, 0.0, 100.0);
    }

    public static function n_cci(array $high, array $low, array $close, int $n1, int $n2): array
    {
        $cci = self::cci($high, $low, $close, $n1);
        $ema = self::ema($cci, $n2);
        return self::normalize($ema);
    }

    public static function n_wt(array $src, int $n1 = 10, int $n2 = 11): array
    {
        $ema1 = self::ema($src, $n1);
        $abs = [];
        $n = count($src);
        for ($i = 0; $i < $n; $i++) $abs[$i] = abs($src[$i] - $ema1[$i]);
        $ema2 = self::ema($abs, $n1);
        $ci = [];
        for ($i = 0; $i < $n; $i++) $ci[$i] = ($src[$i] - $ema1[$i]) / max(0.015 * $ema2[$i], 1e-10);
        $wt1 = self::ema($ci, $n2);
        $wt2 = self::sma($wt1, 4);
        $out = [];
        for ($i = 0; $i < $n; $i++) $out[$i] = $wt1[$i] - $wt2[$i];
        return self::normalize($out);
    }

    public static function n_adx(array $high, array $low, array $close, int $n1): array
    {
        $adx = self::adx($high, $low, $close, $n1);
        return self::rescale($adx, 0.0, 100.0);
    }

    public static function regime_filter(array $src, array $high, array $low, bool $useRegimeFilter, float $threshold): array
    {
        $n = count($src);
        if (!$useRegimeFilter) return array_fill(0, $n, true);

        $value1 = array_fill(0, $n, 0.0);
        $value2 = array_fill(0, $n, 0.0);
        $klmf = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $n; $i++) {
            if (($high[$i] - $low[$i]) == 0.0) continue;
            $value1[$i] = 0.2 * ($src[$i] - ($src[$i - 1] ?? $src[0])) + 0.8 * ($value1[$i - 1] ?? 0.0);
            $value2[$i] = 0.1 * ($high[$i] - $low[$i]) + 0.8 * ($value2[$i - 1] ?? 0.0);
        }
        $omega = [];
        for ($i = 0; $i < $n; $i++) $omega[$i] = ($value2[$i] != 0.0) ? abs($value1[$i] / $value2[$i]) : 0.0;
        $alpha = [];
        for ($i = 0; $i < $n; $i++) $alpha[$i] = ((-($omega[$i] ** 2)) + sqrt(($omega[$i] ** 4) + 16 * ($omega[$i] ** 2))) / 8.0;
        for ($i = 0; $i < $n; $i++) $klmf[$i] = $alpha[$i] * $src[$i] + (1 - $alpha[$i]) * ($klmf[$i - 1] ?? 0.0);
        $absCurveSlope = [];
        $prev = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $absCurveSlope[$i] = abs($klmf[$i] - $prev);
            $prev = $klmf[$i];
        }
        $ema = self::ema($absCurveSlope, 200);
        $flags = array_fill(0, $n, false);
        for ($i = 0; $i < $n; $i++) {
            $den = $ema[$i] != 0.0 ? $ema[$i] : 1e-10;
            $normalized = ($absCurveSlope[$i] - $ema[$i]) / $den;
            $flags[$i] = $normalized >= $threshold;
        }
        return $flags;
    }

    public static function filter_adx(array $src, array $high, array $low, int $adxThreshold, bool $useAdxFilter, int $length = 14): array
    {
        $n = count($src);
        if (!$useAdxFilter) return array_fill(0, $n, true);
        $adx = self::adx($high, $low, $src, $length);
        $flags = [];
        foreach ($adx as $v) $flags[] = $v > $adxThreshold;
        return $flags;
    }

    public static function filter_volatility(array $high, array $low, array $close, bool $useVolatilityFilter, int $minLength = 1, int $maxLength = 10): array
    {
        $n = count($close);
        if (!$useVolatilityFilter) return array_fill(0, $n, true);
        $recentAtr = self::atr($high, $low, $close, $minLength);
        $historicalAtr = self::atr($high, $low, $close, $maxLength);
        $flags = [];
        for ($i = 0; $i < $n; $i++) $flags[$i] = $recentAtr[$i] > $historicalAtr[$i];
        return $flags;
    }
}



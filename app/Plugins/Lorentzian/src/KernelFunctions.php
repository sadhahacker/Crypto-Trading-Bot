<?php

namespace App\Plugins\Lorentzian\src;

class KernelFunctions
{
    public static function rationalQuadratic(array $src, int $lookback, float $relativeWeight, int $startAtBar): array
    {
        $n = count($src);
        $currentWeight = array_fill(0, $n, 0.0);
        $cumulativeWeight = 0.0;
        for ($i = 0; $i <= $startAtBar + 1; $i++) {
            $y = self::shiftSeries($src, $i, 0.0);
            $w = pow(1 + ($i * $i / ($lookback * $lookback * 2.0 * $relativeWeight)), -$relativeWeight);
            for ($j = 0; $j < $n; $j++) $currentWeight[$j] += $y[$j] * $w;
            $cumulativeWeight += $w;
        }
        $val = array_map(fn($v) => $cumulativeWeight > 0 ? $v / $cumulativeWeight : 0.0, $currentWeight);
        for ($k = 0; $k <= min($startAtBar, $n - 1); $k++) $val[$k] = 0.0;
        return $val;
    }

    public static function gaussian(array $src, int $lookback, int $startAtBar): array
    {
        $n = count($src);
        $currentWeight = array_fill(0, $n, 0.0);
        $cumulativeWeight = 0.0;
        for ($i = 0; $i <= $startAtBar + 1; $i++) {
            $y = self::shiftSeries($src, $i, 0.0);
            $w = exp(-($i * $i) / (2.0 * $lookback * $lookback));
            for ($j = 0; $j < $n; $j++) $currentWeight[$j] += $y[$j] * $w;
            $cumulativeWeight += $w;
        }
        $val = array_map(fn($v) => $cumulativeWeight > 0 ? $v / $cumulativeWeight : 0.0, $currentWeight);
        for ($k = 0; $k <= min($startAtBar, $n - 1); $k++) $val[$k] = 0.0;
        return $val;
    }

    private static function shiftSeries(array $arr, int $by, float $fill): array
    {
        $n = count($arr);
        $fillArr = array_fill(0, min($by, $n), $fill);
        $slice = array_slice($arr, 0, $n - min($by, $n));
        return array_merge($fillArr, $slice);
    }
}



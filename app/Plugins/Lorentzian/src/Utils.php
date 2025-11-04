<?php

namespace App\Plugins\Lorentzian\src;

class Utils
{
    public static function shift(array $arr, int $len, float $fillValue = 0.0): array
    {
        $n = count($arr);
        if ($len <= 0) {
            $len = abs($len);
            $prefix = array_slice($arr, $len);
            $fill = array_fill(0, min($len, $n), $fillValue);
            return array_merge($prefix, $fill);
        }
        $fill = array_fill(0, min($len, $n), $fillValue);
        $slice = array_slice($arr, 0, $n - min($len, $n));
        return array_merge($fill, $slice);
    }

    public static function barsSince(array $boolSeries): array
    {
        $size = count($boolSeries);
        $val = array_fill(0, $size, NAN);
        $c = NAN;
        for ($i = 0; $i < $size; $i++) {
            if (!empty($boolSeries[$i])) {
                $c = 0;
            } else {
                if ($c >= 0) $c += 1;
            }
            $val[$i] = $c;
        }
        return $val;
    }
}



<?php

namespace App\Plugins\Lorentzian\src;

class KernelFilter
{
    public bool $useKernelSmoothing = false;
    public int $lookbackWindow = 8;
    public float $relativeWeight = 8.0;
    public int $regressionLevel = 25;
    public int $crossoverLag = 2;
}



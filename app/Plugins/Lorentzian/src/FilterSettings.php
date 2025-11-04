<?php

namespace App\Plugins\Lorentzian\src;

class FilterSettings
{
    public bool $useVolatilityFilter = false;
    public bool $useRegimeFilter = false;
    public bool $useAdxFilter = false;
    public float $regimeThreshold = 0.0;
    public int $adxThreshold = 0;

    public KernelFilter $kernelFilter;

    public function __construct(?KernelFilter $kernelFilter = null)
    {
        $this->kernelFilter = $kernelFilter ?? new KernelFilter();
    }
}



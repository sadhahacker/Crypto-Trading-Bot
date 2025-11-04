<?php

namespace App\Plugins\Lorentzian\src;

enum Direction: int
{
    case LONG = 1;
    case SHORT = -1;
    case NEUTRAL = 0;
}

class Feature
{
    public string $type;
    public int $param1;
    public int $param2;

    public function __construct(string $type, int $param1, int $param2)
    {
        $this->type = $type;
        $this->param1 = $param1;
        $this->param2 = $param2;
    }
}

class KernelFilter
{
    public bool $useKernelSmoothing = false;
    public int $lookbackWindow = 8;
    public float $relativeWeight = 8.0;
    public int $regressionLevel = 25;
    public int $crossoverLag = 2;
}

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

class Filter
{
    public array $volatility = [];
    public array $regime = [];
    public array $adx = [];
}

class Settings
{
    /** @var array<float> */
    public array $source;
    public int $neighborsCount = 8;
    public int $maxBarsBack = 2000;
    public bool $useDynamicExits = false;
    public bool $useEmaFilter = false;
    public int $emaPeriod = 200;
    public bool $useSmaFilter = false;
    public int $smaPeriod = 200;

    public function __construct(array $attrs)
    {
        $this->source = $attrs['source'] ?? [];
        if (isset($attrs['neighborsCount'])) $this->neighborsCount = (int)$attrs['neighborsCount'];
        if (isset($attrs['maxBarsBack'])) $this->maxBarsBack = (int)$attrs['maxBarsBack'];
        if (isset($attrs['useDynamicExits'])) $this->useDynamicExits = (bool)$attrs['useDynamicExits'];
        if (isset($attrs['useEmaFilter'])) $this->useEmaFilter = (bool)$attrs['useEmaFilter'];
        if (isset($attrs['emaPeriod'])) $this->emaPeriod = (int)$attrs['emaPeriod'];
        if (isset($attrs['useSmaFilter'])) $this->useSmaFilter = (bool)$attrs['useSmaFilter'];
        if (isset($attrs['smaPeriod'])) $this->smaPeriod = (int)$attrs['smaPeriod'];
    }
}



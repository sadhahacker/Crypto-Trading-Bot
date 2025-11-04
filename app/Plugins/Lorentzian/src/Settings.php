<?php

namespace App\Plugins\Lorentzian\src;

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



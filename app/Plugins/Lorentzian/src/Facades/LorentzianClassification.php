<?php

namespace App\Plugins\Lorentzian\src\Facades;

use App\Plugins\Lorentzian\src\Classifier\Classifier;
use Illuminate\Support\Facades\Facade;
use AdvancedTa\LorentzianClassification\Services\LorentzianClassificationManager;

/**
 * @method static Classifier make(array $data, array $features = null, array $settings = null, array $filterSettings = null)
 */
class LorentzianClassification extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LorentzianClassificationManager::class;
    }
}



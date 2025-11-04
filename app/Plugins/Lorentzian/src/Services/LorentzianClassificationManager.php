<?php

namespace App\Plugins\Lorentzian\src\Services;

use App\Plugins\Lorentzian\src\Classifier\Classifier;

class LorentzianClassificationManager
{
    public function make(array $data, array $features = null, array $settings = null, array $filterSettings = null): Classifier
    {
        return new Classifier($data, $features, $settings, $filterSettings);
    }
}



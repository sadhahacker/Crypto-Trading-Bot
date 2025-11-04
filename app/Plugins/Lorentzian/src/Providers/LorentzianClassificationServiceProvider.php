<?php

namespace App\Plugins\Lorentzian\src\Providers;

use Illuminate\Support\ServiceProvider;
use AdvancedTa\LorentzianClassification\Services\LorentzianClassificationManager;

class LorentzianClassificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LorentzianClassificationManager::class, function () {
            return new LorentzianClassificationManager();
        });
    }

    public function boot(): void
    {
        // No boot-time actions required
    }
}



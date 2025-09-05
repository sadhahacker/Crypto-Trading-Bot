<?php

use App\Http\Controllers\Trading\ExecuteTradeController;
use App\Http\Controllers\Trading\IndicatorController;
use Illuminate\Support\Facades\Route;

Route::get('/{any}', function () {
    return view('welcome'); // Your React entry blade
})->where('any', '.*');

<?php

use App\Http\Controllers\Trading\ExecuteTradeController;
use App\Http\Controllers\Trading\IndicatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('trade');
});

Route::post('/trade/manual', [ExecuteTradeController::class, 'manualTradeApi']);

Route::get('start', [IndicatorController::class, 'start']);
Route::get('stop', [IndicatorController::class, 'stop']);

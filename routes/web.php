<?php

use App\Http\Controllers\Admin\ArbitrageController;
use App\Http\Controllers\Admin\BacktestController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExchangeSettingController;
use App\Http\Controllers\Trading\ExecuteTradeController;
use App\Http\Controllers\Trading\IndicatorController;
use Illuminate\Support\Facades\Route;

//Route::get('/{any}', function () {
//    return view('welcome');
//})->where('any', '.*');


Route::get('/dashboard', [DashboardController::class, 'home'])->name('dashboard');
Route::get('settings', [DashboardController::class, 'settings']);
Route::get('/logout',[])->name('logout');
Route::get('exchange/settings', [ExchangeSettingController::class, 'index']);

Route::post('exchange-settings', [ExchangeSettingController::class, 'update']);


Route::resource('arbitrage', ArbitrageController::class);
Route::get('signals/tester', [BacktestController::class, 'index']);
Route::get('signals/tester/run', [BacktestController::class, 'runTesting']);


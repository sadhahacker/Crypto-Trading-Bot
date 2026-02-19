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
Route::get('profile', [DashboardController::class, 'profile'])->name('profile');
Route::get('/logout',[])->name('logout');
Route::get('exchange/settings', [ExchangeSettingController::class, 'index']);

Route::post('exchange-settings', [ExchangeSettingController::class, 'update']);


Route::resource('arbitrage', ArbitrageController::class);
Route::get('signals/tester', [BacktestController::class, 'legacySignalTester']);
Route::get('backtest', [BacktestController::class, 'index']);
Route::get('backtest/strategy/{strategy}', [BacktestController::class, 'strategy']);
Route::get('historical-data', [BacktestController::class, 'historicalData']);
Route::post('generate/backTestData', [BacktestController::class, 'generateBackTestData']);
Route::get('backtest/files', [BacktestController::class, 'getGeneratedFiles']);
Route::get('backtest/datasets', [BacktestController::class, 'listDatasets']);
Route::get('backtest/files/{backtestData}/download', [BacktestController::class, 'downloadGeneratedFile']);
Route::post('backtest/run', [BacktestController::class, 'runBacktest']);
Route::get('test', [BacktestController::class, 'testRunner']);

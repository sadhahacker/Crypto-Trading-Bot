<?php

use App\Http\Controllers\Admin\BotsController;
use App\Http\Controllers\Trading\TradeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Account details
Route::get('/account/details', [TradeController::class, 'getAccountDetails']);

// Bots management
Route::get('/bots', [BotsController::class, 'index']);
Route::post('/bots/{bot}/toggle', [BotsController::class, 'toggleBot']);

// List all coins for a bot
Route::get('/bots/{bot}/coins', [BotsController::class, 'botCoins']);

// Add a coin to a bot
Route::post('/bots/{bot}/coins', [BotsController::class, 'addCoin']);

// Signals for a coin
Route::get('/bots/{bot}/coins/{coin}/signals', [BotsController::class, 'getSignals']);
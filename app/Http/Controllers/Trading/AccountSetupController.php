<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccountSetupController extends Controller
{
    protected $exchange;

    public function __construct()
    {
        $exchangeClass = env('EXCHANGE_NAME');
        // Ensure the class exists
        if (!class_exists("\\ccxt\\$exchangeClass")) {
            throw new \Exception("Exchange class \\ccxt\\$exchangeClass not found.");
        }

        $exchangeClass = "\\ccxt\\$exchangeClass";
        $this->exchange = new $exchangeClass([
            'apiKey' => env('EXCHANGE_API_KEY'),
            'secret' => env('EXCHANGE_SECRET'),
            'options' => [
                'defaultType' => 'future', // For Binance Futures
            ],
        ]);
    }

    public function getExchange(){
        return $this->exchange;
    }
}

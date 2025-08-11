<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccountSetupController extends Controller
{
    protected $exchange;

    public function __construct()
    {
        $exchangeName = config('trading.exchange_name'); // e.g., "binance"
        $fullClass = "\\ccxt\\{$exchangeName}";

        if (!class_exists($fullClass)) {
            throw new \Exception("Exchange class $fullClass not found.");
        }

        $this->exchange = new $fullClass([
            'apiKey' => config('trading.api_key'),
            'secret' => config('trading.api_secret'),
            'options' => config('trading.options'),
        ]);
    }

    public function getExchange()
    {
        return $this->exchange;
    }
}

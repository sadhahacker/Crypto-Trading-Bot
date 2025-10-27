<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\ExchangeSetting;
use Illuminate\Http\Request;

class AccountSetupController extends Controller
{
    protected $exchange;

    public function __construct()
    {
        $settings = ExchangeSetting::first();

        // Fallbacks if settings table empty
        $exchangeName = $settings->exchange_name ?? config('trading.exchange_name', 'binance');
        $fullClass = "\\ccxt\\{$exchangeName}";

        if (!class_exists($fullClass)) {
            throw new \Exception("Exchange class $fullClass not found.");
        }

        $this->exchange = new $fullClass([
            'apiKey' => $settings->api_key ?? config('trading.api_key'),
            'secret' => $settings->api_secret ?? config('trading.api_secret'),
            'enableRateLimit' => true,
            'options' => [
                'defaultType' => $settings->default_type ?? config('trading.options.defaultType', 'future'),
            ],
        ]);

        if (method_exists($this->exchange, 'load_time_difference')) {
            $this->exchange->load_time_difference(); // Sync local time with exchange server
        }
    }

    public function getExchange()
    {
        return $this->exchange;
    }
}

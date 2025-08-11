<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Exchange
    |--------------------------------------------------------------------------
    |
    | This is the default exchange you want to use.
    | Must match the CCXT exchange class name exactly (e.g., binance, kucoin, bybit).
    |
    */
    'exchange_name' => env('EXCHANGE_NAME', 'binance'),

    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    |
    | Set your API credentials for the chosen exchange.
    |
    */
    'api_key' => env('EXCHANGE_API_KEY', ''),
    'api_secret' => env('EXCHANGE_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Exchange Options
    |--------------------------------------------------------------------------
    |
    | Additional CCXT options.
    | Example: 'defaultType' => 'future' for Binance Futures
    |
    */
    'options' => [
        'defaultType' => env('EXCHANGE_DEFAULT_TYPE', 'future'),
    ],

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Exchange
    |--------------------------------------------------------------------------
    |
    | The exchange you want to use by default for all trading operations.
    | This must match the exact CCXT exchange class name (e.g., 'binance',
    | 'kucoin', 'bybit'). You can override this value in the .env file:
    |
    | EXCHANGE_NAME=binance
    |
    */
    'exchange_name' => env('EXCHANGE_NAME', 'binance'),

    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    |
    | Your API key and secret for the selected exchange.
    | These credentials are required for authentication and
    | must have the appropriate permissions (trading, futures, etc.).
    |
    | Example .env values:
    | EXCHANGE_API_KEY=your_api_key_here
    | EXCHANGE_SECRET=your_api_secret_here
    |
    */
    'api_key' => env('EXCHANGE_API_KEY', 'uZSrTKZnWYLwvGBixlZfyfaf9ioTSA9KdUZ3pDl30C8eqta4KELNV1yCPY8Xgcsx'),
    'api_secret' => env('EXCHANGE_SECRET', '22vfYdgKN7QiyiswGbc1vOcZLwWBas3WdRo2VgGbcKS1FdXtRWpdxN2HzmpbgPh7'),

    /*
    |--------------------------------------------------------------------------
    | Risk Management Settings
    |--------------------------------------------------------------------------
    |
    | These values define how Stop Loss and Take Profit levels are calculated.
    |
    | stoploss_from_account_balance:
    |     - Percentage of your total account balance you're willing to risk per trade.
    |     - Example: 0.23 means 23% of your total account balance.
    |
    | takeprofit_from_account_balance:
    |     - Percentage of your account balance as profit target per trade.
    |     - Example: 0.30 means 30% of your account balance.
    |
    | stoploss_from_coin:
    |     - Percentage price drop from the entry price at which to exit the position.
    |     - Example: 0.03 means exit if the price drops by 3%.
    |
    | takeprofit_from_coin:
    |     - Percentage price increase from the entry price at which to take profit.
    |     - Example: 0.023 means take profit if the price rises by 2.3%.
    |
    */
    'stoploss_from_account_balance' => 0.23,
    'takeprofit_from_account_balance' => 0.30,
    'stoploss_from_coin' => 0.03,
    'takeprofit_from_coin' => 0.023,

    /*
    |--------------------------------------------------------------------------
    | Exchange Options
    |--------------------------------------------------------------------------
    |
    | Additional CCXT options to customize how the exchange is used.
    | For example:
    |   - 'defaultType' => 'future' will make Binance trade in Futures mode.
    |
    | You can override this in the .env file:
    | EXCHANGE_DEFAULT_TYPE=future
    |
    */
    'options' => [
        'defaultType' => env('EXCHANGE_DEFAULT_TYPE', 'future'),
    ],

];

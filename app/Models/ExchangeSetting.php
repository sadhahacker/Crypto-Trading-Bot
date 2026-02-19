<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeSetting extends Model
{
    protected $fillable = [
        'exchange_name',
        'api_key',
        'api_secret',
        'stoploss_from_account_balance',
        'takeprofit_from_account_balance',
        'stoploss_from_coin',
        'takeprofit_from_coin',
        'default_type',
        'display_currency',
        'id',
    ];

}

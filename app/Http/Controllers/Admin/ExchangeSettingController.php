<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeSetting;
use Illuminate\Http\Request;

class ExchangeSettingController extends Controller
{

    public function index()
    {
        $settings = ExchangeSetting::first();
        return view('Settings.exchangeSetting', compact('settings'));
    }
    public function update(Request $request)
    {
        $data = $request->validate([
            'exchange_name' => 'required|string',
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
            'stoploss_from_account_balance' => 'numeric',
            'takeprofit_from_account_balance' => 'numeric',
            'stoploss_from_coin' => 'numeric',
            'takeprofit_from_coin' => 'numeric',
            'default_type' => 'string',
        ]);

        ExchangeSetting::updateOrCreate(['id' => 1], $data);

        return successResponse('Exchange settings updated successfully.');
    }
}

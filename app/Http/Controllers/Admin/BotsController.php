<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Trading\TradeController;
use App\Models\BotConfiguration;
use App\Plugins\LorentzianClassification\ScriptsRunner;
use Illuminate\Http\Request;

class BotsController extends Controller
{
    public function index()
    {
        // Return list of bots
        return response()->json([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Lorentzian Classification Bot',
                    'status' => 'running',
                ],
                [
                    'id' => 2,
                    'name' => 'Swing Bot',
                    'status' => 'stopped',
                ],
                [
                    'id' => 3,
                    'name' => 'Arbitrage Bot',
                    'status' => 'running',
                ],
            ]
        ]);
    }

    public function getSignals(Request $request, $botId, $coin)
    {
        $paginate = $request->input('paginate', 10);
        return (new ScriptsRunner())->lorentzianTable()
            ->orderBy('timestamp', 'desc')
            ->paginate($paginate);
    }

    public function botCoins($botId)
    {
        return response()->json([
            'data' => [
                [
                    'symbol'=>'BTCUSDT',
                    'id' => 1,
                ],
                [
                    'symbol'=>'ETHUSDT',
                    'id' => 2,
                ],
            ]
        ]);
    }

    public function addCoin(Request $request, $botId)
    {
        // In a real implementation, you would save this to a database
        $coin = [
            'bot_id' => $botId,
            'symbol' => $request->input('symbol'),
            'id' => rand(100, 999) // Mock ID
        ];

        return response()->json($coin);
    }

    public function toggleBot($botId)
    {
        // In a real implementation, you would update the bot status in the database
        return response()->json([
            'message' => 'Bot status updated successfully',
            'bot_id' => $botId
        ]);
    }
}
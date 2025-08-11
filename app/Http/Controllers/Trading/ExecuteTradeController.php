<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExecuteTradeController extends Controller
{
    protected $tradeController;

    protected $stoplossFromAccountBalance; //23% stop loss from account balance

    protected $takeProfitFromAccountBalance; //30% take profit from account balance

    protected $stoplossFromCoin;

    protected $takeProfitFromCoin;

    public function __construct() {
        $this->tradeController = new TradeController();
        $this->stoplossFromAccountBalance = config('trading.stoploss_from_account_balance');
        $this->takeProfitFromAccountBalance = config('trading.takeprofit_from_account_balance');
        $this->stoplossFromCoin = config('trading.stoploss_from_coin');
        $this->takeProfitFromCoin = config('trading.takeprofit_from_coin');
    }

    public function executeTrade($symbol, $entry_price, $side){

        if(!$this->isOrdersEmpty($symbol)){
            return false;
        }

        $tradeLevels = $this->getTradeLevels($symbol,$entry_price , $this->takeProfitFromAccountBalance, $this->stoplossFromAccountBalance, $side);

        if (!$tradeLevels) {
            return false; // No valid trade levels
        }

        $entryPrice = $tradeLevels['entryPrice'];

        $takeProfitPrice = $tradeLevels['takeProfit'];

        $stopLossPrice = $tradeLevels['stopLoss'];

        $tradeAmount = $this->getTradeAmount($symbol, $entryPrice);

        return $this->order($symbol, $side, $tradeAmount, $entryPrice, $takeProfitPrice, $stopLossPrice);
    }

    public function isOrdersEmpty($symbol)
    {
        $positions = $this->tradeController->getPositions($symbol);
        $orders = $this->tradeController->getOpenOrders($symbol);
        return (empty($orders) && empty($positions));
    }

    protected function getTradeLevels($symbol, $entry_price, $tp, $sl, $side)
    {
        $ticker = $this->tradeController->getTicker($symbol);
        $current_price = isset($ticker['last']) ? $ticker['last'] : null;

        if (!$current_price) {
            return null; // No price data
        }

        // 0.1% distance percentage
        $distancePercentage = 0.1 / 100;

        // Check if entry is close to current (within 0.1%)
        $diffPercentage = abs($current_price - $entry_price) / $entry_price;

        if ($diffPercentage <= $distancePercentage) {
            if (strtolower($side) === 'buy' && $current_price < $entry_price) {
                // Move entry slightly below current
                $entry_price = $current_price - ($current_price * $distancePercentage);
            } elseif (strtolower($side) === 'sell' && $current_price > $entry_price) {
                // Move entry slightly above current
                $entry_price = $current_price + ($current_price * $distancePercentage);
            }
        } else {
            // Entry too far — recalculate from current
            if (strtolower($side) === 'buy') {
                $entry_price = $current_price - ($current_price * $distancePercentage);
            } elseif (strtolower($side) === 'sell') {
                $entry_price = $current_price + ($current_price * $distancePercentage);
            }
        }

        // Calculate TP & SL
        if (strtolower($side) === 'buy') {
            $takeProfit = $entry_price + ($entry_price * $tp);
            $stopLoss   = $entry_price - ($entry_price * $sl);
        } elseif (strtolower($side) === 'sell') {
            $takeProfit = $entry_price - ($entry_price * $tp);
            $stopLoss   = $entry_price + ($entry_price * $sl);
        } else {
            return null;
        }

        return [
            'entryPrice' => $this->tradeController->priceToPrecision($symbol, $entry_price),
            'takeProfit' => $this->tradeController->priceToPrecision($symbol, $takeProfit),
            'stopLoss'   => $this->tradeController->priceToPrecision($symbol, $stopLoss),
        ];
    }

    public function getTradeAmount($symbol, $entry_price)
    {
        // Get USDT balance safely
        $balance = (float) ($this->tradeController->getBalance()['total']['USDT'] ?? 0);

        if ($balance <= 0 || $entry_price <= 0) {
            return 0;
        }

        $leverage = $this->calculateLeverageFromStopsAndProfits();

        // Set leverage once for the symbol
        $this->tradeController->setLeverage($symbol, $leverage + 5);

        // Calculate position size (balance × leverage) / entry price
        $tradeAmount = ($balance * ($leverage)) / $entry_price;

        return $this->tradeController->amountToPrecision($symbol, $tradeAmount);
    }

    protected function calculateLeverageFromStopsAndProfits()
    {
        if ($this->stoplossFromCoin <= 0 || $this->takeProfitFromCoin <= 0) {
            return 0; // avoid division by zero
        }

        // Leverage from stop loss rule
        $leverageFromSL = $this->stoplossFromAccountBalance / $this->stoplossFromCoin;

        // Leverage from take profit rule
        $leverageFromTP = $this->takeProfitFromAccountBalance / $this->takeProfitFromCoin;

        // Use the max leverage to ensure both conditions are respected
        $leverage = max($leverageFromSL, $leverageFromTP);

        return round($leverage, 2);
    }


    public function order($symbol, $side, $tradeAmount, $entry_price, $takeProfitPrice, $stopLossPrice)
    {
        $exitSide = $side === 'buy' ? 'sell' : 'buy';

        $orders = [
            // Main entry order
            [
                'symbol' => $symbol,
                'type' => 'limit',
                'side' => $side,
                'amount' => $tradeAmount,
                'price' => $entry_price,
                'params' => [
                    'marginMode' => 'isolated',
                    'timeInForce' => 'GTC',
                ],
            ],
            // Take profit
            [
                'symbol' => $symbol,
                'type' => 'take_profit_market',
                'side' => $exitSide,
                'amount' => $tradeAmount,
                'price' => null,
                'reduceOnly' => true,
                'params' => [
                    'triggerPrice' => $takeProfitPrice,
                    'marginMode' => 'isolated',
                ],
            ],
            // Stop loss
            [
                'symbol' => $symbol,
                'type' => 'stop_market',
                'side' => $exitSide,
                'amount' => $tradeAmount,
                'price' => null,
                'reduceOnly' => true,
                'params' => [
                    'triggerPrice' => $stopLossPrice,
                    'marginMode' => 'isolated',
                ],
            ],
        ];

        // Place batch orders (requires CCXT Pro)
        $createdOrders = $this->tradeController->createOrders($orders);

        foreach ($createdOrders as $order) {
            if (!$order['status'] || !in_array(strtolower($order['status']), ['open', 'new'])) {
                $this->tradeController->cancelAllOrders($symbol);
                return false;
            }
        }

        return true;
    }
}

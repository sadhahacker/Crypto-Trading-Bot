<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;

class TradeController extends Controller
{
    protected $exchange;

    public function __construct()
    {
        ini_set('memory_limit', '512M');
        $this->exchange = (new AccountSetupController)->getExchange();
    }

    /**
     * Get account details including balance, positions, and performance metrics
     */
    public function getAccountDetails()
    {
        try {
            $balance = $this->getBalance();
            $positions = $this->getPositions('BNBUSDT');

            // Calculate total balance in USDT
            $totalBalance = 0;
            $assets = [];

            if (isset($balance['total'])) {
                foreach ($balance['total'] as $currency => $amount) {
                    if ($amount > 0) {
                        // For simplicity, we're assuming all values are in USDT
                        // In a real implementation, you would convert based on current prices
                        $assets[] = [
                            'currency' => $currency,
                            'amount' => $amount,
                            'value' => $currency === 'USDT' ? $amount : 0 // Simplified
                        ];

                        if ($currency === 'USDT') {
                            $totalBalance += $amount;
                        }
                    }
                }
            }

            // Process positions data
            $activePositions = [];
            if ($positions) {
                foreach ($positions as $position) {
                    if (isset($position['contracts']) && $position['contracts'] > 0) {
                        $activePositions[] = [
                            'symbol' => $position['symbol'] ?? '',
                            'side' => $position['side'] ?? '',
                            'contracts' => $position['contracts'] ?? 0,
                            'entryPrice' => $position['entryPrice'] ?? 0,
                            'markPrice' => $position['markPrice'] ?? 0,
                            'unrealizedPnl' => $position['unrealizedPnl'] ?? 0,
                            'percentage' => $position['percentage'] ?? 0,
                        ];
                    }
                }
            }

            // Extract performance data from balance info
            $performance = [
                'today' => 0,
                'week' => 0,
                'month' => 0,
            ];

            // If we have balance info, use it
            if (isset($balance['info'])) {
                $info = $balance['info'];
                if (isset($info['totalUnrealizedProfit'])) {
                    $performance['today'] = (float)$info['totalUnrealizedProfit'];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'balance' => [
                        'total' => $totalBalance,
                        'assets' => $assets
                    ],
                    'positions' => $activePositions,
                    'performance' => $performance,
                    'raw_balance' => $balance // Include raw data for debugging
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch account details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get markets available on the exchange
     */
    public function getMarkets($params = [])
    {
        return $this->exchange->fetch_markets($params);
    }

    /**
     * Get ticker for a symbol
     */
    public function getTicker($symbol, $params = [])
    {
        if ($this->exchange->has['fetchTicker']) {
            return $this->exchange->fetch_ticker($symbol, $params);
        }
        return null;
    }

    /**
     * Get all tickers
     */
    public function getTickers($symbols = null, $params = [])
    {
        if ($this->exchange->has['fetchTickers']) {
            return $this->exchange->fetch_tickers($symbols, $params);
        }
        return null;
    }

    /**
     * Get order book for a symbol
     */
    public function getOrderBook($symbol, $limit = null, $params = [])
    {
        return $this->exchange->fetch_order_book($symbol, $limit, $params);
    }

    /**
     * Get trades for a symbol
     */
    public function getTrades($symbol, $since = null, $limit = null, $params = [])
    {
        if ($this->exchange->has['fetchTrades']) {
            return $this->exchange->fetch_trades($symbol, $since, $limit, $params);
        }
        return null;
    }


    public function getPositions($symbol = null, $params = [])
    {
        if ($this->exchange->has['fetchPositions']) {
            return $this->exchange->fetch_positions([$symbol], $params);
        }
        return null;
    }

    /**
     * Get OHLCV data for a symbol
     */
    public function getOHLCV($symbol, $timeframe = '1m', $since = null, $limit = null, $params = [])
    {
        if ($this->exchange->has['fetchOHLCV']) {
            return $this->exchange->fetch_ohlcv($symbol, $timeframe, $since, $limit, $params);
        }
        return null;
    }

    /**
     * Get user's account balance
     */
    public function getBalance($params = [])
    {
        return $this->exchange->fetch_balance($params);
    }

    /**
     * Get user's trades
     */
    public function getMyTrades($symbol = null, $since = null, $limit = null, $params = [])
    {
        if ($this->exchange->has['fetchMyTrades']) {
            return $this->exchange->fetch_my_trades($symbol, $since, $limit, $params);
        }
        return null;
    }

    /**
     * Create a new order
     */
    public function createOrder($symbol, $type, $side, $amount, $price = null, $params = [])
    {
        return $this->exchange->create_order($symbol, $type, $side, $amount, $price, $params);
    }

    public function createOrders($orders, $params = [])
    {
        return $this->exchange->create_orders($orders, $params);
    }

    /**
     * Cancel an order
     */
    public function cancelOrder($id, $symbol = null, $params = [])
    {
        if ($this->exchange->has['cancelOrder']) {
            return $this->exchange->cancel_order($id, $symbol, $params);
        }
        return null;
    }

    /**
     * Get an order status
     */
    public function getOrder($id, $symbol = null, $params = [])
    {
        if ($this->exchange->has['fetchOrder']) {
            return $this->exchange->fetch_order($id, $symbol, $params);
        }
        return null;
    }

    /**
     * Get all open orders
     */
    public function getOpenOrders($symbol = null, $since = null, $limit = null, $params = [])
    {
        if ($this->exchange->has['fetchOpenOrders']) {
            return $this->exchange->fetch_open_orders($symbol, $since, $limit, $params);
        }
        return null;
    }

    /**
     * Get all closed orders
     */
    public function getClosedOrders($symbol = null, $since = null, $limit = null, $params = [])
    {
        if ($this->exchange->has['fetchClosedOrders']) {
            return $this->exchange->fetch_closed_orders($symbol, $since, $limit, $params);
        }
        return null;
    }

    public function cancelAllOrders($symbol = null, $params = [])
    {
        // Check if exchange supports cancelAllOrders
        if ($this->exchange->has['cancelAllOrders']) {
            return $this->exchange->cancel_all_orders($symbol, $params);
        }

        return null;
    }

    public function getCoins()
    {
        $cacheKey = 'ccxt_markets_' . $this->exchange->id;
        return \Cache::remember($cacheKey, now()->addDay(), function () {
            $markets = $this->exchange->load_markets();
            return array_keys($markets);
        });
    }

    /**
     * Get deposit address for a currency
     */
    public function getDepositAddress($code, $params = [])
    {
        if ($this->exchange->has['fetchDepositAddress']) {
            return $this->exchange->fetch_deposit_address($code, $params);
        }
        return null;
    }

    /**
     * Get deposit history
     */
    public function getDeposits($code = null, $since = null, $limit = null, $params = [])
    {
        if ($this->exchange->has['fetchDeposits']) {
            return $this->exchange->fetch_deposits($code, $since, $limit, $params);
        }
        return null;
    }

    /**
     * Get withdrawal history
     */
    public function getWithdrawals($code = null, $since = null, $limit = null, $params = [])
    {
        if ($this->exchange->has['fetchWithdrawals']) {
            return $this->exchange->fetch_withdrawals($code, $since, $limit, $params);
        }
        return null;
    }

    /**
     * Transfer funds between accounts
     */
    public function transfer($code, $amount, $fromAccount, $toAccount, $params = [])
    {
        if ($this->exchange->has['transfer']) {
            return $this->exchange->transfer($code, $amount, $fromAccount, $toAccount, $params);
        }
        return null;
    }

    /**
     * Withdraw funds
     */
    public function withdraw($code, $amount, $address, $tag = null, $params = [])
    {
        if ($this->exchange->has['withdraw']) {
            return $this->exchange->withdraw($code, $amount, $address, $tag, $params);
        }
        return null;
    }

    public function setLeverage($symbol, $leverage, $params = [])
    {
        if ($this->exchange->has['setLeverage']) {
            return $this->exchange->set_leverage($leverage, $symbol, $params);
        }
        return null;
    }

    public function amountToPrecision($symbol, $amount)
    {
        return $this->exchange->amount_to_precision($symbol, $amount);
    }

    public function priceToPrecision($symbol, $amount)
    {
        return $this->exchange->price_to_precision($symbol, $amount);
    }

    // API Routes - add Laravel route handlers below

    /**
     * API endpoint for getting markets
     */
    public function getMarketsRequest(Request $request)
    {
        try {
            $params = $request->input('params', []);
            $markets = $this->getMarkets($params);
            return response()->json(['success' => true, 'data' => $markets]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for getting ticker
     */
    public function getTickerRequest(Request $request)
    {
        try {
            $symbol = $request->input('symbol');
            $params = $request->input('params', []);

            if (!$symbol) {
                return response()->json(['success' => false, 'error' => 'Symbol is required'], 400);
            }

            $ticker = $this->getTicker($symbol, $params);
            return response()->json(['success' => true, 'data' => $ticker]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for getting all tickers
     */
    public function getTickersRequest(Request $request)
    {
        try {
            $symbols = $request->input('symbols');
            $params = $request->input('params', []);
            $tickers = $this->getTickers($symbols, $params);
            return response()->json(['success' => true, 'data' => $tickers]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for getting order book
     */
    public function getOrderBookRequest(Request $request)
    {
        try {
            $symbol = $request->input('symbol');
            $limit = $request->input('limit');
            $params = $request->input('params', []);

            if (!$symbol) {
                return response()->json(['success' => false, 'error' => 'Symbol is required'], 400);
            }

            $orderBook = $this->getOrderBook($symbol, $limit, $params);
            return response()->json(['success' => true, 'data' => $orderBook]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for getting trades
     */
    public function getTradesRequest(Request $request)
    {
        try {
            $symbol = $request->input('symbol');
            $since = $request->input('since');
            $limit = $request->input('limit');
            $params = $request->input('params', []);

            if (!$symbol) {
                return response()->json(['success' => false, 'error' => 'Symbol is required'], 400);
            }

            $trades = $this->getTrades($symbol, $since, $limit, $params);
            return response()->json(['success' => true, 'data' => $trades]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for getting OHLCV data
     */
    public function getOHLCVRequest(Request $request)
    {
        try {
            $symbol = $request->input('symbol');
            $timeframe = $request->input('timeframe', '1m');
            $since = $request->input('since');
            $limit = $request->input('limit');
            $params = $request->input('params', []);

            if (!$symbol) {
                return response()->json(['success' => false, 'error' => 'Symbol is required'], 400);
            }

            $ohlcv = $this->getOHLCV($symbol, $timeframe, $since, $limit, $params);
            return response()->json(['success' => true, 'data' => $ohlcv]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for getting account balance
     */
    public function getBalanceRequest(Request $request)
    {
        try {
            $params = $request->input('params', []);
            $balance = $this->getBalance($params);
            return response()->json(['success' => true, 'data' => $balance]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for getting user's trades
     */
    public function getMyTradesRequest(Request $request)
    {
        try {
            $symbol = $request->input('symbol');
            $since = $request->input('since');
            $limit = $request->input('limit');
            $params = $request->input('params', []);
            $trades = $this->getMyTrades($symbol, $since, $limit, $params);
            return response()->json(['success' => true, 'data' => $trades]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for creating a new order
     */
    public function createOrderRequest(Request $request)
    {
        try {
            $symbol = $request->input('symbol');
            $type = $request->input('type');
            $side = $request->input('side');
            $amount = $request->input('amount');
            $price = $request->input('price');
            $params = $request->input('params', []);

            if (!$symbol || !$type || !$side || !$amount) {
                return response()->json(['success' => false, 'error' => 'Symbol, type, side, and amount are required'], 400);
            }

            $order = $this->createOrder($symbol, $type, $side, $amount, $price, $params);
            return response()->json(['success' => true, 'data' => $order]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for canceling an order
     */
    public function cancelOrderRequest(Request $request)
    {
        try {
            $id = $request->input('id');
            $symbol = $request->input('symbol');
            $params = $request->input('params', []);

            if (!$id) {
                return response()->json(['success' => false, 'error' => 'Order ID is required'], 400);
            }

            $result = $this->cancelOrder($id, $symbol, $params);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for getting an order status
     */
    public function getOrderRequest(Request $request)
    {
        try {
            $id = $request->input('id');
            $symbol = $request->input('symbol');
            $params = $request->input('params', []);

            if (!$id) {
                return response()->json(['success' => false, 'error' => 'Order ID is required'], 400);
            }

            $order = $this->getOrder($id, $symbol, $params);
            return response()->json(['success' => true, 'data' => $order]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for getting open orders
     */
    public function getOpenOrdersRequest(Request $request)
    {
        try {
            $symbol = $request->input('symbol');
            $since = $request->input('since');
            $limit = $request->input('limit');
            $params = $request->input('params', []);
            $orders = $this->getOpenOrders($symbol, $since, $limit, $params);
            return response()->json(['success' => true, 'data' => $orders]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for getting closed orders
     */
    public function getClosedOrdersRequest(Request $request)
    {
        try {
            $symbol = $request->input('symbol');
            $since = $request->input('since');
            $limit = $request->input('limit');
            $params = $request->input('params', []);
            $orders = $this->getClosedOrders($symbol, $since, $limit, $params);
            return response()->json(['success' => true, 'data' => $orders]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for getting deposit address
     */
    public function getDepositAddressRequest(Request $request)
    {
        try {
            $code = $request->input('code');
            $params = $request->input('params', []);

            if (!$code) {
                return response()->json(['success' => false, 'error' => 'Currency code is required'], 400);
            }

            $address = $this->getDepositAddress($code, $params);
            return response()->json(['success' => true, 'data' => $address]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for getting deposit history
     */
    public function getDepositsRequest(Request $request)
    {
        try {
            $code = $request->input('code');
            $since = $request->input('since');
            $limit = $request->input('limit');
            $params = $request->input('params', []);
            $deposits = $this->getDeposits($code, $since, $limit, $params);
            return response()->json(['success' => true, 'data' => $deposits]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for getting withdrawal history
     */
    public function getWithdrawalsRequest(Request $request)
    {
        try {
            $code = $request->input('code');
            $since = $request->input('since');
            $limit = $request->input('limit');
            $params = $request->input('params', []);
            $withdrawals = $this->getWithdrawals($code, $since, $limit, $params);
            return response()->json(['success' => true, 'data' => $withdrawals]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for transferring funds
     */
    public function transferRequest(Request $request)
    {
        try {
            $code = $request->input('code');
            $amount = $request->input('amount');
            $fromAccount = $request->input('fromAccount');
            $toAccount = $request->input('toAccount');
            $params = $request->input('params', []);

            if (!$code || !$amount || !$fromAccount || !$toAccount) {
                return response()->json(['success' => false, 'error' => 'Currency code, amount, from account, and to account are required'], 400);
            }

            $result = $this->transfer($code, $amount, $fromAccount, $toAccount, $params);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API endpoint for withdrawing funds
     */
    public function withdrawRequest(Request $request)
    {
        try {
            $code = $request->input('code');
            $amount = $request->input('amount');
            $address = $request->input('address');
            $tag = $request->input('tag');
            $params = $request->input('params', []);

            if (!$code || !$amount || !$address) {
                return response()->json(['success' => false, 'error' => 'Currency code, amount, and address are required'], 400);
            }

            $result = $this->withdraw($code, $amount, $address, $tag, $params);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}

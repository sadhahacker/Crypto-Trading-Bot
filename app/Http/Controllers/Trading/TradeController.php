<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\ExchangeSetting;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TradeController extends Controller
{
    public $exchange;

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
            $snapshot = $this->buildDashboardSnapshot();
            return successResponse('Account details fetched successfully', $snapshot);
        } catch (Exception $e) {
            return errorResponse($e->getMessage());
        }
    }

    /**
     * Snapshot of balances, open positions and open orders for the dashboard
     */
    public function getDashboardSnapshot()
    {
        try {
            $snapshot = $this->buildDashboardSnapshot();
            return successResponse('Dashboard snapshot fetched successfully', $snapshot);
        } catch (Exception $e) {
            return errorResponse($e->getMessage());
        }
    }

    /**
     * Compose a lightweight snapshot for dashboard consumption.
     */
    private function buildDashboardSnapshot(): array
    {
        $balance = $this->getBalance();

        // Normalize balances with sensible fallbacks
        $totalWalletBalance = Arr::get($balance, 'info.totalWalletBalance')
            ?? Arr::get($balance, 'total.USDT')
            ?? Arr::get($balance, 'info.balance')
            ?? 0;

        $availableBalance = Arr::get($balance, 'info.availableBalance')
            ?? Arr::get($balance, 'free.USDT')
            ?? Arr::get($balance, 'info.cashBal')
            ?? 0;

        // Assets list (wallet + available)
        $assets = collect(Arr::get($balance, 'info.assets', []))
            ->map(function ($asset) {
                return [
                    'asset' => $asset['asset'] ?? '',
                    'walletBalance' => $asset['walletBalance'] ?? 0,
                    'availableBalance' => $asset['availableBalance'] ?? 0,
                ];
            })
            ->filter(fn ($a) => $a['asset'] !== '')
            ->values()
            ->toArray();

        $balanceBaseCurrency = $this->detectBalanceBaseCurrency($balance, $assets);
        $displayCurrency = $this->getDisplayCurrency();
        $conversionRate = $this->getCachedConversionRate($balanceBaseCurrency, $displayCurrency);
        $totalWalletBalanceInDisplayCurrency = (float) $totalWalletBalance * $conversionRate;
        $availableBalanceInDisplayCurrency = (float) $availableBalance * $conversionRate;

        // Positions (filter out zero-sized) and trim to dashboard-friendly fields
        $rawPositions = $this->getPositions() ?? [];
        $openPositions = collect($rawPositions)
            ->filter(function ($position) {
                $size = $position['contracts'] ?? $position['size'] ?? $position['positionAmt'] ?? 0;
                return abs((float) $size) > 0;
            })
            ->map(function ($position) {
                return [
                    'symbol' => $position['symbol'] ?? null,
                    'side' => $position['side'] ?? null,
                    'contracts' => $position['contracts'] ?? $position['size'] ?? $position['positionAmt'] ?? null,
                    'entryPrice' => $position['entryPrice'] ?? null,
                    'notional' => $position['notional'] ?? null,
                    'unrealizedPnl' => $position['unrealizedPnl'] ?? null,
                    'percentage' => $position['percentage'] ?? null,
                    'leverage' => $position['leverage'] ?? null,
                    'markPrice' => $position['markPrice'] ?? null,
                    'liquidationPrice' => $position['liquidationPrice'] ?? null,
                ];
            })
            ->values()
            ->toArray();

        // Open orders trimmed
        $openOrders = $this->getOpenOrders() ?? [];
        $orders = collect($openOrders)
            ->map(function ($order) {
                return [
                    'id' => $order['id'] ?? null,
                    'symbol' => $order['symbol'] ?? null,
                    'type' => $order['type'] ?? null,
                    'side' => $order['side'] ?? null,
                    'price' => $order['price'] ?? null,
                    'amount' => $order['amount'] ?? null,
                    'filled' => $order['filled'] ?? null,
                    'remaining' => $order['remaining'] ?? null,
                    'status' => $order['status'] ?? null,
                    'timestamp' => $order['timestamp'] ?? null,
                ];
            })
            ->values()
            ->toArray();

        return [
            'exchange' => [
                'id' => $this->exchange->id,
                'name' => $this->exchange->name,
                'mode' => $this->exchange->options['defaultType'] ?? null,
            ],
            'balances' => [
                'baseCurrency' => $balanceBaseCurrency,
                'displayCurrency' => $displayCurrency,
                'conversionRate' => $conversionRate,
                'totalWalletBalance' => $totalWalletBalance,
                'totalWalletBalanceInDisplayCurrency' => $totalWalletBalanceInDisplayCurrency,
                'availableBalance' => $availableBalance,
                'availableBalanceInDisplayCurrency' => $availableBalanceInDisplayCurrency,
                'assets' => $assets,
            ],
            'counts' => [
                'positions' => count($openPositions),
                'openOrders' => count($orders),
            ],
            'positions' => $openPositions,
            'orders' => $orders,
        ];
    }

    private function getDisplayCurrency(): string
    {
        $currency = ExchangeSetting::query()
            ->value('display_currency');

        $normalized = strtoupper((string) $currency);
        return $normalized !== '' ? $normalized : 'USD';
    }

    private function detectBalanceBaseCurrency(array $balance, array $assets): string
    {
        $candidates = [
            Arr::get($balance, 'info.totalWalletBalanceAsset'),
            Arr::get($balance, 'info.asset'),
            Arr::get($balance, 'info.settleCoin'),
            Arr::get($balance, 'info.quoteAsset'),
        ];

        foreach ($candidates as $candidate) {
            $currency = strtoupper((string) $candidate);
            if ($currency !== '') {
                return $currency;
            }
        }

        $total = Arr::get($balance, 'total', []);
        if (is_array($total)) {
            foreach (['USDT', 'USD', 'USDC', 'BUSD'] as $preferred) {
                if (array_key_exists($preferred, $total)) {
                    return $preferred;
                }
            }

            $firstTotalCurrency = array_key_first($total);
            if ($firstTotalCurrency) {
                return strtoupper((string) $firstTotalCurrency);
            }
        }

        $firstAsset = collect($assets)->first(function ($asset) {
            return !empty($asset['asset']) && (float) ($asset['walletBalance'] ?? 0) > 0;
        });

        if (!empty($firstAsset['asset'])) {
            return strtoupper((string) $firstAsset['asset']);
        }

        return 'USDT';
    }

    private function getCachedConversionRate(string $fromCurrency, string $toCurrency): float
    {
        $from = strtoupper(trim($fromCurrency));
        $to = strtoupper(trim($toCurrency));

        if ($from === '' || $to === '' || $from === $to) {
            return 1.0;
        }

        $cacheKey = "fx_rate_{$from}_{$to}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($from, $to) {
            // Public API for crypto-to-fiat conversion (USDT -> INR, etc.)
            if ($from === 'USDT') {
                $coingeckoRate = $this->fetchUsdtRateFromCoingecko($to);
                if ($coingeckoRate > 0) {
                    return $coingeckoRate;
                }
            }

            // Fallback for fiat rates using a public API.
            $fiatRate = $this->fetchFiatRateFromFrankfurter($from === 'USDT' ? 'USD' : $from, $to);
            if ($fiatRate > 0) {
                return $fiatRate;
            }

            // Safe fallback if public APIs fail.
            return 1.0;
        });
    }

    private function fetchUsdtRateFromCoingecko(string $toCurrency): float
    {
        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get('https://api.coingecko.com/api/v3/simple/price', [
                    'ids' => 'tether',
                    'vs_currencies' => strtolower($toCurrency),
                ]);

            if (! $response->successful()) {
                return 0;
            }

            $rate = (float) Arr::get($response->json(), 'tether.' . strtolower($toCurrency), 0);
            return $rate > 0 ? $rate : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function fetchFiatRateFromFrankfurter(string $fromCurrency, string $toCurrency): float
    {
        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get('https://api.frankfurter.app/latest', [
                    'from' => strtoupper($fromCurrency),
                    'to' => strtoupper($toCurrency),
                ]);

            if (! $response->successful()) {
                return 0;
            }

            $rate = (float) Arr::get($response->json(), 'rates.' . strtoupper($toCurrency), 0);
            return $rate > 0 ? $rate : 0;
        } catch (\Throwable $e) {
            return 0;
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


    public function getPositions($symbol = [], $params = [])
    {
        if ($this->exchange->has['fetchPositions']) {
            return $this->exchange->fetch_positions($symbol, $params);
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

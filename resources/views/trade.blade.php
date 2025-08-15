<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Advanced Manual Trade Panel with Price/Percentage TP/SL</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white flex items-center justify-center min-h-screen px-4">

<div class="bg-gray-800 rounded-lg shadow-lg max-w-md w-full p-6">
    <div class="flex justify-between mb-6">
        <button id="buyBtn" class="flex-1 py-3 rounded-l-lg bg-green-600 hover:bg-green-700 font-semibold focus:outline-none">BUY</button>
        <button id="sellBtn" class="flex-1 py-3 rounded-r-lg bg-gray-700 hover:bg-gray-600 font-semibold focus:outline-none">SELL</button>
    </div>

    <form id="tradeForm" novalidate>
        <input type="hidden" id="side" name="side" value="buy" />

        <div class="mb-4">
            <label for="symbol" class="block text-gray-300 font-medium mb-1">Symbol</label>
            <input id="symbol" name="symbol" type="text" value="BTC/USDT" class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500" required />
            <p class="text-xs text-gray-400 mt-1">Format: ASSET/QUOTE (e.g. BTC/USDT)</p>
        </div>

        <div class="mb-4">
            <label for="entry_price" class="block text-gray-300 font-medium mb-1" id="entryLabel">Entry Price (Loading...)</label>
            <input id="entry_price" name="entry_price" type="number" step="any" min="0" placeholder="0.0" class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500" required />
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label for="usdt_amount" class="block text-gray-300 font-medium mb-1">USDT Amount</label>
                <input id="usdt_amount" name="usdt_amount" type="number" step="any" min="0" placeholder="0.0" class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500" required />
            </div>
            <div>
                <label for="leverage" class="block text-gray-300 font-medium mb-1">Leverage</label>
                <input id="leverage" name="leverage" type="number" min="1" max="125" placeholder="Auto" class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500" />
                <p class="text-xs text-gray-400 mt-1">Leave empty to auto-calc leverage</p>
            </div>
        </div>

        <fieldset class="mb-4 border border-gray-600 rounded p-4">
            <legend class="text-gray-300 font-semibold mb-2">Take Profit / Stop Loss Input Mode</legend>
            <label class="inline-flex items-center mr-6 cursor-pointer">
                <input type="radio" name="tp_sl_mode" value="price" checked class="form-radio text-green-500" />
                <span class="ml-2">Use Prices</span>
            </label>
            <label class="inline-flex items-center cursor-pointer">
                <input type="radio" name="tp_sl_mode" value="percentage" class="form-radio text-green-500" />
                <span class="ml-2">Use Percentages (%)</span>
            </label>
        </fieldset>

        <div id="priceInputs" class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label for="take_profit" class="block text-gray-300 font-medium mb-1">Take Profit Price</label>
                <input id="take_profit" name="take_profit" type="number" step="any" min="0" placeholder="0.0" class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500" required />
            </div>
            <div>
                <label for="stop_loss" class="block text-gray-300 font-medium mb-1">Stop Loss Price</label>
                <input id="stop_loss" name="stop_loss" type="number" step="any" min="0" placeholder="0.0" class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500" required />
            </div>
        </div>

        <div id="percentageInputs" class="grid grid-cols-2 gap-4 mb-4 hidden">
            <div>
                <label for="take_profit_percent" class="block text-gray-300 font-medium mb-1">Take Profit %</label>
                <input id="take_profit_percent" name="take_profit_percent" type="number" step="any" placeholder="e.g. 2.5" class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500" />
            </div>
            <div>
                <label for="stop_loss_percent" class="block text-gray-300 font-medium mb-1">Stop Loss %</label>
                <input id="stop_loss_percent" name="stop_loss_percent" type="number" step="any" placeholder="e.g. 1.5" class="w-full p-3 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500" />
            </div>
        </div>

        <button type="submit" id="submitBtn" class="w-full py-3 rounded font-bold text-lg transition-colors duration-300 bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
            Place Buy Order
        </button>

        <p id="message" class="mt-4 text-center text-sm"></p>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/ccxt/dist/ccxt.browser.js"></script>
<script>
    (() => {
        const buyBtn = document.getElementById('buyBtn');
        const sellBtn = document.getElementById('sellBtn');
        const sideInput = document.getElementById('side');
        const submitBtn = document.getElementById('submitBtn');
        const messageEl = document.getElementById('message');
        const form = document.getElementById('tradeForm');

        const entryPriceInput = document.getElementById('entry_price');
        const entryLabel = document.getElementById('entryLabel');

        const priceInputs = document.getElementById('priceInputs');
        const percentageInputs = document.getElementById('percentageInputs');

        // Radio buttons for mode
        const modeRadios = document.querySelectorAll('input[name="tp_sl_mode"]');

        // Initially show buy tab
        function setSide(side) {
            sideInput.value = side;
            if (side === 'buy') {
                buyBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                buyBtn.classList.remove('bg-gray-700', 'hover:bg-gray-600');
                sellBtn.classList.add('bg-gray-700', 'hover:bg-gray-600');
                sellBtn.classList.remove('bg-red-600', 'hover:bg-red-700');
                submitBtn.textContent = 'Place Buy Order';
                submitBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                submitBtn.classList.remove('bg-red-600', 'hover:bg-red-700');
            } else {
                sellBtn.classList.add('bg-red-600', 'hover:bg-red-700');
                sellBtn.classList.remove('bg-gray-700', 'hover:bg-gray-600');
                buyBtn.classList.add('bg-gray-700', 'hover:bg-gray-600');
                buyBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                submitBtn.textContent = 'Place Sell Order';
                submitBtn.classList.add('bg-red-600', 'hover:bg-red-700');
                submitBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
            }
            clearMessage();
        }
        buyBtn.addEventListener('click', () => setSide('buy'));
        sellBtn.addEventListener('click', () => setSide('sell'));

        // Switch input modes (price vs percentage)
        modeRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                if (radio.value === 'price' && radio.checked) {
                    priceInputs.classList.remove('hidden');
                    percentageInputs.classList.add('hidden');

                    // Clear percentage inputs
                    document.getElementById('take_profit_percent').value = '';
                    document.getElementById('stop_loss_percent').value = '';

                    // Make price inputs required
                    document.getElementById('take_profit').required = true;
                    document.getElementById('stop_loss').required = true;

                    // Remove required from percentage inputs
                    document.getElementById('take_profit_percent').required = false;
                    document.getElementById('stop_loss_percent').required = false;
                } else if (radio.value === 'percentage' && radio.checked) {
                    priceInputs.classList.add('hidden');
                    percentageInputs.classList.remove('hidden');

                    // Clear price inputs
                    document.getElementById('take_profit').value = '';
                    document.getElementById('stop_loss').value = '';

                    // Make percentage inputs required
                    document.getElementById('take_profit_percent').required = true;
                    document.getElementById('stop_loss_percent').required = true;

                    // Remove required from price inputs
                    document.getElementById('take_profit').required = false;
                    document.getElementById('stop_loss').required = false;
                }
                clearMessage();
            });
        });

        startEntryPriceUpdates();

        function startEntryPriceUpdates() {
            updateEntryPriceLabel(); // call immediately once
            setInterval(updateEntryPriceLabel, 5000); // then every 5 seconds
        }

        // Simulate fetching current price — replace this with real API call
        async function fetchCurrentPrice(symbol) {
            const exchange = new ccxt.binance();
            await exchange.loadMarkets();

            // Fetch ticker for the symbol
            const ticker = await exchange.fetchTicker(symbol);

            return ticker.last;  // Return the last price
        }

        // Load current price on page load or symbol change
        async function updateEntryPriceLabel() {
            const symbol = form.symbol.value.trim();
            if (!symbol) return;

            try {
                const price = await fetchCurrentPrice(symbol);
                entryLabel.textContent = `Entry Price (Current: ${price})`;
            } catch {
                entryLabel.textContent = `Entry Price (Failed to fetch price)`;
            }
        }

        // Update price label on load and when symbol changes
        window.addEventListener('load', updateEntryPriceLabel);
        form.symbol.addEventListener('change', updateEntryPriceLabel);

        // Utility functions to show message
        function clearMessage() {
            messageEl.textContent = '';
            messageEl.className = '';
        }

        function showError(msg) {
            messageEl.textContent = msg;
            messageEl.className = 'text-red-500 font-semibold';
        }

        function showSuccess(msg) {
            messageEl.textContent = msg;
            messageEl.className = 'text-green-500 font-semibold';
        }

        // Form submit handler
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearMessage();

            // Gather common inputs
            const symbol = form.symbol.value.trim();
            const side = sideInput.value;
            const entry_price = parseFloat(entryPriceInput.value);
            const usdt_amount = parseFloat(form.usdt_amount.value);
            const leverage = form.leverage.value ? parseInt(form.leverage.value) : null;

            if (!symbol) return showError('Symbol is required');
            if (!['buy', 'sell'].includes(side)) return showError('Invalid side selected');
            if (!(entry_price > 0)) return showError('Entry price must be greater than 0');
            if (!(usdt_amount > 0)) return showError('USDT amount must be greater than 0');
            if (leverage !== null && (leverage < 1 || leverage > 125)) return showError('Leverage must be between 1 and 125');

            // Determine TP/SL mode and get values accordingly
            const tp_sl_mode = form.querySelector('input[name="tp_sl_mode"]:checked').value;

            let take_profit, stop_loss;

            if (tp_sl_mode === 'price') {
                take_profit = parseFloat(form.take_profit.value);
                stop_loss = parseFloat(form.stop_loss.value);
                if (!(take_profit > 0)) return showError('Take profit price must be greater than 0');
                if (!(stop_loss > 0)) return showError('Stop loss price must be greater than 0');
            } else if (tp_sl_mode === 'percentage') {
                const tp_percent = parseFloat(form.take_profit_percent.value);
                const sl_percent = parseFloat(form.stop_loss_percent.value);

                if (isNaN(tp_percent) || tp_percent <= 0) return showError('Take profit percentage must be greater than 0');
                if (isNaN(sl_percent) || sl_percent <= 0) return showError('Stop loss percentage must be greater than 0');

                // Calculate actual prices based on entry price and side
                if (side === 'buy') {
                    take_profit = entry_price * (1 + tp_percent / 100);
                    stop_loss = entry_price * (1 - sl_percent / 100);
                } else {
                    // sell side
                    take_profit = entry_price * (1 - tp_percent / 100);
                    stop_loss = entry_price * (1 + sl_percent / 100);
                }
            } else {
                return showError('Invalid TP/SL input mode');
            }

            // Disable submit button while processing
            submitBtn.disabled = true;

            try {
                const response = await fetch('{{ url('trade/manual') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}' // Laravel blade csrf token
                    },
                    body: JSON.stringify({
                        symbol,
                        side,
                        entry_price,
                        takeProfitPrice: take_profit,
                        stopLossPrice: stop_loss,
                        usdt_amount,
                        leverage,
                    }),
                });

                const data = await response.json();

                if (response.ok && data.status === 'success') {
                    showSuccess(data.message || 'Trade placed successfully');
                    form.reset();
                    setSide('buy');
                    updateEntryPriceLabel();
                } else {
                    showError(data.message || 'Failed to place trade');
                }
            } catch (err) {
                showError('Network error. Please try again.');
            } finally {
                submitBtn.disabled = false;
            }
        });

        // Initialize default side
        setSide('buy');
    })();
</script>

</body>
</html>

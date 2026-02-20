@extends('master')

@section('title', 'Dashboard')

@section('page_title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('page_actions')
    <div class="dashboard-live-control">
        <i class="bi bi-fire live-fire-icon" id="live-fire-icon" aria-hidden="true"></i>
        <span class="live-status-text" id="live-status-text">Live</span>
        <span class="live-updated-text" id="last-updated">Pulse --:--:--</span>
        <div class="form-check form-switch mb-0 ms-1">
            <input class="form-check-input" type="checkbox" role="switch" id="auto-refresh" checked>
        </div>
    </div>
@endsection

@section('content')
    <div id="dashboard-alert"></div>

    <div class="row g-3 mb-2">
        <div class="col-md-3 col-sm-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon text-bg-primary">
                    <i class="bi bi-bank"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Wallet Balance</span>
                    <span class="info-box-number" id="total-balance">--</span>
                    <span class="metric-subtext" id="total-balance-note">--</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon text-bg-success">
                    <i class="bi bi-currency-exchange"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">Available Balance</span>
                    <span class="info-box-number" id="available-balance">--</span>
                    <span class="metric-subtext" id="available-balance-note">--</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon text-bg-warning">
                    <i class="bi bi-activity"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">Open Positions</span>
                    <span class="info-box-number" id="positions-count">--</span>
                    <span class="metric-subtext">Active size &gt; 0</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon text-bg-info">
                    <i class="bi bi-ui-checks-grid"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">Open Orders</span>
                    <span class="info-box-number" id="orders-count">--</span>
                    <span class="metric-subtext">Limit/stop awaiting fill</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center">
                    <span class="fw-semibold">Positions</span>
                    <span class="badge bg-warning-subtle text-warning-emphasis ms-auto" id="positions-badge">--</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 table-sm align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>Symbol</th>
                                <th>Side</th>
                                <th class="text-end">Size</th>
                                <th class="text-end">Entry</th>
                                <th class="text-end">PnL</th>
                                <th class="text-end">ROE %</th>
                            </tr>
                            </thead>
                            <tbody id="positions-body">
                            <tr><td colspan="6" class="text-center text-muted py-3">No positions</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center">
                    <span class="fw-semibold">Open Orders</span>
                    <span class="badge bg-info-subtle text-info-emphasis ms-auto" id="orders-badge">--</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 table-sm align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Symbol</th>
                                <th>Side</th>
                                <th>Type</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Filled</th>
                            </tr>
                            </thead>
                            <tbody id="orders-body">
                            <tr><td colspan="7" class="text-center text-muted py-3">No open orders</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .card { border: none; }
        .dashboard-live-control {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.36rem 0.65rem;
            border: 1px solid #dee2e6;
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 6px 18px rgba(33, 37, 41, 0.06);
        }
        .live-fire-icon {
            font-size: 0.92rem;
            color: #fd7e14;
            filter: drop-shadow(0 0 6px rgba(253, 126, 20, 0.25));
            transition: all 0.2s ease;
        }
        .live-fire-icon.paused {
            color: #adb5bd;
            filter: none;
        }
        .live-status-text {
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            color: #fd7e14;
            text-transform: uppercase;
        }
        .live-status-text.paused {
            color: #6c757d;
        }
        .live-updated-text {
            font-size: 0.75rem;
            color: #6c757d;
            white-space: nowrap;
            border-left: 1px solid #e9ecef;
            padding-left: 0.55rem;
        }
        .dashboard-live-control .form-check-input { cursor: pointer; }
        .info-box .info-box-number {
            white-space: nowrap;
        }
        .info-box .info-box-content {
            align-items: flex-start;
            text-align: left;
        }
        .metric-subtext {
            display: block;
            font-size: 0.8rem;
            color: #6c757d;
            line-height: 1.25;
            margin-top: auto;
            text-align: left;
        }
        .table thead th { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.02em; white-space: nowrap; }
        .table td, .table th { vertical-align: middle; }
        #positions-badge, #orders-badge {
            min-width: 2rem;
        }
        @media (max-width: 576px) {
            .dashboard-live-control {
                max-width: 100%;
                border-radius: 0.8rem;
            }
            .live-updated-text {
                display: none;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const apiUrl = @json(url('/api/dashboard/snapshot'));
            const wsUrl = @json(config('trading.websocket.url')) || `ws://${window.location.hostname}:${@json(config('trading.websocket.port'))}`;
            const autoRefresh = document.getElementById('auto-refresh');
            const liveFireIcon = document.getElementById('live-fire-icon');
            const liveStatusText = document.getElementById('live-status-text');
            const lastUpdated = document.getElementById('last-updated');

            let timer = null;
            let ws = null;
            let wsConnected = false;

            function formatUpdateTime(date = new Date()) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }

            function syncLiveIndicator() {
                const isLive = autoRefresh.checked;
                liveStatusText.textContent = isLive ? 'Live' : 'Paused';
                liveStatusText.classList.toggle('paused', !isLive);
                liveFireIcon.classList.toggle('paused', !isLive);
                if (!isLive) {
                    lastUpdated.textContent = `Sleep · ${formatUpdateTime()}`;
                }
            }

            function formatNumber(value) {
                const num = Number(value);
                if (Number.isNaN(num)) return '--';
                return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 8 });
            }

            function formatCurrency(value, currencyCode) {
                const num = Number(value);
                if (Number.isNaN(num)) return '--';
                try {
                    return new Intl.NumberFormat(undefined, {
                        style: 'currency',
                        currency: currencyCode || 'USD',
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    }).format(num);
                } catch (e) {
                    return `${num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currencyCode || ''}`.trim();
                }
            }

            function renderPositions(positions) {
                const tbody = document.getElementById('positions-body');
                tbody.innerHTML = '';
                if (!positions || positions.length === 0) {
                    tbody.innerHTML = '<tr><td colspan=\"6\" class=\"text-center text-muted py-3\">No positions</td></tr>';
                    return;
                }
                positions.forEach(pos => {
                    const perc = Number(pos.percentage);
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${pos.symbol ?? '--'}</td>
                        <td class=\"text-${pos.side === 'long' ? 'success' : 'danger'}\">${pos.side ?? '--'}</td>
                        <td class=\"text-end\">${formatNumber(pos.contracts)}</td>
                        <td class=\"text-end\">${formatNumber(pos.entryPrice)}</td>
                        <td class=\"text-${(pos.unrealizedPnl ?? 0) >= 0 ? 'success' : 'danger'} text-end\">${formatNumber(pos.unrealizedPnl)}</td>
                        <td class=\"text-end\">${Number.isFinite(perc) ? perc.toFixed(2) : '--'}%</td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            function renderOrders(orders) {
                const tbody = document.getElementById('orders-body');
                tbody.innerHTML = '';
                if (!orders || orders.length === 0) {
                    tbody.innerHTML = '<tr><td colspan=\"7\" class=\"text-center text-muted py-3\">No open orders</td></tr>';
                    return;
                }
                orders.forEach(order => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${order.id ?? '--'}</td>
                        <td>${order.symbol ?? '--'}</td>
                        <td class=\"text-${order.side === 'buy' ? 'success' : 'danger'}\">${order.side ?? '--'}</td>
                        <td>${order.type ?? '--'}</td>
                        <td class=\"text-end\">${formatNumber(order.price)}</td>
                        <td class=\"text-end\">${formatNumber(order.amount)}</td>
                        <td class=\"text-end\">${formatNumber(order.filled)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            function renderSnapshot(data) {
                const balances = data?.balances || {};
                const baseCurrency = balances.baseCurrency || 'USDT';
                const displayCurrency = balances.displayCurrency || 'USD';
                const totalWalletBalance = balances.totalWalletBalance ?? 0;
                const availableBalance = balances.availableBalance ?? 0;
                const totalWalletBalanceInDisplayCurrency = balances.totalWalletBalanceInDisplayCurrency ?? totalWalletBalance;
                const availableBalanceInDisplayCurrency = balances.availableBalanceInDisplayCurrency ?? availableBalance;

                document.getElementById('total-balance').textContent = `${formatNumber(totalWalletBalance)} ${baseCurrency}`;
                document.getElementById('available-balance').textContent = `${formatNumber(availableBalance)} ${baseCurrency}`;
                document.getElementById('total-balance-note').textContent = `${formatCurrency(totalWalletBalanceInDisplayCurrency, displayCurrency)} (${displayCurrency})`;
                document.getElementById('available-balance-note').textContent = `${formatCurrency(availableBalanceInDisplayCurrency, displayCurrency)} (${displayCurrency})`;
                document.getElementById('positions-count').textContent = data?.counts?.positions ?? '--';
                document.getElementById('orders-count').textContent = data?.counts?.openOrders ?? '--';
                document.getElementById('positions-badge').textContent = data?.counts?.positions ?? '--';
                document.getElementById('orders-badge').textContent = data?.counts?.openOrders ?? '--';

                renderPositions(data?.positions);
                renderOrders(data?.orders);
            }

            function showError(message) {
                window.helper.showAlert({
                    message: message || 'Unable to load dashboard data.',
                    type: 'danger',
                    containerSelector: '#dashboard-alert',
                    id: 'dashboard-error'
                });
            }

            async function fetchSnapshot() {
                try {
                    const response = await axios.get(apiUrl);
                    if (response.data?.success) {
                        renderSnapshot(response.data.data);
                        lastUpdated.textContent = `Pulse · ${formatUpdateTime()}`;
                    } else {
                        showError(response.data?.message);
                    }
                } catch (error) {
                    const msg = error.response?.data?.message || error.message;
                    showError(msg);
                } finally {
                    syncLiveIndicator();
                }
            }

            function closeWebsocket() {
                if (ws) {
                    ws.close();
                    ws = null;
                }
                wsConnected = false;
            }

            function stopAll() {
                closeWebsocket();
                if (timer) clearInterval(timer);
                timer = null;
            }

            function startWebsocket() {
                try {
                    ws = new WebSocket(wsUrl);
                } catch (e) {
                    console.error('WS init failed', e);
                    startAutoRefresh();
                    return;
                }

                ws.addEventListener('open', () => {
                    wsConnected = true;
                    // ask for immediate snapshot
                    ws.send(JSON.stringify({ type: 'snapshot' }));
                });

                ws.addEventListener('message', (event) => {
                    try {
                        const payload = JSON.parse(event.data);
                        if (payload?.type === 'snapshot' && payload.data) {
                            renderSnapshot(payload.data);
                            lastUpdated.textContent = `Live · ${formatUpdateTime()}`;
                        }
                    } catch (e) {
                        console.error('WS parse error', e);
                    }
                });

                ws.addEventListener('error', () => {
                    wsConnected = false;
                    startAutoRefresh();
                });

                ws.addEventListener('close', () => {
                    wsConnected = false;
                    startAutoRefresh();
                });
            }

            function startAutoRefresh() {
                stopAll();
                timer = setInterval(fetchSnapshot, 5000);
            }

            autoRefresh.addEventListener('change', (e) => {
                if (e.target.checked) {
                    // Prefer websocket; fall back to polling if it fails
                    stopAll();
                    startWebsocket();
                    // kick a fetch to render quickly in case ws is slow to open
                    fetchSnapshot();
                } else {
                    stopAll();
                    lastUpdated.textContent = `Sleep · ${formatUpdateTime()}`;
                }
                syncLiveIndicator();
            });

            // initial load
            syncLiveIndicator();
            startWebsocket();
            fetchSnapshot();
        });
    </script>
@endpush

@extends('master')

@section('title', 'Strategy Backtest')

@section('page_title', 'Strategy Backtest')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ url('settings') }}">Settings</a></li>
    <li class="breadcrumb-item"><a href="{{ url('backtest') }}">Backtest</a></li>
    <li class="breadcrumb-item active">{{ $selectedStrategy['name'] ?? 'Strategy' }}</li>
@endsection

@section('content')
    <div class="col-12">
        <div class="card card-info card-outline mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <div class="card-title mb-0">{{ $selectedStrategy['name'] ?? 'Strategy' }}</div>
                    <small class="text-muted">{{ $selectedStrategy['description'] ?? '' }}</small>
                </div>
                <a href="{{ url('backtest') }}" class="btn btn-sm btn-outline-secondary">All Strategies</a>
            </div>

            <div class="card-body">
                <form id="strategy-run-form">
                    @csrf
                    <input type="hidden" name="strategy" value="{{ $selectedStrategy['key'] ?? '' }}">

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="backtest_data_id">Backtest Dataset</label>
                        <select id="backtest_data_id" name="backtest_data_id" class="form-select" required>
                            <option value="">Select dataset</option>
                            @foreach($datasets as $dataset)
                                <option value="{{ $dataset['id'] }}">{{ $dataset['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-3">
                        @foreach(($selectedStrategy['config_fields'] ?? []) as $field)
                            @if(($field['type'] ?? 'number') === 'checkbox')
                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-2">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            role="switch"
                                            id="{{ $field['name'] }}"
                                            name="{{ $field['name'] }}"
                                            @checked(!empty($field['default']))
                                        >
                                        <label class="form-check-label" for="{{ $field['name'] }}">{{ $field['label'] }}</label>
                                    </div>
                                </div>
                            @else
                                <div class="col-md-6">
                                    <label class="form-label" for="{{ $field['name'] }}">{{ $field['label'] }}</label>
                                    <input
                                        type="{{ $field['type'] ?? 'number' }}"
                                        class="form-control"
                                        id="{{ $field['name'] }}"
                                        name="{{ $field['name'] }}"
                                        value="{{ $field['default'] ?? '' }}"
                                        @if(isset($field['step'])) step="{{ $field['step'] }}" @endif
                                        @if(isset($field['min'])) min="{{ $field['min'] }}" @endif
                                        @if(isset($field['max'])) max="{{ $field['max'] }}" @endif
                                        @if(!empty($field['required'])) required @endif
                                    >
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-success" id="btn-run-test">
                            <i class="bi bi-play-fill me-1"></i> Run Test
                        </button>
                    </div>
                </form>

                <div class="alert alert-light border mt-3 mb-0 small" id="exchange-backtest-config">
                    <div class="fw-semibold mb-1">Exchange Setting Defaults Used For Backtest</div>
                    <div><strong>Exchange:</strong> {{ $exchangeConfig['exchange_name'] ?? '-' }}</div>
                    <div><strong>Mode:</strong> {{ $exchangeConfig['default_type'] ?? '-' }}</div>
                    <div><strong>SL From Coin:</strong> {{ $exchangeConfig['stoploss_from_coin'] ?? '-' }}</div>
                    <div><strong>TP From Coin:</strong> {{ $exchangeConfig['takeprofit_from_coin'] ?? '-' }}</div>
                    <div><strong>SL From Account:</strong> {{ $exchangeConfig['stoploss_from_account_balance'] ?? '-' }}</div>
                    <div><strong>TP From Account:</strong> {{ $exchangeConfig['takeprofit_from_account_balance'] ?? '-' }}</div>
                    <div><strong>Display Currency:</strong> {{ $exchangeConfig['display_currency'] ?? '-' }}</div>
                    <div><strong>Suggested Leverage:</strong> {{ $exchangeDefaults['leverage'] ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card card-success card-outline mb-4 d-none" id="backtest-result-card">
            <div class="card-header">
                <div class="card-title">Backtest Result</div>
            </div>

            <div class="card-body">
                <div class="row" id="backtest-stats"></div>

                <div class="table-container mt-4">
                    <table class="table table-sm table-striped w-100" id="backtest-trades-table">
                        <thead>
                        <tr>
                            <th>Side</th>
                            <th>Entry Time</th>
                            <th>Exit Time</th>
                            <th>Entry</th>
                            <th>Exit</th>
                            <th>Qty</th>
                            <th>Net PnL</th>
                            <th>Reason</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="module">
        $(document).ready(function () {
            const $form = $('#strategy-run-form');
            const $runButton = $('#btn-run-test');
            const $resultCard = $('#backtest-result-card');
            const $stats = $('#backtest-stats');
            const $tradesTbody = $('#backtest-trades-table tbody');

            $form.on('submit', function (e) {
                e.preventDefault();

                if (!$('#backtest_data_id').val()) {
                    window.helper?.showAlert({
                        message: 'Please select a dataset first.',
                        type: 'danger'
                    });
                    return;
                }

                const data = {
                    _token: '{{ csrf_token() }}',
                };

                $form.serializeArray().forEach((item) => {
                    data[item.name] = item.value;
                });

                data.allow_short = $('#allow_short').is(':checked') ? 1 : 0;

                $runButton.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm"></span> Running...');

                $.ajax({
                    url: "{{ url('backtest/run') }}",
                    method: 'POST',
                    data: data,
                    success: function (response) {
                        if (!response.success) {
                            window.helper?.showAlert({
                                message: response.message || 'Backtest failed.',
                                type: 'danger'
                            });
                            return;
                        }

                        const payload = response.data || {};
                        const stats = payload.stats || {};
                        const trades = payload.trades || [];
                        const dataset = payload.dataset || {};
                        const strategy = payload.strategy || {};

                        renderStats(stats, dataset, strategy);
                        renderTrades(trades);
                        $resultCard.removeClass('d-none');

                        window.helper?.showAlert({
                            message: response.message || 'Backtest completed successfully.',
                            type: 'success'
                        });
                    },
                    error: function (xhr) {
                        const message = xhr?.responseJSON?.message || 'Failed to run backtest.';
                        window.helper?.showAlert({
                            message: message,
                            type: 'danger'
                        });
                    },
                    complete: function () {
                        $runButton.prop('disabled', false)
                            .html('<i class="bi bi-play-fill me-1"></i> Run Test');
                    }
                });
            });

            function renderStats(stats, dataset, strategy) {
                const items = [
                    ['Strategy', strategy.name || '-'],
                    ['Symbol', dataset.symbol || '-'],
                    ['Timeframe', dataset.timeframe || '-'],
                    ['Candles', dataset.candles ?? '-'],
                    ['Initial Balance', stats.initial_balance ?? '-'],
                    ['Final Balance', stats.final_balance ?? '-'],
                    ['Net Profit', stats.net_profit ?? '-'],
                    ['Net Profit %', stats.net_profit_percent ?? '-'],
                    ['Total Trades', stats.total_trades ?? '-'],
                    ['Win Rate %', stats.win_rate ?? '-'],
                    ['Profit Factor', stats.profit_factor ?? '-'],
                    ['Max Drawdown %', stats.max_drawdown_percent ?? '-'],
                ];

                const cards = items.map(([label, value]) => {
                    return `
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="border rounded p-2 h-100">
                                <div class="small text-muted">${label}</div>
                                <div class="fw-bold">${value}</div>
                            </div>
                        </div>
                    `;
                }).join('');

                $stats.html(cards);
            }

            function renderTrades(trades) {
                if (!Array.isArray(trades) || trades.length === 0) {
                    $tradesTbody.html('<tr><td colspan="8" class="text-center text-muted">No trades generated.</td></tr>');
                    return;
                }

                const rows = trades.slice(0, 200).map((trade) => {
                    return `
                        <tr>
                            <td>${trade.side ?? '-'}</td>
                            <td>${trade.entry_time ?? '-'}</td>
                            <td>${trade.exit_time ?? '-'}</td>
                            <td>${trade.entry_price ?? '-'}</td>
                            <td>${trade.exit_price ?? '-'}</td>
                            <td>${trade.quantity ?? '-'}</td>
                            <td>${trade.net_pnl ?? '-'}</td>
                            <td>${trade.exit_reason ?? '-'}</td>
                        </tr>
                    `;
                }).join('');

                $tradesTbody.html(rows);
            }
        });
    </script>
@endpush

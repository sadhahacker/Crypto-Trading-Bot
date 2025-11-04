@extends('master')

@section('title', 'Backtesting')

@section('page_title', 'Backtesting')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ url('settings') }}">Settings</a></li>
    <li class="breadcrumb-item active">Backtesting</li>
@endsection

@section('content')
    <div class="col-12">
        <div class="card card-secondary card-outline mb-4">
            <div class="card-header">
                <div class="card-title">Generated Backtest Data</div>
                <div class="card-tools">
                    <button class="btn btn-sm btn-primary float-right" id="btn-generate">
                        <i class="bi bi-plus-circle"></i> Generate Data
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div class="table-container">
                    <table id="generated-data-table" class="table table-hover w-100">
                        <thead>
                        <tr>
                            <th>Symbol</th>
                            <th>Timeframe</th>
                            <th>From</th>
                            <th>To</th>
                            <th>File Name</th>
                            <th>Size (KB)</th>
                            <th>Created At</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="generateModal" tabindex="-1" aria-labelledby="generateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="generateForm" class="modal-content">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title" id="generateModalLabel">Generate Backtest Data</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Symbol</label>
                        <input type="text" class="form-control" name="symbol" placeholder="e.g., BTCUSDT" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Timeframe</label>
                        <select name="timeframe" class="form-select" required>
                            <option value="1m">1m</option>
                            <option value="5m">5m</option>
                            <option value="1h" selected>1h</option>
                            <option value="4h">4h</option>
                            <option value="1d">1d</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col">
                            <label class="form-label">From</label>
                            <input type="date" name="from" class="form-control" required>
                        </div>
                        <div class="col">
                            <label class="form-label">To</label>
                            <input type="date" name="to" class="form-control" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-secondary" id="btn-submit">Generate</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="module">
        $(document).ready(function () {
            const $generateModal = $('#generateModal');
            const $generateForm = $('#generateForm');
            const $btnGenerate = $('#btn-generate');
            const $btnSubmit = $('#btn-submit');

            // ✅ Initialize DataTable
            const table = $('#generated-data-table').DataTable({
                responsive: true,
                scrollX: true,
                serverSide: true,
                ajax: {
                    url: "{{ url('backtest/files') }}",
                    method: 'GET'
                },
                columns: [
                    {data: 'symbol'},
                    {data: 'timeframe'},
                    {data: 'from_date'},
                    {data: 'to_date'},
                    {data: 'file_name'},
                    {data: 'size_kb'},
                    {data: 'created_at'},
                    {
                        data: 'action',
                        render: function (data) {
                            return data; // already contains HTML <a> tag
                        },
                        orderable: false,
                        searchable: false
                    }
                ],
                order: [[6, 'desc']],
                language: {emptyTable: "No backtest data available"}
            });
            const modal = new bootstrap.Modal(document.getElementById('generateModal'));
            const modalInstance = bootstrap.Modal.getInstance(document.getElementById('generateModal'));


            // ✅ Open modal on button click
            $btnGenerate.on('click', function () {
                $generateForm.trigger('reset');
                modal.show();
            });

            // ✅ Handle generate form submit
            $generateForm.on('submit', function (e) {
                e.preventDefault();

                $btnSubmit.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm"></span> Generating...');

                $.ajax({
                    url: "{{ url('generate/backTestData') }}",
                    method: "POST",
                    data: $generateForm.serialize(),
                    headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    success: function (data) {
                        if (data.status === 'success') {
                            window.helper?.showAlert({
                                message: data.message,
                                type: 'success'
                            });
                            modalInstance.hide();
                            table.ajax.reload(null, false);
                        } else {
                            window.helper?.showAlert({
                                message: data.message || 'Failed to generate data.',
                                type: 'danger'
                            });
                        }
                    },
                    error: function () {
                        window.helper?.showAlert({
                            message: 'Something went wrong while generating data.',
                            type: 'danger'
                        });
                    },
                    complete: function () {
                        $btnSubmit.prop('disabled', false).html('Generate');
                    }
                });
            });
        });
    </script>
@endpush

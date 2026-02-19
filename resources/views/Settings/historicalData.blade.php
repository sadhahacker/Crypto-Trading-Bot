@extends('master')

@section('title', 'Historical Data')

@section('page_title', 'Historical Data')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ url('settings') }}">Settings</a></li>
    <li class="breadcrumb-item active">Historical Data</li>
@endsection

@section('content')
    <div class="col-12">
        <div class="card card-secondary card-outline mb-4">
            <div class="card-header">
                <div class="card-title">Generated Historical Files</div>
            </div>

            <div class="card-body">
                <div class="table-container">
                    <table id="historical-data-table" class="table table-hover w-100">
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
@endsection

@push('scripts')
    <script type="module">
        $(document).ready(function () {
            $('#historical-data-table').DataTable({
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
                            return data;
                        },
                        orderable: false,
                        searchable: false
                    }
                ],
                order: [[6, 'desc']],
                language: {emptyTable: 'No historical data available'}
            });
        });
    </script>
@endpush

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
                    <div class="card-title">Testing</div>
                </div>

                <div class="card-body">
                </div>

                <div class="card-footer">
                </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
    </style>
@endpush

@push('scripts')
    <script type="module">
    </script>
@endpush


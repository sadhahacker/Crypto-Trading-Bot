@extends('master')

@section('title', 'Backtest')

@section('page_title', 'Backtest')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ url('settings') }}">Settings</a></li>
    <li class="breadcrumb-item active">Backtest</li>
@endsection

@section('content')
    <div class="col-12">
        <div class="card card-info card-outline mb-4">
            <div class="card-header">
                <div class="card-title">Available Strategies</div>
            </div>

            <div class="card-body">
                @if(empty($strategyCatalog))
                    <div class="alert alert-warning mb-0">No backtest-ready strategy found.</div>
                @else
                    <div class="row g-3">
                        @foreach($strategyCatalog as $strategy)
                            <div class="col-md-4 col-sm-6">
                                <a
                                    href="{{ url('backtest/strategy/' . $strategy['key']) }}"
                                    class="strategy-card d-block p-3 border rounded-3 h-100 text-decoration-none"
                                >
                                    <div class="d-flex align-items-start">
                                        <div class="icon-wrapper bg-info bg-opacity-10 text-info rounded-circle p-2 me-3">
                                            <i class="bi bi-bar-chart-line fs-5"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 fw-semibold text-dark">{{ $strategy['name'] }}</h6>
                                            <small class="text-muted">{{ $strategy['description'] }}</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .strategy-card {
            cursor: pointer;
            transition: all 0.2s ease;
            background-color: #fff;
        }

        .strategy-card:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .icon-wrapper {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
@endpush

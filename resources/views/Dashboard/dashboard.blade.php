@extends('master')

@section('title', 'Dashboard')

@section('page_title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
@endsection

@push('styles')
    <!-- Add page-specific styles here -->
@endpush

@push('scripts')
    <!-- Add page-specific scripts here -->
    <script>
        console.log('Dashboard page loaded');
    </script>
@endpush

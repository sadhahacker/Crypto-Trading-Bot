@extends('master')

@section('title', 'Settings')

@section('page_title', 'Settings')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
    <li class="breadcrumb-item active">Settings</li>
@endsection

@section('content')
    <div class="col-12">
        <!--begin::Different Height-->
        <div class="card card-secondary card-outline mb-4">
            <!--begin::Header-->
            <div class="card-header">
                <div class="card-title">General Settings</div>
            </div>
            <!--end::Header-->

            <!--begin::Body-->
            <div class="card-body">
                <div class="row g-3"><!-- g-3 adds nice spacing between columns -->

{{--                    <div class="col-md-3 col-sm-6">--}}
{{--                        <div class="setting-item p-3 border rounded-3 h-100" data-url="{{ url('settings/profile') }}">--}}
{{--                            <div class="d-flex align-items-start">--}}
{{--                                <div class="icon-wrapper bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3">--}}
{{--                                    <i class="bi bi-person-gear fs-5"></i>--}}
{{--                                </div>--}}
{{--                                <div>--}}
{{--                                    <h6 class="mb-1 fw-semibold">Profile Settings</h6>--}}
{{--                                    <small class="text-muted">Manage your name, email, and password</small>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}

{{--                    <div class="col-md-3 col-sm-6">--}}
{{--                        <div class="setting-item p-3 border rounded-3 h-100" data-url="{{ url('settings/notifications') }}">--}}
{{--                            <div class="d-flex align-items-start">--}}
{{--                                <div class="icon-wrapper bg-success bg-opacity-10 text-success rounded-circle p-2 me-3">--}}
{{--                                    <i class="bi bi-bell fs-5"></i>--}}
{{--                                </div>--}}
{{--                                <div>--}}
{{--                                    <h6 class="mb-1 fw-semibold">Notifications</h6>--}}
{{--                                    <small class="text-muted">Control email and app notifications</small>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}

{{--                    <div class="col-md-3 col-sm-6">--}}
{{--                        <div class="setting-item p-3 border rounded-3 h-100" data-url="{{ url('settings/security') }}">--}}
{{--                            <div class="d-flex align-items-start">--}}
{{--                                <div class="icon-wrapper bg-danger bg-opacity-10 text-danger rounded-circle p-2 me-3">--}}
{{--                                    <i class="bi bi-shield-lock fs-5"></i>--}}
{{--                                </div>--}}
{{--                                <div>--}}
{{--                                    <h6 class="mb-1 fw-semibold">Security</h6>--}}
{{--                                    <small class="text-muted">Enable two-factor authentication</small>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}

                    <div class="col-md-3 col-sm-6">
                        <div class="setting-item p-3 border rounded-3 h-100" data-url="{{ url('exchange/settings') }}">
                            <div class="d-flex align-items-start">
                                <div class="icon-wrapper bg-warning bg-opacity-10 text-warning rounded-circle p-2 me-3">
                                    <i class="bi bi-gear fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-semibold">Exchange Settings</h6>
                                    <small class="text-muted">Configure exchange and its api</small>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="col-md-3 col-sm-6">
                        <div class="setting-item p-3 border rounded-3 h-100" data-url="{{ url('signals/tester') }}">
                            <div class="d-flex align-items-start">
                                <div class="icon-wrapper bg-warning bg-opacity-10 text-warning rounded-circle p-2 me-3">
                                    <i class="bi bi-gear fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-semibold">Signal Tester</h6>
                                    <small class="text-muted">Configure exchange and its api</small>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <!--end::Body-->
        </div>
    </div>

@endsection


@push('styles')
    <style>
        .setting-item {
            cursor: pointer;
            transition: all 0.25s ease;
            background-color: #fff;
        }

        .setting-item:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.setting-item').forEach(item => {
                item.addEventListener('click', function () {
                    window.location.href = this.dataset.url;
                });
            });
        });
    </script>
@endpush

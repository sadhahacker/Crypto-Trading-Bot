@extends('master')

@section('title', 'Exchange Settings')

@section('page_title', 'Exchange Settings')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ url('settings') }}">Settings</a></li>
    <li class="breadcrumb-item active">Exchange Settings</li>
@endsection

@section('content')
    <div class="col-12">
        <div id="exchange-setting-alert"></div>
        <div class="card card-secondary card-outline mb-4">
            {{-- The form tag now has the ID --}}
            <form id="exchangeSettingsForm">
                @csrf
                <div class="card-header">
                    <div class="card-title">Exchange Configuration</div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="exchange_name" class="form-label">Exchange Name</label>
                            <input type="text" name="exchange_name" id="exchange_name"
                                   class="form-control" value="{{ $settings->exchange_name ?? 'binance' }}">
                            {{-- This div will be used by the validator --}}
                            <div class="invalid-feedback" id="exchange_name_error"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="default_type" class="form-label">Default Type</label>
                            <select name="default_type" id="default_type" class="form-select">
                                <option value="future" {{ ($settings->default_type ?? '') === 'future' ? 'selected' : '' }}>Future</option>
                                <option value="spot" {{ ($settings->default_type ?? '') === 'spot' ? 'selected' : '' }}>Spot</option>
                            </select>
                            <div class="invalid-feedback" id="default_type_error"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="display_currency" class="form-label">Display Currency</label>
                            @php($displayCurrency = strtoupper($settings->display_currency ?? 'USD'))
                            <select name="display_currency" id="display_currency" class="form-select">
                                <option value="USD" {{ $displayCurrency === 'USD' ? 'selected' : '' }}>USD</option>
                                <option value="INR" {{ $displayCurrency === 'INR' ? 'selected' : '' }}>INR</option>
                                <option value="EUR" {{ $displayCurrency === 'EUR' ? 'selected' : '' }}>EUR</option>
                                <option value="GBP" {{ $displayCurrency === 'GBP' ? 'selected' : '' }}>GBP</option>
                                <option value="AED" {{ $displayCurrency === 'AED' ? 'selected' : '' }}>AED</option>
                                <option value="JPY" {{ $displayCurrency === 'JPY' ? 'selected' : '' }}>JPY</option>
                            </select>
                            <div class="invalid-feedback" id="display_currency_error"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="api_key" class="form-label">API Key</label>
                            <input type="text" name="api_key" id="api_key"
                                   class="form-control" value="{{ $settings->api_key ?? '' }}">
                            <div class="invalid-feedback" id="api_key_error"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="api_secret" class="form-label">API Secret</label>
                            <input type="text" name="api_secret" id="api_secret"
                                   class="form-control" value="{{ $settings->api_secret ?? '' }}">
                            <div class="invalid-feedback" id="api_secret_error"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="stoploss_from_account_balance" class="form-label">Stoploss (from Account %)</label>
                            <input type="number" step="0.01" name="stoploss_from_account_balance" id="stoploss_from_account_balance"
                                   class="form-control" value="{{ $settings->stoploss_from_account_balance ?? 0.23 }}">
                            <div class="invalid-feedback" id="stoploss_from_account_balance_error"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="takeprofit_from_account_balance" class="form-label">Takeprofit (from Account %)</label>
                            <input type="number" step="0.01" name="takeprofit_from_account_balance" id="takeprofit_from_account_balance"
                                   class="form-control" value="{{ $settings->takeprofit_from_account_balance ?? 0.30 }}">
                            <div class="invalid-feedback" id="takeprofit_from_account_balance_error"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="stoploss_from_coin" class="form-label">Stoploss (from Coin %)</label>
                            <input type="number" step="0.001" name="stoploss_from_coin" id="stoploss_from_coin"
                                   class="form-control" value="{{ $settings->stoploss_from_coin ?? 0.03 }}">
                            <div class="invalid-feedback" id="stoploss_from_coin_error"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="takeprofit_from_coin" class="form-label">Takeprofit (from Coin %)</label>
                            <input type="number" step="0.001" name="takeprofit_from_coin" id="takeprofit_from_coin"
                                   class="form-control" value="{{ $settings->takeprofit_from_coin ?? 0.023 }}">
                            <div class="invalid-feedback" id="takeprofit_from_coin_error"></div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    {{-- Added id="saveBtn" here --}}
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .form-label {
            font-weight: 500;
        }
        /* Style for jQuery Validation error messages to match Bootstrap's invalid state */
        .is-invalid ~ .invalid-feedback {
            display: block;
        }
    </style>
@endpush

@push('scripts')
    <script type="module">
        $(document).ready(function () {
            // Replaced the .on('submit') with .validate()
            $('#exchangeSettingsForm').validate({
                // --- 1. DEFINE VALIDATION RULES ---
                rules: {
                    exchange_name: {
                        required: true
                    },
                    default_type: {
                        required: true
                    },
                    display_currency: {
                        required: true
                    },
                    api_key: {
                        required: true
                    },
                    api_secret: {
                        required: true
                    },
                    stoploss_from_account_balance: {
                        required: true,
                        number: true,
                        min: 0
                    },
                    takeprofit_from_account_balance: {
                        required: true,
                        number: true,
                        min: 0
                    },
                    stoploss_from_coin: {
                        required: true,
                        number: true,
                        min: 0
                    },
                    takeprofit_from_coin: {
                        required: true,
                        number: true,
                        min: 0
                    }
                },

                // --- 2. DEFINE CUSTOM ERROR MESSAGES ---
                messages: {
                    exchange_name: {
                        required: "Please enter an exchange name."
                    },
                    api_key: {
                        required: "Please enter your API key."
                    },
                    api_secret: {
                        required: "Please enter your API secret."
                    },
                    // You can add more custom messages here
                },

                errorPlacement: function(error, element) {
                    const errorDiv = $('#' + element.attr('id') + '_error');
                    errorDiv.text(error.text()).addClass('d-block'); // show message
                    $(element).addClass('is-invalid'); // highlight input
                },

                highlight: function(element) {
                    $(element).addClass('is-invalid');
                },

                unhighlight: function(element) {
                    $(element).removeClass('is-invalid');
                    $('#' + element.id + '_error').text('').removeClass('d-block'); // hide message
                },
                submitHandler: function(form) {
                    const $form = $(form);
                    const $btn = $('#saveBtn');

                    // Clear any *previous* server-side validation errors
                    $('.invalid-feedback').text('');
                    $('.form-control, .form-select').removeClass('is-invalid');

                    $.ajax({
                        url: "{{ url('exchange-settings') }}",
                        method: "POST",
                        data: $form.serialize(),
                        beforeSend: function () {
                            $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Saving...');
                        },
                        success: function (response) {
                            window.helper.showAlert({
                                message: response.message || 'Exchange settings updated successfully.',
                                type: 'success',
                                containerSelector: '#exchange-setting-alert'
                            });
                        },
                        error: function (xhr) {
                            if (xhr.status === 422) {
                                const errors = xhr.responseJSON.errors;
                                for (const [key, message] of Object.entries(errors)) {
                                    $(`#${key}`).addClass('is-invalid');
                                    $(`#${key}_error`).text(message[0]);
                                }
                            } else {
                                window.helper.showAlert({
                                    message: xhr.responseJSON.message || 'Something went wrong. Please try again.',
                                    type: 'danger',
                                    containerSelector: '#exchange-setting-alert'
                                });
                            }
                        },
                        complete: function (){
                            $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Save Changes');
                        }
                    });
                    // --- End of your AJAX code ---
                }
            });
        });
    </script>
@endpush

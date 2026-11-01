@extends('layouts.admin.app')

@section('title',translate('Add fund'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/add-fund.css') }}">
@endpush

@section('content')

@php
    $currencySymbol = \App\CentralLogics\Helpers::currency_symbol();
    $currencyPosition = \App\CentralLogics\Helpers::get_business_settings('currency_symbol_position') ?? 'left';
    $roundDigit = (int) (config('round_up_to_digit') ?? 2);
    $quickAmounts = [10, 25, 50, 100];
    $zeroBalance = \App\CentralLogics\Helpers::format_currency(0);
    $addFundLang = [
        'confirm' => translate('Add this amount to the customer wallet?'),
        'amount' => translate('messages.Amount'),
        'customer' => translate('messages.Customer'),
    ];
@endphp

<div class="content container-fluid tps adf">
    <div class="tps-head">
        <div class="tps-head__title">
            <span class="tps-head__icon">
                <img src="{{ asset('public/assets/admin/img/outline/wallet.svg') }}" alt="">
            </span>
            <span class="tps-head__text">
                <h1>{{ translate('Add fund') }}</h1>
                <p>{{ translate('Put money into a customer\'s wallet by hand, with a note saying why.') }}</p>
            </span>
        </div>
    </div>

    <form action="{{ route('admin.users.customer.wallet.add-fund') }}" method="post" id="add_fund"
          data-ajax-form data-ajax-reset
          data-ajax-confirm="{{ translate('This money lands in the wallet straight away.') }}"
          data-ajax-confirm-title="{{ translate('messages.Are you sure?') }}"
          data-ajax-confirm-yes="{{ translate('Add fund') }}"
          data-ajax-confirm-no="{{ translate('messages.No') }}">
        @csrf
        <div class="row g-3">
            <div class="col-xl-8">
                <div class="tps-card">
                    <div class="tps-card__body">
                        <div class="tps-group">
                            <h3 class="tps-group__label">{{ translate('messages.Customer') }}</h3>
                            <div class="tps-field mb-3">
                                <label class="tps-field__label" for="customer">
                                    {{ translate('messages.Customer') }} <span class="tps-req">*</span>
                                </label>
                                <select id="customer" name="customer_id" required
                                        data-placeholder="{{ translate('messages.Select customer by name or phone') }}"
                                        class="js-data-example-ajax form-control"></select>
                                <small class="tps-field__hint">{{ translate('Search by name or phone number.') }}</small>
                            </div>

                            <div class="adf-empty" id="customer_empty">
                                <i class="tio-user"></i>
                                <span>{{ translate('No customer picked yet. Their balance and details show up here.') }}</span>
                            </div>

                            <div class="adf-picked d-none" id="customer_picked">
                                <img class="adf-picked__avatar onerror-image" id="customer_avatar" alt=""
                                     src="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                     data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}">
                                <span class="adf-picked__text">
                                    <span class="adf-picked__name" id="customer_name"></span>
                                    <span class="adf-picked__meta">
                                        <span><i class="tio-call"></i> <span id="customer_phone"></span></span>
                                        <span><i class="tio-email"></i> <span id="customer_email"></span></span>
                                    </span>
                                </span>
                                <a href="#" class="adf-picked__link d-none" id="customer_profile" target="_blank">
                                    <i class="tio-visible-outlined"></i> {{ translate('View profile') }}
                                </a>
                            </div>

                            <div class="adf-stats d-none" id="customer_stats">
                                <span class="adf-stat">
                                    <span class="adf-stat__label">{{ translate('Wallet balance') }}</span>
                                    <span class="adf-stat__value" id="stat_balance">{{ $zeroBalance }}</span>
                                </span>
                                <span class="adf-stat">
                                    <span class="adf-stat__label">{{ translate('messages.Orders') }}</span>
                                    <span class="adf-stat__value" id="stat_orders">0</span>
                                </span>
                                <span class="adf-stat">
                                    <span class="adf-stat__label">{{ translate('Loyalty points') }}</span>
                                    <span class="adf-stat__value" id="stat_points">0</span>
                                </span>
                                <span class="adf-stat">
                                    <span class="adf-stat__label">{{ translate('Customer since') }}</span>
                                    <span class="adf-stat__value" id="stat_since">—</span>
                                </span>
                            </div>

                            <div class="tps-note tps-note--warn mt-2 d-none" id="wallet_off_note">
                                <i class="tio-warning"></i>
                                <p>{{ translate('This customer cannot hold wallet money, so the fund would be rejected.') }}</p>
                            </div>
                        </div>

                        <div class="tps-group">
                            <h3 class="tps-group__label">{{ translate('Amount') }}</h3>
                            <div class="tps-field">
                                <label class="tps-field__label" for="amount">
                                    {{ translate('Amount to add') }} <span class="tps-req">*</span>
                                </label>
                                <div class="adf-amount {{ $currencyPosition === 'right' ? 'adf-amount--suffix' : '' }}">
                                    <span class="adf-amount__cur">{{ $currencySymbol }}</span>
                                    <input type="number" class="form-control" name="amount" id="amount"
                                           min="0.01" step=".01" required
                                           placeholder="{{ translate('Ex') . ': 50' }}">
                                </div>
                                <div class="adf-quick">
                                    @foreach ($quickAmounts as $quickAmount)
                                        <button type="button" class="adf-quick__btn" data-amount="{{ $quickAmount }}">
                                            + {{ $currencyPosition === 'right' ? $quickAmount . ' ' . $currencySymbol : $currencySymbol . ' ' . $quickAmount }}
                                        </button>
                                    @endforeach
                                </div>
                                <small class="tps-field__hint">{{ translate('Wallet bonuses do not apply to money added by an admin.') }}</small>
                            </div>
                        </div>

                        <div class="tps-group">
                            <h3 class="tps-group__label">{{ translate('messages.reference') }}</h3>
                            <div class="tps-field">
                                <label class="tps-field__label" for="reference">
                                    {{ translate('messages.reference') }} <span class="tps-opt">({{ translate('Optional') }})</span>
                                </label>
                                <input type="text" class="form-control" name="reference" id="reference"
                                       maxlength="191" placeholder="{{ translate('Ex') . ': 123' }}">
                                <small class="tps-field__hint">{{ translate('Your own note, such as an invoice or ticket number. The customer does not see it.') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="tps-card__foot">
                        <span class="tps-foot-note">{{ translate('The customer is told about the money as soon as it lands.') }}</span>
                        <button type="reset" id="reset" class="btn btn--reset">
                            <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                        </button>
                        <button type="submit" id="submit" class="btn btn--primary">
                            <i class="tio-add-circle"></i> {{ translate('Add fund') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="adf-aside">
                    <div class="tps-card">
                        <div class="tps-card__head">
                            <span class="tps-card__brand"><i class="tio-receipt-outlined"></i></span>
                            <div class="tps-card__titles">
                                <h2 class="tps-card__title">{{ translate('Balance preview') }}</h2>
                                <p class="tps-card__subtitle">{{ translate('What the wallet holds once this fund is added.') }}</p>
                            </div>
                        </div>
                        <div class="tps-card__body">
                            <div class="adf-sum__hero">
                                <span class="adf-sum__hero-label">{{ translate('New balance') }}</span>
                                <span class="adf-sum__hero-value" id="new_balance">{{ $zeroBalance }}</span>
                            </div>
                            <div class="adf-sum__rows">
                                <div class="adf-sum__row">
                                    <span class="adf-sum__label">{{ translate('Current balance') }}</span>
                                    <span class="adf-sum__value" id="current_balance">{{ $zeroBalance }}</span>
                                </div>
                                <div class="adf-sum__row">
                                    <span class="adf-sum__label">{{ translate('Amount to add') }}</span>
                                    <span class="adf-sum__value adf-sum__value--add" id="added_amount">+ {{ $zeroBalance }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tps-note tps-note--info mt-3">
                        <i class="tio-info-outined"></i>
                        <p>{{ translate('Money added here is credited at once and cannot be taken back from this screen. Every entry shows up in the customer wallet report.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@endsection

@push('script_2')
    <script>
        "use strict";

        const addFundLang = @json($addFundLang);
        const addFundCurrency = {
            symbol: @json($currencySymbol),
            position: @json($currencyPosition),
            decimals: {{ $roundDigit }}
        };

        let addFundBalance = 0;
        let addFundCustomerName = '';

        function addFundMoney(value) {
            const amount = Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: addFundCurrency.decimals,
                maximumFractionDigits: addFundCurrency.decimals
            });

            return addFundCurrency.position === 'right'
                ? amount + ' ' + addFundCurrency.symbol
                : addFundCurrency.symbol + ' ' + amount;
        }

        function addFundPreview() {
            const amount = Math.max(0, Number($('#amount').val()) || 0);

            $('#current_balance').text(addFundMoney(addFundBalance));
            $('#added_amount').text('+ ' + addFundMoney(amount));
            $('#new_balance').text(addFundMoney(addFundBalance + amount));

            $('#add_fund').attr('data-ajax-confirm', addFundLang.confirm
                + ' ' + addFundLang.amount + ': ' + addFundMoney(amount)
                + ', ' + addFundLang.customer + ': ' + addFundCustomerName);
        }

        function addFundClearCustomer() {
            addFundBalance = 0;
            addFundCustomerName = '';
            $('#customer_picked, #customer_stats, #wallet_off_note').addClass('d-none');
            $('#customer_empty').removeClass('d-none');
            addFundPreview();
        }

        $('.js-data-example-ajax').select2({
            ajax: {
                url: '{{ route('admin.users.customer.select-list') }}',
                data: function (params) {
                    return {
                        q: params.term,
                        page: params.page
                    };
                },
                processResults: function (data) {
                    return {
                        results: data
                    };
                }
            }
        });

        $('#customer').on('change', function () {
            const customerId = this.value;

            if (!customerId) {
                addFundClearCustomer();
                return;
            }

            $.get({
                url: '{{ route('admin.users.customer.wallet.getUserWallet') }}',
                dataType: 'json',
                data: { customer_id: customerId },
                success: function (data) {
                    if (!data || !data.found) {
                        addFundClearCustomer();
                        return;
                    }

                    addFundBalance = Number(data.balance) || 0;
                    addFundCustomerName = data.name;

                    $('#customer_avatar').attr('src', data.image);
                    $('#customer_name').text(data.name);
                    $('#customer_phone').text(data.phone);
                    $('#customer_email').text(data.email);
                    $('#stat_balance').text(data.balance_formatted);
                    $('#stat_orders').text(data.order_count);
                    $('#stat_points').text(data.loyalty_point);
                    $('#stat_since').text(data.member_since);
                    $('#customer_profile')
                        .attr('href', data.profile_url || '#')
                        .toggleClass('d-none', !data.profile_url);
                    $('#wallet_off_note').toggleClass('d-none', data.wallet_available);
                    $('#customer_empty').addClass('d-none');
                    $('#customer_picked, #customer_stats').removeClass('d-none');

                    addFundPreview();
                }
            });
        });

        $('#amount').on('input', addFundPreview);

        $('.adf-quick__btn').on('click', function () {
            const current = Number($('#amount').val()) || 0;

            $('#amount').val((current + Number($(this).attr('data-amount'))).toFixed(addFundCurrency.decimals));
            addFundPreview();
        });

        $('#add_fund').on('reset', function () {
            window.setTimeout(function () {
                $('#customer').val(null).trigger('change');
            }, 0);
        });
    </script>
@endpush

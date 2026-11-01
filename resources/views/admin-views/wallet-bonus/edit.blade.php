@extends('layouts.admin.app')

@section('title', translate('Edit bonus'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/wallet-bonus.css') }}">
@endpush

@section('content')

@php
    $currencySymbol = \App\CentralLogics\Helpers::currency_symbol();
    $currencyPosition = \App\CentralLogics\Helpers::get_business_settings('currency_symbol_position') ?? 'left';
@endphp

<div class="content container-fluid tps wbn">
    <div class="tps-head">
        <div class="tps-head__title">
            <span class="tps-head__icon">
                <img src="{{ asset('public/assets/admin/img/outline/wallet.svg') }}" alt="">
            </span>
            <span class="tps-head__text">
                <h1>{{ translate('messages.Wallet bonus update') }}</h1>
                <p>{{ translate('Change how much this bonus adds, or how long the offer runs.') }}</p>
            </span>
        </div>

        <a href="{{ route('admin.users.customer.wallet.bonus.add-new') }}" class="tps-help">
            <i class="tio-back-ui"></i>
            <span>{{ translate('messages.Bonus list') }}</span>
        </a>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <form action="{{ route('admin.users.customer.wallet.bonus.update', $bonus->id) }}" method="post">
                @csrf
                @include('admin-views.wallet-bonus.partials._form', [
                    'bonus'        => $bonus,
                    'submitLabel'  => translate('Update'),
                    'submitIcon'   => 'tio-save',
                ])
            </form>
        </div>

        <div class="col-xl-4">
            @include('admin-views.wallet-bonus.partials._preview')
        </div>
    </div>
</div>

@endsection

@push('script_2')
<script>
    "use strict";

    window.walletBonusConfig = {
        startsToday: false,
        currency: {
            symbol: @json($currencySymbol),
            position: @json($currencyPosition),
            decimals: {{ (int) (config('round_up_to_digit') ?? 2) }}
        },
        lang: {
            capReached: @json(translate('Top-up that reaches the cap'))
        }
    };
</script>
<script src="{{ asset('public/assets/admin') }}/js/view-pages/wallet-bonus-form.js"></script>
@endpush

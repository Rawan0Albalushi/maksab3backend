@extends('layouts.vendor.app')
@section('title', $title == 'Provider' ? translate('messages.Provider_Subscription') : translate('messages.Store_Subscription'))
@section('subscriberList')
active
@endsection
@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/subscriber-detail.css') }}">
@endpush

@php($subscription = $store?->store_sub_update_application)
@php($is_commission_plan = $store->store_business_model == 'commission' && $commission_check)
@php($has_subscription = !$is_commission_plan && in_array($store->store_business_model, ['subscription', 'unsubscribed']) && $subscription)
@php($show_store_features = !in_array($store?->module?->module_type, ['rental', 'service']))
@php($commission_rate = $store->comission ?? $admin_commission)
@php($commission_label = $order_or_trip == 'trip' ? translate('Commission per trip') : ($order_or_trip == 'booking' ? translate('messages.Commission per booking') : translate('Commission per order')))
@php($commission_text = $order_or_trip == 'trip'
    ? translate('You pay a commission on each trip, with full access to the provider panel, app and customers.')
    : ($order_or_trip == 'booking'
        ? translate('You pay a commission on each booking, with full access to the provider panel, app and customers.')
        : translate('You pay a commission on each order, with full access to the store panel, app and customers.')))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{ asset('public/assets/admin/img/store.png') }}" alt="">
            </span>
            <span>
                {{ $store->name }}
                @if ($has_subscription)
                    @if ($store?->status == 0 && $store?->vendor?->status == 0)
                        <span class="badge badge-soft-info ml-2">{{ translate('Approval pending') }}</span>
                    @elseif ($subscription->status == 1)
                        <span class="badge badge-soft-success ml-2">{{ translate('Active') }}</span>
                    @else
                        <span class="badge badge-soft-danger ml-2">{{ translate('Expired') }}</span>
                    @endif
                    @if ($subscription->is_canceled)
                        <span class="badge badge-soft-warning ml-2">{{ translate('Canceled') }}</span>
                    @elseif ($subscription->is_trial)
                        <span class="badge badge-soft-warning ml-2">{{ translate('Trial') }}</span>
                    @endif
                @endif
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('The plan you are on, what it covers and when it renews.') }}</p>
    </div>

    @if ($has_subscription || ($is_commission_plan && $store->store_all_sub_trans_count > 0))
        <div class="js-nav-scroller hs-nav-scroller-horizontal mb-4">
            <ul class="nav nav-tabs tabs-inner border-0 nav--tabs nav--pills">
                <li class="nav-item">
                    <a href="{{ route('vendor.subscriptionackage.subscriberDetail') }}" class="nav-link active">{{ $has_subscription ? translate('Subscription details') : translate('Business details') }}</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('vendor.subscriptionackage.subscriberTransactions', $store->id) }}" class="nav-link">{{ translate('Transactions') }}</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('vendor.subscriptionackage.subscriberWalletTransactions') }}" class="nav-link">{{ translate('Subscription refunds') }}</a>
                </li>
            </ul>
        </div>
    @endif

    <div class="tps sbd">
        @if ($is_commission_plan)
            <div class="tps-card">
                <div class="tps-card__body sbd-commission__body">
                    <div class="sbd-commission__text">
                        <span class="sbd-eyebrow">{{ translate('Current plan') }}</span>
                        <h2 class="sbd-commission__title">{{ translate('Commission base plan') }}</h2>
                        <p class="sbd-commission__desc">{{ $commission_text }}</p>
                    </div>
                    <div class="sbd-commission__rate">
                        <span class="sbd-commission__icon">
                            <img src="{{ asset('public/assets/admin/img/money-percentage.png') }}" alt="">
                        </span>
                        <div>
                            <span class="sbd-commission__value">{{ $commission_rate }}%</span>
                            <span class="sbd-commission__label">{{ $commission_label }}</span>
                        </div>
                    </div>
                </div>
                @if ($subscription_check)
                    <div class="tps-card__foot">
                        <button type="button" data-toggle="modal" data-target="#plan-modal" class="btn btn--primary"><i class="tio-sync"></i> {{ translate('Change business plan') }}</button>
                    </div>
                @endif
            </div>
        @elseif ($has_subscription)
            @include('subscription.partials._billing', ['store' => $store])
            @include('subscription.partials._plan-overview', ['store' => $store, 'routePrefix' => 'vendor.subscriptionackage', 'isServiceModule' => $is_service_module, 'showPos' => $show_store_features, 'showSelfDelivery' => $show_store_features, 'showStatusBadge' => false])
        @else
            @include('subscription.partials._empty-state', ['isServiceModule' => $is_service_module])
        @endif
    </div>

    @include('subscription.partials._plan-modal', ['store' => $store, 'packages' => $packages, 'admin_commission' => $admin_commission, 'business_name' => $business_name, 'routePrefix' => 'vendor.subscriptionackage', 'title' => $title, 'orderOrTrip' => $order_or_trip, 'isServiceModule' => $is_service_module, 'showPos' => $show_store_features, 'showSelfDelivery' => $show_store_features])

    @include('subscription.partials._renew-modal')

    @include('subscription.partials._product-warning-modal', ['isServiceModule' => $is_service_module])

</div>
@endsection

@push('script_2')
    @include('subscription.partials._scripts', ['store' => $store, 'index' => $index, 'routePrefix' => 'vendor.subscriptionackage', 'enableQuickActions' => true])
@endpush

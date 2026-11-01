{{-- Shared header + tab strip for the three subscriber screens (details,
     transactions, refunds). They are tabs of one record, so the heading, the
     status badge and the strip have to be identical on all three.

     @include('admin-views.subscription.subscriber.partials._subscriber-nav', [
         'sn_active'   => 'transactions',
         'sn_subtitle' => translate('Every subscription payment this store has made.'),
     ])

     Variable names are prefixed `sn_` because `@include` merges the including
     view's variables and `$subtitle` is already in use on these pages. --}}

@php
    $sn_subscription = $store?->store_sub_update_application;
@endphp

<div class="page-header">
    <h1 class="page-header-title">
        <span class="page-header-icon">
            <img src="{{ asset('public/assets/admin/img/store.png') }}" alt="">
        </span>
        <span>
            {{ $store->name }}
            @if($store?->status == 0 && $store?->vendor?->status == 0)
                <span class="badge badge-soft-info ml-2">{{ translate('Approval pending') }}</span>
            @elseif($sn_subscription?->status == 1)
                <span class="badge badge-soft-success ml-2">{{ translate('Active') }}</span>
            @elseif($sn_subscription)
                <span class="badge badge-soft-danger ml-2">{{ translate('Expired') }}</span>
            @endif
            @if($sn_subscription?->is_canceled)
                <span class="badge badge-soft-warning ml-2">{{ translate('Canceled') }}</span>
            @elseif($sn_subscription?->is_trial)
                <span class="badge badge-soft-warning ml-2">{{ translate('Trial') }}</span>
            @endif
        </span>
    </h1>
    <p class="page-header-desc">{{ $sn_subtitle }}</p>
</div>

<div class="js-nav-scroller hs-nav-scroller-horizontal mb-4">
    <ul class="nav nav-tabs tabs-inner border-0 nav--tabs nav--pills">
        <li class="nav-item">
            <a href="{{ route('admin.business-settings.subscriptionackage.subscriberDetail', ['id' => $store->id, 'module' => $store->module_id]) }}"
                class="nav-link {{ $sn_active === 'details' ? 'active' : '' }}">{{ translate('Subscription details') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.business-settings.subscriptionackage.subscriberTransactions', $store->id) }}"
                class="nav-link {{ $sn_active === 'transactions' ? 'active' : '' }}">{{ translate('Transactions') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.business-settings.subscriptionackage.subscriberWalletTransactions', $store->id) }}"
                class="nav-link {{ $sn_active === 'refunds' ? 'active' : '' }}">{{ translate('Subscription refunds') }}</a>
        </li>
    </ul>
</div>

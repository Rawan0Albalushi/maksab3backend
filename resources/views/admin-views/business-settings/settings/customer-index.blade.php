@extends('layouts.admin.app')

@section('title', translate('messages.Customer settings'))

@push('css_or_js')
@endpush

@section('content')
@php use App\CentralLogics\Helpers; @endphp
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title mr-3">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/business.svg') }}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('Business setup') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('What customers may do in the apps, from guest checkout to reviews and wallets.') }}</p>
            @include('admin-views.business-settings.partials.nav-menu')
        </div>
        <form action="{{ route('admin.users.customer.update-settings') }}" method="post" enctype="multipart/form-data"
            id="update-settings">
            @csrf
            <div class="row g-3">
                <div class="col-lg-12">
                    <div class="fs-12 color-656565 px-3 py-2 bg-opacity-10 rounded bg-info mb-20">
                        <div class="d-flex align-items-center gap-2 mb-0">
                            <span class="text-info fs-16">
                                <i class="tio-light-on"></i>
                            </span>
                            <span>
                                {{ translate('See and manage all customers') }}: <a target="_blank" href="{{ route('admin.users.customer.list') }}" class="text-primary text-underline fw-semibold">{{ translate('All Customer List') }}</a>
                            </span>
                        </div>
                    </div>
                    <div class="card mb-20" id="guest_checkout_section">
                        <div class="card-body">
                            <div class="row g-3 align-items-center">
                                <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                                    <div>
                                        <h4 class="mb-1">
                                            {{ translate('Guest Checkout') }}
                                        </h4>
                                        <p class="mb-0 fs-12">
                                            {{ translate('Customers can order as guests when this feature is enabled.') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                                    @php($guest_checkout_status = $data['guest_checkout_status'] ?? 0)
                                    <div class="form-group mb-0">
                                        <label
                                            class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                    <span class="pr-1 d-flex align-items-center switch--label">
                                        <span class="line--limit-1">
                                            {{translate('messages.Status') }}
                                        </span>
                                    </span>
                                            <input type="checkbox" data-id="guest_checkout_status" data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/dm-tips-on.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/dm-tips-off.png') }}"
                                                    data-title-on="<strong>{{ translate('messages.Want to enable guest checkout?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('messages.Want to disable guest checkout?') }}</strong>"
                                                    data-text-on="<p>{{ translate('messages.If you enable this, guest checkout will be visible when customer is not logged in.') }}</p>"
                                                    data-text-off="<p>{{ translate('messages.If you disable this, guest checkout will not be visible when customer is not logged in.') }}</p>"
                                                    class="status toggle-switch-input dynamic-checkbox-toggle" value="1"
                                                    name="guest_checkout_status" id="guest_checkout_status" {{ $guest_checkout_status == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="fs-12 color-656565 px-3 py-2 bg-opacity-10 rounded bg-info mt-20">
                                <div class="d-flex align-items-center gap-2 mb-0">
                                    <span class="text-info fs-16">
                                        <i class="tio-light-on"></i>
                                    </span>
                                    <span>
                                        {{ translate('Customers can order as guests when this feature is enabled.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-20" id="general_setup_section">
                        <div class="card-body">
                            <div class="mb-20">
                                <h4 class="mb-1">
                                    {{ translate('General setup') }}
                                </h4>
                                <p class="mb-0 fs-12">
                                    {{ translate('Configure options to customize services for your customers.') }}
                                </p>
                            </div>
                            <div class="bg-light rounded p-xxl-20 p-3">
                                <div class="row g-3">
                                    <div class="col-sm-6 col-lg-4">
                                        @php($vnv = $data['toggle_veg_non_veg'] ?? 0)
                                        <div class="form-group mb-0">
                                            <span class="mb-10px d-flex align-items-center">
                                                <span class="text-title">
                                                    {{ translate('messages.Customer\'s Food Preference') }}
                                                </span>
                                                <span class="form-label-secondary  d-flex" data-toggle="tooltip"
                                                    data-placement="right"
                                                    data-original-title="{{ translate('messages.If this feature is active, customers can filter food according to their preference from the Customer App or Website.') }}"><i class="tio-info text-muted ps--3"></i></span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1">
                                                        {{ translate('messages.Status') }}
                                                    </span>
                                                </span>
                                                    <input type="checkbox" data-id="vnv1" data-type="toggle"
                                                        data-image-on="{{ asset('/public/assets/admin/img/modal/veg-on.png') }}"
                                                        data-image-off="{{ asset('/public/assets/admin/img/modal/veg-off.png') }}"
                                                        data-title-on="{{ translate('messages.Want to enable the') }} <strong>{{ translate('messages.\'Veg/Non-Veg\' feature?') }}</strong>"
                                                        data-title-off="{{ translate('messages.Want to disable') }} <strong>{{ translate('messages.the Veg/Non-Veg Feature?') }}</strong>"
                                                        data-text-on="<p>{{ translate('messages.If you enable this, customers can filter food items by choosing food from the Veg/Non-Veg feature.') }}</p>"
                                                        data-text-off="<p>{{ translate('If you disable this, the Veg/Non-Veg feature will be hidden in the customer app & website.') }}</p>"
                                                        class="status toggle-switch-input dynamic-checkbox-toggle" value="1"
                                                        name="toggle_veg_non_veg" id="vnv1" {{ $vnv == 1 ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    @php($pro_member_status = $data['pro_member_status'] ?? 0)
                    <div class="card mb-20 p-20">
                        <div class="d-flex align-items-center justify-content-between gap-2 flex-sm-nowrap flex-wrap">
                            <div>
                                <h4 class="mb-1">{{translate('Pro customer')}}</h4>
                                <p class="fs-12 m-0">
                                    {{translate('Enable to allow Pro Customer. Ensure subscription')}}
                                    @if($pro_member_status == 1)
                                        <a href="{{ route('admin.pro-customer.benefits-setup') }}" class="fs-12 font-weight-medium text-info text-underline m-0">{{translate('Setup')}}</a>
                                    @else
                                        <span class="fs-12 font-weight-medium text-muted m-0">{{translate('Setup')}}</span>
                                    @endif
                                    {{translate('is complete and Setup the')}}
                                    @if($pro_member_status == 1)
                                        <a href="{{ route('admin.business-settings.fcm-index') }}#subscription-notification-en" class="fs-12 font-weight-medium text-info text-underline m-0">{{translate('Push notification')}}</a>.
                                    @else
                                        <span class="fs-12 font-weight-medium text-muted m-0">{{translate('Push notification')}}</span>.
                                    @endif
                                </p>
                            </div>
                            <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end justify-content-end align-items-center gap-3">
                                <div class="mb-0">
                                    <label class="toggle-switch toggle-switch-sm mb-0">
                                        <input type="checkbox"
                                            data-id="pro_member_status" data-type="toggle"
                                            data-image-on="{{ asset('/public/assets/admin/img/modal/crown_on.png') }}"
                                            data-image-off="{{ asset('/public/assets/admin/img/modal/crown_off.png') }}"
                                            data-title-on="<strong>{{ translate('Want to enable Pro Customer feature?') }}</strong>"
                                            data-title-off="<strong>{{ translate('Want to disable Pro Customer feature?') }}</strong>"
                                            data-text-on="<p>{{ translate('If you enable this, customers can subscribe to Pro Customer plans.') }}</p>"
                                            data-text-off="<p>{{ translate('If you disable the subscription plan, new customers won\'t be able to subscribe. Existing subscribers. keep benefits until expiry, then the feature will be unavailable.') }}</p>"
                                            class="status toggle-switch-input dynamic-checkbox-toggle"
                                            name="pro_member_status" id="pro_member_status" value="1"
                                            {{ $pro_member_status == 1 ? 'checked' : '' }}>
                                        <span class="toggle-switch-label text mb-0">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    @if (addon_published_status('AI'))
                        @php($customer_personalization_status = $data['customer_personalization_status'] ?? 0)
                        @php($customer_personalization_tooltip = addon_published_status('Service')
                            ? translate('Personalized home page is available for the Grocery, Pharmacy, Food, Shop and Service modules.')
                            : translate('Personalized home page is available for the Grocery, Pharmacy, Food and Shop modules.'))
                        <div class="card mb-20 p-20" id="customer-personalization">
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-sm-nowrap flex-wrap">
                                <div>
                                    <h4 class="mb-1 d-flex align-items-center">
                                        {{ translate('AI Personalization') }}
                                        <span class="form-label-secondary d-flex" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ $customer_personalization_tooltip }}"><i class="tio-info text-muted ps--3"></i></span>
                                    </h4>
                                    <p class="fs-12 m-0">
                                        {{ translate('Show each customer a more relevant order of items, stores, and categories.') }}
                                    </p>
                                </div>
                                <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end align-items-center gap-3">
                                    <div class="mb-0">
                                        <label class="toggle-switch toggle-switch-sm mb-0">
                                            <input type="checkbox"
                                                data-id="customer_personalization_status" data-type="toggle"
                                                data-image-on="{{ asset('/public/assets/admin/img/modal/schedule-on.png') }}"
                                                data-image-off="{{ asset('/public/assets/admin/img/modal/schedule-off.png') }}"
                                                data-title-on="<strong>{{ translate('Enable AI Personalization?') }}</strong>"
                                                data-title-off="<strong>{{ translate('Disable AI Personalization?') }}</strong>"
                                                data-text-on="<p>{{ translate('Listings are tailored to each customer for a more relevant shopping experience.') }}</p>"
                                                data-text-off="<p>{{ translate('Customers will see the standard listing order.') }}</p>"
                                                class="status toggle-switch-input dynamic-checkbox-toggle"
                                                name="customer_personalization_status" id="customer_personalization_status" value="1"
                                                {{ $customer_personalization_status == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text mb-0">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="card mb-20 card-container" id="customer-wallet">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-sm-nowrap flex-wrap">
                                <div>
                                    <h4 class="mb-1">{{translate('Customer wallet')}}</h4>
                                    <p class="fs-12 m-0">{{translate('When active this feature customer can Earn & Buy through wallet. See customer wallet from Customers Details page.')}}</p>
                                </div>
                                <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end justify-content-end align-items-center gap-3">
                                    <div class="view_toggle_btn fz--14px info-dark cursor-pointer text-decoration-underline font-semibold d-flex align-items-center gap-1">
                                        {{ translate('messages.View') }}
                                        <i class="tio-chevron-down fs-22"></i>
                                    </div>
                                    <div class="mb-0">
                                        <label class="toggle-switch toggle-switch-sm mb-0">
                                            <input type="checkbox"


                                                     data-id="wallet_status" data-type="toggle"
                                                        data-image-on="{{ asset('/public/assets/admin/img/modal/refund-on.png') }}"
                                                        data-image-off="{{ asset('/public/assets/admin/img/modal/refund-off.png') }}"
                                                        data-title-on="{{ translate('messages.Want to enable') }} <strong>{{ translate('messages.Wallet feature?') }}</strong>"
                                                        data-title-off="{{ translate('messages.Want to disable') }} <strong>{{ translate('messages.Wallet feature?') }}</strong>"
                                                        data-text-on="<p>{{ translate('messages.If you enable this, Customers will have the wallet feature.') }}</p>"
                                                        data-text-off="<p>{{ translate('If you disable this, the wallet feature will be hidden from the customer app and website.') }}</p>"
                                                        class="status toggle-switch-input dynamic-checkbox-toggle "


                                            name="wallet_status" id="wallet_status" value="1"
                                                    {{ isset($data['wallet_status']) && $data['wallet_status'] == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text mb-0">
                                                <span
                                                    class="toggle-switch-indicator">
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="card-details-body {{ !isset($data['wallet_status']) || $data['wallet_status'] != 1  ? 'd-none' : '' }}">
                                <div class="bg-light2  rounded p-xxl-20 p-3 mt-20">
                                    <div class="row g-3">
                                         <div class="col-sm-6 col-lg-6">
                                            <div class="form-group mb-0">
                                                <span class="mb-2 d-flex align-items-center text-title">{{ translate('messages.Refund to Wallet') }}<span
                                                    class="input-label-secondary" data-toggle="tooltip"
                                                    data-placement="right"
                                                    data-original-title="{{ translate('messages.If it\'s enabled, Customers will automatically receive the refunded amount in their wallets. But if it\'s disabled, the Admin will handle the Refund Request in his convenient transaction channel.') }}"><i class="tio-info text-muted ps--3"></i></span>
                                                </span>
                                                <label
                                                    class="toggle-switch toggle-switch-sm d-flex justify-content-between border border-secondary rounded px-4 form-control {{ isset($data['wallet_status']) && $data['wallet_status'] == 1 ? '' : 'text-muted' }}">
                                                    <span class="pr-2">{{ translate('messages.Status') }}</span>
                                                    <input type="checkbox"
                                                    {{ isset($data['wallet_status']) && $data['wallet_status'] == 1 ? '' : 'disabled' }}
                                                    data-id="refund_to_wallet" data-type="toggle"
                                                        data-image-on="{{ asset('/public/assets/admin/img/modal/refund-on.png') }}"
                                                        data-image-off="{{ asset('/public/assets/admin/img/modal/refund-off.png') }}"
                                                        data-title-on="{{ translate('messages.Want to enable') }} <strong>{{ translate('messages.Refund to Wallet feature?') }}</strong>"
                                                        data-title-off="{{ translate('messages.Want to disable') }} <strong>{{ translate('messages.Refund to Wallet feature?') }}</strong>"
                                                        data-text-on="<p>{{ translate('messages.If you enable this, Customers will automatically receive the refunded amount in their wallets.') }}</p>"
                                                        data-text-off="<p>{{ translate('If you disable this, the admin will handle the refund request in his convenient transaction channel.') }}</p>"
                                                        class="status toggle-switch-input dynamic-checkbox-toggle "
                                                        name="wallet_add_refund" id="refund_to_wallet" value="1"
                                                        {{ isset($data['wallet_add_refund']) && $data['wallet_add_refund'] == 1 ? 'checked' : '' }}>
                                                    <span class="toggle-switch-label text">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-sm-6 col-lg-6">
                                            <div class="form-group mb-0">
                                                <span class="mb-2 d-flex align-items-center text-title">{{ translate('Add fund to wallet') }}
                                                    <span class="input-label-secondary" data-toggle="tooltip"
                                                        data-placement="right"
                                                        data-original-title="{{ translate('messages.With this feature, customers can add fund to wallet if the payment module is available.') }}">
                                                        <i class="tio-info text-muted ps--3"></i>
                                                    </span>
                                                </span>
                                                <label
                                                    class="toggle-switch toggle-switch-sm d-flex justify-content-between border border-secondary rounded px-4 form-control {{ isset($data['wallet_status']) && $data['wallet_status'] == 1 ? '' : 'text-muted' }}">
                                                    <span class="pr-2">{{ translate('Status') }}
                                                    </span>
                                                    <input {{ isset($data['wallet_status']) && $data['wallet_status'] == 1 ? '' : 'disabled' }}
                                                    type="checkbox" data-id="add_fund_status" data-type="toggle"
                                                        data-image-on="{{ asset('/public/assets/admin/img/modal/wallet-on.png') }}"
                                                        data-image-off="{{ asset('/public/assets/admin/img/modal/wallet-off.png') }}"
                                                        data-title-on="{{ translate('messages.Want to enable') }} <strong>{{ translate('Add fund to Wallet feature?') }}</strong>"
                                                        data-title-off="{{ translate('messages.Want to disable') }} <strong>{{ translate('Add fund to Wallet feature?') }}</strong>"
                                                        data-text-on="<p>{{ translate('messages.If you enable this, Customers can add fund to wallet using payment module') }}</p>"
                                                        data-text-off="<p>{{ translate('If you disable this, add fund to wallet will be hidden from the customer app & website.') }}</p>"
                                                        class="status toggle-switch-input dynamic-checkbox-toggle "
                                                        name="add_fund_status" id="add_fund_status" value="1"
                                                        {{ isset($data['add_fund_status']) && $data['add_fund_status'] == 1 ? 'checked' : '' }}>
                                                    <span class="toggle-switch-label text">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="fs-12 color-656565 px-3 py-2 bg-opacity-10 rounded bg-info mt-20">
                                    <div class="d-flex align-items-center mb-0">
                                        <span class="text-info fs-16">
                                            <i class="tio-light-on"></i>
                                        </span>
                                        <ul class="mb-0 fs-12">
                                                <li>{{ translate('You can see customer wallet from Customers details page. Go to this path') }} <strong>{{ translate('customers') }} > {{ translate('Customer list') }} > {{ translate('View details') }}</strong></li>
                                                <li>
                                                    <p class="mb-0 mt-2 fs-12 color-656565">{{ translate('To add fund for a customer visit') }} <a target="_blank" href="{{ route('admin.users.customer.wallet.add-fund') }}" class="text-primary text-underline fw-semibold">{{ translate('Add fund') }}</a> {{ translate('Page') }}</p>
                                                </li>
                                            </ul>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                     <div class="card mb-20 card-container" id="loyalty_point_section">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-sm-nowrap flex-wrap">
                                <div>
                                    <h4 class="mb-1">{{translate('Customer loyalty point')}}</h4>
                                    <p class="fs-12 m-0">{{translate('If enabled, customers will earn a certain amount of points after each purchase.')}}</p>
                                </div>
                                <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end justify-content-end align-items-center gap-3">
                                    <div class="view_toggle_btn fz--14px info-dark cursor-pointer text-decoration-underline font-semibold d-flex align-items-center gap-1">
                                        {{ translate('messages.View') }}
                                        <i class="tio-chevron-down fs-22"></i>
                                    </div>

                                    <div class="mb-0">
                                        <label class="toggle-switch toggle-switch-sm mb-0">
                                            <input type="checkbox" data-type="toggle" class="status toggle-switch-input" name="loyalty_point_status" id="loyalty_point_status"
                                                    data-section="loyalty-point-section" value="1"
                                                    {{ isset($data['loyalty_point_status']) && $data['loyalty_point_status'] == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text mb-0">
                                                <span
                                                    class="toggle-switch-indicator">
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="card-details-body {{ !isset($data['loyalty_point_status']) || $data['loyalty_point_status'] != 1  ? 'd-none' : '' }}">
                                <div class="bg-light2  rounded p-xxl-20 p-3 mt-20">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <label class="input-label" for="loyalty_point_exchange_rate">1
                                                    {{ \App\CentralLogics\Helpers::currency_code() }}
                                                    {{ translate('equivalent point amount') }}
                                                    <span class="input-label-secondary"
                                                        data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('Set how many loyalty points equal one unit of your currency when converting points into wallet money.') }}"><i class="tio-info text-muted"></i>
                                                    </span>
                                                    <span class="text-danger"> *</span>
                                                </label>
                                                <input {{ isset($data['loyalty_point_status']) && $data['loyalty_point_status'] == 1 ? 'required' : 'readonly' }}
                                                id="loyalty_point_exchange_rate" type="number" class="form-control" name="loyalty_point_exchange_rate"  min="0"
                                                    value="{{ $data['loyalty_point_exchange_rate'] ?? '0' }}">
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <label class="input-label gap-0" for="loyalty_point_item_purchase_point">
                                                    {{ translate('Loyalty Point Earn Per Order') }} (%)
                                                    <span class="input-label-secondary"
                                                        data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('Specify the percentage of the total order amount that a customer will earn as loyalty points.') }}"><i class="tio-info text-muted"></i>
                                                    </span>
                                                     <span class="text-danger"> *</span>
                                                </label>
                                                <input {{ isset($data['loyalty_point_status']) && $data['loyalty_point_status'] == 1 ? 'required' : 'readonly' }} id="item_purchase_point"
                                                    type="number" class="form-control" name="loyalty_point_item_purchase_point"  min="0" value="{{ $data['loyalty_point_item_purchase_point'] ?? '0' }}">
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <label class="input-label" for="minimum_transfer_point">
                                                    {{ translate('Minimum Point Required To Convert') }}
                                                    <span class="input-label-secondary"
                                                        data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('Enter the minimum number of points a customer must collect before they can convert them into a wallet balance.') }}"><i class="tio-info text-muted"></i>
                                                    </span>
                                                     <span class="text-danger"> *</span>
                                                </label>
                                                <input {{ isset($data['loyalty_point_status']) && $data['loyalty_point_status'] == 1 ? 'required' : 'readonly' }} id="minimum_transfer_point"
                                                    type="number" class="form-control" name="loyalty_point_minimum_point" min="0" value="{{ $data['loyalty_point_minimum_point'] ?? '0' }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="fs-12 color-656565 px-3 py-2 bg-opacity-10 rounded bg-info mt-20">
                                    <div class="d-flex align-items-center gap-2 mb-0">
                                        <span class="text-info fs-16">
                                            <i class="tio-light-on"></i>
                                        </span>
                                        <span>
                                            {{ translate('To see customer loyalty point report visit') }} <a target="_blank" href="{{ route('admin.users.customer.loyalty-point.report') }}" class="text-primary text-underline fw-semibold">{{ translate('Loyalty Point Report.') }}</a>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="card card-container" id="referral_earning_section">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-sm-nowrap flex-wrap">
                                <div>
                                    <h4 class="mb-1">{{translate('Customer Referral Earning Settings')}}</h4>
                                    <p class="fs-12 m-0">{{translate('Customers will receive this wallet balance reward for sharing their referral code')}}</p>
                                </div>
                                <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end justify-content-end align-items-center gap-3">
                                    <div class="view_toggle_btn fz--14px info-dark cursor-pointer text-decoration-underline font-semibold d-flex align-items-center gap-1">
                                        {{ translate('messages.View') }}
                                        <i class="tio-chevron-down fs-22"></i>
                                    </div>
                                    <div class="mb-0">
                                        <label class="toggle-switch toggle-switch-sm mb-0">
                                            <input type="checkbox" data-type="toggle" class="status toggle-switch-input" name="ref_earning_status" id="ref_earning_status"
                                                        data-section="referrer-earning" value="1"
                                                        {{ isset($data['ref_earning_status']) && $data['ref_earning_status'] == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text mb-0">
                                                <span
                                                    class="toggle-switch-indicator">
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="card-details-body {{ !isset($data['ref_earning_status']) || $data['ref_earning_status'] != 1  ? 'd-none' : '' }} mt-20">
                                <div class="bg-light rounded p-xxl-20 p-3">
                                    <div class="py-0">
                                        <div class="row g-3 align-items-end mb-3">

                                            <div class="align-self-center  col-md-4">
                                                <div class="text-left">
                                                    <h5 class="align-items-center">
                                                        <span>
                                                            {{ translate('Who Share the Code') }}
                                                        </span>
                                                    </h5>
                                                    <p class="fs-12 color-656565">
                                                        {{ translate('Customers earn a wallet reward when a friend signs up with their code and completes a first order.') }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="bg-white rounded p-xxl-20 p-3 text-left">
                                                    <div class="card-body p-0">
                                                        <div class="form-group mb-0">
                                                            <label class="input-label" for="ref_earning_exchange_rate">
                                                                {{ translate('Earning Per Referral') }}
                                                                ({{ \App\CentralLogics\Helpers::currency_symbol() }})

                                                                <span class="input-label-secondary" data-toggle="tooltip"
                                                                    data-placement="right"
                                                                    data-original-title="{{ translate('Turn on Refer amount in the Customer Wallet section to complete this setting.') }}">
                                                                    <i class="tio-info text-muted"></i>
                                                                </span>
                                                                 <span class="text-danger"> *</span>
                                                            </label>
                                                            <input {{ isset($data['wallet_status']) && $data['wallet_status'] == 1 ? '' : 'readonly' }}
                                                            id="ref_earning_exchange_rate" type="number" step="{{ Helpers::getDecimalPlaces() }}" min="0" max="99999999999"
                                                                class="form-control" name="ref_earning_exchange_rate"
                                                                value="{{ $data['ref_earning_exchange_rate'] ?? '0' }}" data-toggle="tooltip" data-placement="right" data-original-title="Turn on Refer amount in the Customer Wallet section to complete this setting.">
                                                            @if (isset($data['wallet_status']) && $data['wallet_status'] != 1)
                                                            <p class="text-danger mt-1 mb-0 fs-12">{{ translate('Turn this option on, otherwise the customer cannot receive the reward amount') }}: <strong>{{ translate('Add fund to wallet') }}</strong> </p>
                                                            @endif

                                                            </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 align-items-end">
                                            <div class="align-self-center col-md-4 text-center">
                                                <div class="text-left">

                                                    <h5 class="align-items-center">
                                                        <span>
                                                            {{ translate('Who Use the Code') }}
                                                        </span>
                                                    </h5>
                                                    <p class="fs-12 color-656565">
                                                        {{ translate('Customers get a signup and first purchase discount when they use the referral code for a limited time.') }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="bg-white rounded p-xxl-20 p-3 text-left">
                                                    <div class="card-body p-0">
                                                        <div>
                                                            <div class="form-group">
                                                                <span
                                                                    class="mb-2 text-title d-flex align-items-center">{{ translate('Customer will get Discount on first order') }} 
                                                                    <span class="input-label-secondary" data-toggle="tooltip"
                                                                        data-placement="right"
                                                                        data-original-title="{{ translate('messages.Set the discount type and amount for users who sign up with a referral code.') }}">
                                                                        <i class="tio-info text-muted"></i>
                                                                    </span>
                                                                </span>
                                                                <label
                                                                    class="toggle-switch toggle-switch-sm d-flex justify-content-between border border-secondary rounded px-4 form-control {{ isset($data['wallet_status']) && $data['wallet_status'] == 1 ? '' : 'text-muted' }}">
                                                                    <span
                                                                        class="pr-2">{{ translate('Status') }} 
                                                                    </span>
                                                                    <input {{ isset($data['wallet_status']) && $data['wallet_status'] == 1 ? '' : 'disabled' }}
                                                                    type="checkbox" data-id="new_customer_discount_status"
                                                                        data-type="toggle"
                                                                        data-image-on="{{ asset('/public/assets/admin/img/modal/basic_campaign_on.png') }}"
                                                                        data-image-off="{{ asset('/public/assets/admin/img/modal/basic_campaign_off.png') }}"
                                                                        data-title-on="{{ translate('messages.Want to enable') }} <strong>{{ translate('messages.New customer discount?') }}</strong>"
                                                                        data-title-off="{{ translate('messages.Want to disable') }} <strong>{{ translate('messages.New customer discount?') }}</strong>"
                                                                        data-text-on="<p>{{ translate('messages.If you enable this, Customers will get discount on first order.') }}</p>"
                                                                        data-text-off="<p>{{ translate('mo. If you disable this, Customers won\'t get any discount on first order.') }}</p>"
                                                                        class="status toggle-switch-input dynamic-checkbox-toggle "
                                                                        name="new_customer_discount_status"
                                                                        id="new_customer_discount_status" value="1"
                                                                        {{ data_get($data, 'new_customer_discount_status') == 1 ? 'checked' : '' }}>
                                                                    <span class="toggle-switch-label text">
                                                                        <span class="toggle-switch-indicator"></span>
                                                                    </span>
                                                                </label>
                                                            </div>

                                                        </div>
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <div class="form-group mb-0">
                                                                    <label class="input-label" for="new_customer_discount_amount">
                                                                        {{ translate('Discount amount') }}

                                                                        <span class="{{  data_get($data, 'new_customer_discount_amount_type') != 'amount'  ? '': 'd-none' }} " id="percentage">(%)</span>
                                                                        <span  class=" {{  data_get($data, 'new_customer_discount_amount_type') == 'amount' ? '': 'd-none' }} " id='cuttency_symbol'>({{ \App\CentralLogics\Helpers::currency_symbol() }})
                                                                        </span>


                                                                        <span
                                                                            class="input-label-secondary" data-toggle="tooltip"
                                                                            data-placement="right"
                                                                            data-original-title="{{ translate('Enter the discount value for referral-based new user registrations.') }}">
                                                                            <i class="tio-info text-muted"></i>
                                                                        </span>
                                                                         <span class="text-danger"> *</span>
                                                                    </label>
                                                                    <div class="d-flex align-items-center gap-0 border rounded overflow-hidden">
                                                                        <input id="new_customer_discount_amount" type="number" step="{{ Helpers::getDecimalPlaces() }}" min="0"
                                                                        {{  isset($data['wallet_status']) && $data['wallet_status'] == 1 && data_get($data, 'new_customer_discount_status') == 1 ? 'required' : 'readonly' }}
                                                                            class="form-control border-0 rounded-0" name="new_customer_discount_amount" max='{{  data_get($data, 'new_customer_discount_amount_type') != 'amount'  ? '100': '9999999999' }}'
                                                                            value="{{data_get($data, 'new_customer_discount_amount') ?? '0' }}">
                                                                        <select   name="new_customer_discount_amount_type"  class="bg-modal-btn custom-select border-0 rounded-0 w-auto"  id="new_customer_discount_amount_type"
                                                                            {{ isset($data['wallet_status']) && $data['wallet_status'] == 1 && data_get($data, 'new_customer_discount_status') == 1 ? 'required' : 'disabled' }}
                                                                            >
                                                                                <option {{ data_get($data, 'new_customer_discount_amount_type') == 'percentage' ? "selected": '' }} value="percentage">(%)</option>
                                                                                <option {{ data_get($data, 'new_customer_discount_amount_type') == 'amount' ? "selected": '' }}  value="amount">{{ \App\CentralLogics\Helpers::currency_symbol() }}</option>
                                                                            </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group mb-0">
                                                                    <label class="input-label" for="new_customer_discount_amount_validity">
                                                                        {{ translate('Validity') }}
                                                                        <span class="input-label-secondary" data-toggle="tooltip"
                                                                            data-placement="right"
                                                                            data-original-title="{{ translate('Set how long the discount remains active after registration.') }}">
                                                                            <i class="tio-info text-muted"></i>
                                                                        </span>
                                                                         <span class="text-danger"> *</span>
                                                                    </label>
                                                                    <div class="d-flex align-items-center gap-0 border rounded overflow-hidden">
                                                                        <input id="new_customer_discount_amount_validity" type="number"  min="0" max="999"
                                                                        {{ isset($data['wallet_status']) && $data['wallet_status'] == 1 && data_get($data, 'new_customer_discount_status') == 1 ? 'required' : 'readonly' }}
                                                                            class="form-control border-0 rounded-0" name="new_customer_discount_amount_validity"
                                                                            value="{{ data_get($data, 'new_customer_discount_amount_validity') ?? '0' }}">
                                                                        <select name="new_customer_discount_validity_type" class="bg-modal-btn custom-select border-0 rounded-0 w-auto" id="new_customer_discount_validity_type"  {{ isset($data['wallet_status']) && $data['wallet_status'] == 1 &&  data_get($data, 'new_customer_discount_status') == 1 ? 'required' : 'disabled' }}>
                                                                            <option {{ data_get($data, 'new_customer_discount_validity_type') == 'day' ? "selected": '' }} value="day">{{translate('messages.Day')}}</option>
                                                                            <option {{ data_get($data, 'new_customer_discount_validity_type') == 'month' ? "selected": '' }}  value="month">{{translate('messages.month')}} </option>
                                                                            <option {{ data_get($data, 'new_customer_discount_validity_type') == 'year' ? "selected": '' }}  value="year">{{translate('messages.year')}} </option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>



                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @include('admin-views.partials._floating-submit-button')
                </div>
            </div>
        </form>

    </div>





    <div id="global_guideline_offcanvas" style="overflow-y: auto;"
         class="custom-offcanvas d-flex flex-column justify-content-between global_guideline_offcanvas">
        <div>
            <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
                <h3 class="mb-0">{{ translate('Customer Settings Guideline') }}</h3>
                <button type="button"
                        class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                        aria-label="Close">&times;</button>
            </div>

            <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                    <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#guest_checkout_guide" aria-expanded="true">
                        <div
                            class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                            <i class="tio-down-ui"></i>
                        </div>
                        <span class="font-semibold text-left fs-14 text-title">{{ translate('Guest Checkout') }}</span>
                    </button>
                    <a href="#guest_checkout_section"
                       class="text-info text-underline fs-12 text-nowrap offcanvas-close offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                </div>
                <div class="collapse mt-3 show" id="guest_checkout_guide">
                    <div class="card card-body">
                        <div class="">
                            <h5 class="mb-3">{{ translate('Guest Checkout') }}</h5>
                            <p class="fs-12 mb-0">
                                {{ translate('Customers can order without creating an account, which speeds up checkout and lifts completion rates.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                    <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#food_preference_guide" aria-expanded="true">
                        <div
                            class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                            <i class="tio-down-ui"></i>
                        </div>
                        <span class="font-semibold text-left fs-14 text-title">{{ translate('Customer Food Preference') }}</span>
                    </button>
                    <a href="#general_setup_section"
                       class="text-info text-underline fs-12 text-nowrap offcanvas-close offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                </div>
                <div class="collapse mt-3" id="food_preference_guide">
                    <div class="card card-body">
                        <div class="">
                            <h5 class="mb-3">{{ translate('Customer Food Preference') }}</h5>
                            <p class="fs-12 mb-0">
                                {{ translate('Enabling this option allows customers to view Veg/Non-Veg food preferences on the website and app.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                    <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#customer_wallet_guide" aria-expanded="true">
                        <div
                            class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                            <i class="tio-down-ui"></i>
                        </div>
                        <span class="font-semibold text-left fs-14 text-title">{{ translate('Customer wallet') }}</span>
                    </button>
                    <a href="#customer-wallet"
                       class="text-info text-underline fs-12 text-nowrap offcanvas-close offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                </div>
                <div class="collapse mt-3" id="customer_wallet_guide">
                    <div class="card card-body">
                        <div class="">
                            <h5 class="mb-3">{{ translate('Customer wallet') }}</h5>
                            <p class="fs-12 mb-0">
                                {{ translate('Customers can store funds, pay for orders and receive refunds straight to their wallet.') }}
                            </p>
                            <br>
                            <ul class="fs-12">
                                <li>
                                    <strong>{{ translate('Refund to Wallet') }}:</strong> {{ translate('Customers can pay with their wallet balance, and refunds can go straight back to it.') }}
                                </li>
                                <li>
                                    <strong>{{ translate('Add funds to wallet') }}:</strong> {{ translate('Customers can top up their wallet with digital payment methods.') }}
                                </li>
                                <li>
                                    <strong>{{ translate('Minimum add amount') }}:</strong> {{ translate('The smallest amount a customer can add to their wallet at one time.') }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                    <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#loyalty_point_guide" aria-expanded="true">
                        <div
                            class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                            <i class="tio-down-ui"></i>
                        </div>
                        <span class="font-semibold text-left fs-14 text-title">{{ translate('Customer loyalty point') }}</span>
                    </button>
                    <a href="#loyalty_point_section"
                       class="text-info text-underline fs-12 text-nowrap offcanvas-close offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                </div>
                <div class="collapse mt-3" id="loyalty_point_guide">
                    <div class="card card-body">
                        <div class="">
                            <h5 class="mb-3">{{ translate('Customer loyalty point') }}</h5>
                            <p class="fs-12 mb-0">
                                {{ translate('How many loyalty points equal one unit of currency, so customers know what their points are worth.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                    <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#referral_earning_guide" aria-expanded="true">
                        <div
                            class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                            <i class="tio-down-ui"></i>
                        </div>
                        <span class="font-semibold text-left fs-14 text-title">{{ translate('Customer Referral Earning Settings') }}</span>
                    </button>
                    <a href="#referral_earning_section"
                       class="text-info text-underline fs-12 text-nowrap offcanvas-close offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                </div>
                <div class="collapse mt-3" id="referral_earning_guide">
                    <div class="card card-body">
                        <div class="">
                            <h5 class="mb-3">{{ translate('Customer Referral Earning Settings') }}</h5>
                            <ul class="fs-12">
                                <li>{{ translate('Wallet reward a customer earns when someone uses their referral code and makes a purchase.') }}</li>
                                <li>{{ translate('Wallet reward a customer earns when someone they referred completes their first order.') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>



@endsection

@push('script_2')
<script>
    "use strict";

    $('#loyalty_point_status').on('change', function() {
        if($('#loyalty_point_status').is(':checked')){
            $('#loyalty_point_exchange_rate').removeAttr('readonly').attr('required', 'required');
            $('#item_purchase_point').removeAttr('readonly').attr('required', 'required');
            $('#minimum_transfer_point').removeAttr('readonly').attr('required', 'required');
        }else{
            $('#loyalty_point_exchange_rate').attr('readonly',true).removeAttr('required');
            $('#item_purchase_point').attr('readonly',true).removeAttr('required');
            $('#minimum_transfer_point').attr('readonly',true).removeAttr('required');
        }
    });

    $('#ref_earning_status').on('change', function() {
        if($('#ref_earning_status').is(':checked')){
            $('#ref_earning_exchange_rate').removeAttr('readonly').attr('required', 'required');
            $('#new_customer_discount_status').removeAttr('disabled');

            if($('#new_customer_discount_status').is(':checked')){
                $('#new_customer_discount_amount').removeAttr('readonly').attr('required', 'required');
                $('#new_customer_discount_amount_validity').removeAttr('readonly').attr('required', 'required');
                $('#new_customer_discount_amount_type').removeAttr('disabled').attr('required', 'required');
                $('#new_customer_discount_validity_type').removeAttr('disabled').attr('required', 'required');
            }
        }else{
            $('#ref_earning_exchange_rate').attr('readonly',true).removeAttr('required');
            $('#new_customer_discount_status').attr('disabled',true);

            if($('#new_customer_discount_status').is(':checked')){
                $('#new_customer_discount_amount').attr('readonly',true).removeAttr('required');
                $('#new_customer_discount_amount_validity').attr('readonly',true).removeAttr('required');
                $('#new_customer_discount_amount_type').attr('disabled',true).removeAttr('required');
                $('#new_customer_discount_validity_type').attr('disabled',true).removeAttr('required');
            }

        }
    }).trigger('change');

    $('#new_customer_discount_amount_type').on('change', function() {
        if($('#new_customer_discount_amount_type').val() == 'amount')
        {
            $('#percentage').addClass('d-none');
            $('#cuttency_symbol').removeClass('d-none');
            $('#new_customer_discount_amount').attr('max',99999999999);

        }
        else
        {
            $('#percentage').removeClass('d-none');
            $('#cuttency_symbol').addClass('d-none');
            $('#new_customer_discount_amount').attr('max',100);

        }
    });

</script>
@endpush

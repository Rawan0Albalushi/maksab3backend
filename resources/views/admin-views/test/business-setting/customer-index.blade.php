@extends('layouts.admin.app')

@section('title', translate('messages.Customer settings'))

@push('css_or_js')
@endpush

@section('content')
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
                                {{ translate('See and manage all customers') }}: <a href="javascript:void(0)" class="text-primary text-underline fw-semibold">{{ translate('All Customer List') }}</a>
                            </span>
                        </div>
                    </div>
                    <div class="card mb-20">
                        <div class="card-body">
                            <div class="row g-3 align-items-center">
                                <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                                    <div>
                                        <h4 class="mb-1">
                                            {{ translate('Guest Checkout') }}
                                        </h4>
                                        <p class="mb-0 fs-12">
                                            {{ translate('This option allows customers to checkout and complete their orders without logging in') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                                     @php($guest_checkout_status = \App\CentralLogics\Helpers::get_business_settings('guest_checkout_status', false) ?? 0)
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
                                        {{ translate('When you turn on this feature, you may increase your orders & order amount.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-20">
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
                                        @php($country_picker_status = \App\CentralLogics\Helpers::get_business_settings('country_picker_status', false) ?? 0)
                                        <div class="form-group mb-0">
                                            <span class="mb-10px d-flex align-items-center">
                                                <span class="text-title">
                                                    {{translate('Country picker') }}
                                                </span>
                                                <span class="form-label-secondary text-danger d-flex" data-toggle="tooltip"
                                                        data-placement="right"
                                                        data-original-title="{{ translate('messages.If you enable this option, in all phone no field will show a country picker list.')}}"><i class="tio-info text-muted ps--3"></i>
                                                </span>
                                            </span>
                                            <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1">
                                                        {{translate('messages.Status') }}
                                                    </span>
                                                </span>
                                                <input type="checkbox" data-id="country_picker_status" data-type="toggle"
                                                       data-image-on="{{ asset('/public/assets/admin/img/modal/mail-success.png') }}"
                                                       data-image-off="{{ asset('/public/assets/admin/img/modal/mail-warning.png') }}"
                                                       data-title-on="<strong>{{ translate('messages.Want to enable country picker?') }}</strong>"
                                                       data-title-off="<strong>{{ translate('messages.Want to disable country picker?') }}</strong>"
                                                       data-text-on="<p>{{ translate('messages.If you enable this, user can select country from country picker') }}</p>"
                                                       data-text-off="<p>{{ translate('messages.If you disable this, user cannot select country from country picker, default country will be selected') }}</p>"
                                                       class="status toggle-switch-input dynamic-checkbox-toggle" value="1"
                                                       name="country_picker_status" id="country_picker_status" {{ $country_picker_status == 1 ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        @php($vnv = \App\CentralLogics\Helpers::get_business_settings('toggle_veg_non_veg', false) ?? 0)
                                        <div class="form-group mb-0">
                                            <span class="mb-10px d-flex align-items-center">
                                                <span class="text-title">
                                                    {{ translate('messages.Customer\'s Food Preference') }}
                                                </span>
                                                <span class="form-label-secondary text-danger d-flex" data-toggle="tooltip"
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
                                                        name="vnv" id="vnv1" {{ $vnv == 1 ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="fs-12 text-dark px-3 py-2 rounded bg-warning-10 mt-20">
                                <div class="d-flex align-items-center gap-2 mb-0">
                                    <span class="text-warning fs-14">
                                        <i class="tio-info"></i>
                                    </span>
                                    <span class="color-656566">
                                        {{ translate('To do business in multiple countries, you need to turn on the country picker feature.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-20 card-container">
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


                 

                                            name="customer_wallet" id="wallet_status" value="1"
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
                            <div class="card-details-body">
                                <div class="bg-light2  rounded p-xxl-20 p-3 mt-20">
                                    <div class="row g-3">
                                         <div class="col-sm-6 col-lg-4">
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
                                                        name="refund_to_wallet" id="refund_to_wallet" value="1"
                                                        {{ isset($data['wallet_add_refund']) && $data['wallet_add_refund'] == 1 ? 'checked' : '' }}>
                                                    <span class="toggle-switch-label text">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                                </label>
                                                <p class="mb-0 mt-2 fs-12 color-656565">{{ translate('To add fund for a customer visit') }} <a href="javascript:void(0)" class="text-primary text-underline fw-semibold">{{ translate('Add fund') }}</a> {{ translate('Page') }}</p>
                                            </div>
                                        </div>

                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <span class="mb-2 d-flex align-items-center text-title">{{ translate('Customer can add fund to wallet') }}
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
                                                <p class="mb-0 mt-2 fs-12 color-656565">{{ translate('To add fund for a customer visit') }} <a href="javascript:void(0)" class="text-primary text-underline fw-semibold">{{ translate('Add fund') }}</a> {{ translate('Page') }}</p>
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
                                            {{ translate('You can see customer wallet from Customers details page. Go to this path') }} <strong>{{ translate('Customers > Customer List > View Details.') }}</strong>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                     <div class="card mb-20 card-container">
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
                                            <input type="checkbox" data-type="toggle" class="status toggle-switch-input" name="customer_loyalty_point" id="customer_loyalty_point"
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
                            <div class="card-details-body">
                                <div class="bg-light2  rounded p-xxl-20 p-3 mt-20">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <label class="input-label" for="loyalty_point_exchange_rate">1
                                                    {{ \App\CentralLogics\Helpers::currency_code() }}
                                                    {{ translate('equivalent point amount') }}
                                                    <span class="input-label-secondary"
                                                        data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('Content need') }}"><i class="tio-info text-muted"></i>
                                                    </span>
                                                </label>
                                                <input {{ isset($data['loyalty_point_status']) && $data['loyalty_point_status'] == 1 ? 'required' : 'readonly' }}
                                                id="loyalty_point_exchange_rate" type="number" class="form-control" name="loyalty_point_exchange_rate" step=".001" min="0"
                                                    value="{{ $data['loyalty_point_exchange_rate'] ?? '0' }}">
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <label class="input-label gap-0" for="item_purchase_point">
                                                    {{ translate('Loyalty Point Earn Per Order') }} (%)
                                                    <span class="input-label-secondary"
                                                        data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('messages.On every purchase this percent of amount will be added as loyalty point on his account') }}"><i class="tio-info text-muted"></i>
                                                    </span>
                                                </label>
                                                <input {{ isset($data['loyalty_point_status']) && $data['loyalty_point_status'] == 1 ? 'required' : 'readonly' }} id="item_purchase_point"
                                                    type="number" class="form-control" name="item_purchase_point" step=".001" min="0" value="{{ $data['loyalty_point_item_purchase_point'] ?? '0' }}">
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <label class="input-label" for="minimum_transfer_point">
                                                    {{ translate('Minimum Point Required To Convert') }}
                                                    <span class="input-label-secondary"
                                                        data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('Content need') }}"><i class="tio-info text-muted"></i>
                                                    </span>
                                                </label>
                                                <input {{ isset($data['loyalty_point_status']) && $data['loyalty_point_status'] == 1 ? 'required' : 'readonly' }} id="minimum_transfer_point"
                                                    type="number" class="form-control" name="minimun_transfer_point" min="0" step=".001" value="{{ $data['loyalty_point_minimum_point'] ?? '0' }}">
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
                                            {{ translate('To see customer loyalty point report visit') }} <a href="javascript:void(0)" class="text-primary text-underline fw-semibold">{{ translate('Loyalty Point Report.') }}</a>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="card card-container">
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

                            <div class="card-details-body mt-20">
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
                                                        {{ translate('Wallet reward a customer earns when a friend signs up with their code and completes a first order.') }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="bg-white rounded p-xxl-20 p-3 text-left">
                                                    <div class="card-body p-0">
                                                        <div class="form-group mb-0">
                                                            <label class="input-label" for="ref_earning_exchange_rate">
                                                                {{ translate('Earning Per Referral') }}
                                                                {{ \App\CentralLogics\Helpers::currency_code() }}

                                                                <span class="input-label-secondary" data-toggle="tooltip"
                                                                    data-placement="right"
                                                                    data-original-title="{{ translate('Content need') }}">
                                                                    <i class="tio-info text-muted"></i>
                                                                </span>
                                                            </label>
                                                            <input {{ isset($data['wallet_status']) && $data['wallet_status'] == 1 ? '' : 'readonly' }}
                                                            id="ref_earning_exchange_rate" type="number" step=".001" min="0" max="99999999999"
                                                                class="form-control" name="ref_earning_exchange_rate"
                                                                value="{{ $data['ref_earning_exchange_rate'] ?? '0' }}" data-toggle="tooltip" data-placement="right" data-original-title="Turn on Refer amount in the Customer Wallet section to complete this setting.">
                                                            <p class="text-danger mt-1 mb-0 fs-12">{{ translate('The customer cannot receive the reward amount unless this option is on') }}: <strong>{{ translate('Add fund to wallet') }}</strong> </p>
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
                                                        {{ translate('Customers who sign up with a referral code get a limited-time discount.') }}
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
                                                                    </label>
                                                                    <div class="d-flex align-items-center gap-0 border rounded overflow-hidden">
                                                                        <input id="new_customer_discount_amount" type="number" step=".001" min="0"
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
                                                                    </label>
                                                                    <div class="d-flex align-items-center gap-0 border rounded overflow-hidden">
                                                                        <input id="new_customer_discount_amount_validity" type="number" step="1" min="0" max="999"
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


                    <div class="col-lg-12">
                        <div class="btn--container justify-content-end mt-20">
                            <button type="reset" id="reset_btn"
                                class="btn btn--reset location-reload"><i class="tio-refresh"></i> {{ translate('Reset') }}</button>
                            <button type="submit" id="submit"
                                class="btn btn--primary"><i class="tio-save"></i> {{ translate('Save information') }}</button>
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

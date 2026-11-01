@extends('layouts.admin.app')

@section('title', translate('Edit coupon'))

@php($isServiceModule = \Illuminate\Support\Facades\Config::get('module.current_module_type') == 'service')
@php($isRentalModule = \Illuminate\Support\Facades\Config::get('module.current_module_type') == 'rental')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/edit.png') }}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('messages.Coupon update') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Change this coupon\'s discount, its limits or how long it stays valid.') }}</p>
        </div>
        <div class="card">
            <div class="card-body">
                <form action="{{ route('admin.coupon.update', [$coupon['id']]) }}" method="post" class="custom-validation">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            @if ($language)
                                <ul class="nav nav-tabs mb-4">
                                    <li class="nav-item">
                                        <a class="nav-link lang_link active" href="#"
                                            id="default-link">{{ translate('Default') }}</a>
                                    </li>
                                    @foreach ($language as $lang)
                                        <li class="nav-item">
                                            <a class="nav-link lang_link" href="#"
                                                id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="lang_form" id="default-form">
                                    <div class="form-group error-wrapper">
                                        <label class="input-label" for="default_title">{{ translate('messages.Title') }}
                                            ({{ translate('Default') }})</label>
                                        <input type="text" name="title[]" id="default_title" class="form-control"
                                            placeholder="{{ translate('messages.New coupon') }}"
                                            value="{{ $coupon?->getRawOriginal('title') }}">
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                </div>
                                @foreach ($language as $lang)
                                    <?php
                                    if (count($coupon['translations'])) {
                                        $translate = [];
                                        foreach ($coupon['translations'] as $t) {
                                            if ($t->locale == $lang && $t->key == 'title') {
                                                $translate[$lang]['title'] = $t->value;
                                            }
                                        }
                                    }
                                    ?>
                                    <div class="d-none lang_form" id="{{ $lang }}-form">
                                        <div class="form-group error-wrapper">
                                            <label class="input-label"
                                                for="{{ $lang }}_title">{{ translate('messages.Title') }}
                                                ({{ strtoupper($lang) }})</label>
                                            <input type="text" name="title[]" id="{{ $lang }}_title"
                                                class="form-control" placeholder="{{ translate('messages.New coupon') }}"
                                                value="{{ $translate[$lang]['title'] ?? '' }}" required>
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{ $lang }}">
                                    </div>
                                @endforeach
                            @else
                                <div id="default-form">
                                    <div class="form-group error-wrapper">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.Title') }}
                                            ({{ translate('Default') }})</label>
                                        <input type="text" name="title[]" class="form-control"
                                            placeholder="{{ translate('messages.New coupon') }}"
                                            value="{{ $coupon['title'] }}" maxlength="100">
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                </div>
                            @endif
                        </div>
                        <div class="col-md-4 col-lg-3 col-sm-6">
                            <div class="form-group m-0 error-wrapper">
                                <label class="input-label"
                                    for="exampleFormControlInput1">{{ translate('Coupon type') }}</label>
                                <select name="coupon_type" id="coupon_type" class="form-control js-select2-custom" required>
                                    <option value="store_wise" {{ $coupon['coupon_type'] == 'store_wise' ? 'selected' : '' }}>
                                        {{ $isServiceModule ? translate('Provider wise') : translate('messages.Store wise') }}</option>
                                    <option value="zone_wise" {{ $coupon['coupon_type'] == 'zone_wise' ? 'selected' : '' }}>
                                        {{ translate('Zone wise') }}</option>
                                    @if ((!$isServiceModule && !$isRentalModule) || $coupon['coupon_type'] == 'free_delivery')
                                        <option value="free_delivery"
                                            {{ $coupon['coupon_type'] == 'free_delivery' ? 'selected' : '' }}>
                                            {{ translate('Free delivery') }}</option>
                                    @endif
                                    <option value="first_order" {{ $coupon['coupon_type'] == 'first_order' ? 'selected' : '' }}>
                                        {{ $isServiceModule ? translate('First booking') : translate('messages.First order') }}</option>
                                    @if (\App\CentralLogics\Helpers::get_business_settings('pro_member_status') == 1 || $coupon['coupon_type'] == 'pro_customer')
                                        <option value="pro_customer" {{ $coupon['coupon_type'] == 'pro_customer' ? 'selected' : '' }}>
                                            {{ translate('Pro customer') }}</option>
                                    @endif
                                    <option value="default" {{ $coupon['coupon_type'] == 'default' ? 'selected' : '' }}>
                                        {{ translate('Default') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-3 col-sm-6" id="store_wise">
                            <div class="form-group m-0 error-wrapper">
                                <label class="input-label"
                                    for="exampleFormControlSelect1">{{ $isServiceModule ? translate('Provider') : translate('messages.Store') }}<span
                                        class="input-label-secondary"></span></label>
                                <select name="store_ids[]" class="js-data-example-ajax form-control"
                                    title="{{ $isServiceModule ? translate('Select provider') : translate('Select Restaurant') }}">
                                    @if ($coupon->coupon_type == 'store_wise')
                                        @php($store = \App\Models\Store::with('storeConfig')->find(json_decode($coupon->data)[0]))
                                        @if ($store)
                                            <option value="{{ $store->id }}" data-verified="{{ (int) $store->verified_seller }}">{{ $store->name }}</option>
                                        @endif
                                    @else
                                        <option selected>{{ $isServiceModule ? translate('Select provider') : translate('Select store') }}</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-3 col-sm-6" id="zone_wise">
                            <div class="form-group m-0 error-wrapper">
                                <label class="input-label"
                                    for="exampleFormControlInput1">{{ translate('Select zone') }}</label>
                                <select name="zone_ids[]" id="choice_zones" class="form-control multiple-select2"
                                    multiple="multiple" placeholder="{{ translate('Select zone') }}">
                                    @foreach ($zones as $zone)
                                        <option value="{{ $zone->id }}"
                                            {{ $coupon->coupon_type == 'zone_wise' && json_decode($coupon->data) ? (in_array($zone->id, json_decode($coupon->data)) ? 'selected' : '') : '' }}>
                                            {{ $zone->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group col-md-4 col-lg-3 col-sm-6 error-wrapper" id="customer_wise"
                            style="display: {{ in_array($coupon['coupon_type'], ['zone_wise', 'first_order', 'pro_customer']) ? 'none' : 'block' }}">
                            <label class="input-label"
                                for="select_customer">{{ translate('Select customer') }}</label>
                            <select name="customer_ids[]" id="select_customer" class="form-control multiple-select2"
                                multiple="multiple" data-ajax-url="{{ route('admin.users.customer.select-list') }}"
                                placeholder="{{ translate('Select customer') }}">
                                <option value="all"
                                    {{ in_array('all', json_decode($coupon->customer_id)) ? 'selected' : '' }}>
                                    {{ translate('All') }} </option>
                                @foreach ($selected_customers as $user)
                                    <option value="{{ $user->id }}" selected>
                                        {{ $user->f_name . ' ' . $user->l_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 col-lg-3 col-sm-6">
                            <div class="form-group m-0 error-wrapper">
                                <div class="d-flex justify-content-between">
                                    <label class="input-label"
                                        for="exampleFormControlInput1">{{ translate('messages.code') }}</label>
                                </div>
                                <input type="text" class="form-control" value="{{ $coupon['code'] }}"
                                    maxlength="100" disabled>
                                <input type="hidden" name="code" value="{{ $coupon['code'] }}">
                            </div>
                        </div>
                        <div id="limit_for_same_user" class="col-md-4 col-lg-3 col-sm-6">
                            <div class="form-group m-0 error-wrapper">
                                <label class="input-label"
                                    for="limit">{{ translate('Limit for same user') }}</label>
                                <input type="number" name="limit" id="coupon_limit"
                                    data-value="{{ $coupon['limit'] }}" value="{{ (int) $coupon['limit'] > 0 ? $coupon['limit'] : '' }}"
                                    class="form-control" min="1" max="100" placeholder="{{ translate('Ex') . ': 10' }}">
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-3 col-sm-6" id="start_date_wrap"
                            style="display: {{ $coupon['coupon_type'] == 'pro_customer' ? 'none' : 'block' }}">
                            <div class="form-group m-0 error-wrapper">
                                <label class="input-label" for="">{{ translate('Start date') }}</label>
                                <input type="date" name="start_date" class="form-control" id="date_from"
                                    placeholder="{{ translate('Select date') }}"
                                    value="{{ $coupon['start_date'] ? date('Y-m-d', strtotime($coupon['start_date'])) : '' }}">
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-3 col-sm-6" id="expire_date_wrap"
                            style="display: {{ $coupon['coupon_type'] == 'pro_customer' ? 'none' : 'block' }}">
                            <div class="form-group m-0 error-wrapper">
                                <label class="input-label" for="date_to">{{ translate('messages.Expire date') }}</label>
                                <input type="date" name="expire_date" class="form-control"
                                    placeholder="{{ translate('Select date') }}" id="date_to"
                                    value="{{ $coupon['expire_date'] ? date('Y-m-d', strtotime($coupon['expire_date'])) : '' }}"
                                    data-hs-flatpickr-options='{
                                     "dateFormat": "Y-m-d"
                                   }'>
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-3 col-sm-6" id="discount_type_wrap">
                            <div class="form-group m-0 error-wrapper">
                                <label class="input-label"
                                    for="discount_type">{{ translate('Discount type') }}</label>
                                <select name="discount_type" id="discount_type" class="form-control js-select2-custom">
                                    <option value="amount" {{ $coupon['discount_type'] == 'amount' ? 'selected' : '' }}>
                                        {{ translate('Amount') }}
                                        ({{ \App\CentralLogics\Helpers::currency_symbol() }})
                                    </option>
                                    <option value="percent" {{ $coupon['discount_type'] == 'percent' ? 'selected' : '' }}>
                                        {{ translate('Percent') }} (%)
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-3 col-sm-6">
                            <div class="form-group m-0 error-wrapper">
                                <label class="input-label"
                                    for="exampleFormControlInput1">{{ translate('messages.Min purchase') }}
                                    ({{ \App\CentralLogics\Helpers::currency_symbol() }})</label>
                                <input type="number" id="min_purchase" name="min_purchase" step="0.01"
                                    value="{{ (float) $coupon['min_purchase'] > 0 ? $coupon['min_purchase'] : '' }}" min="1" max="999999999999.99"
                                    class="form-control" placeholder="100">
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-3 col-sm-6" id="discount_wrap">
                            <div class="form-group m-0 error-wrapper">
                                <label class="input-label" for="discount">{{ translate('Discount') }}
                                    <span class="input-label-secondary text--title" data-toggle="tooltip"
                                        data-placement="right"
                                        data-original-title="{{ $isServiceModule ? translate('Currently you need to manage discount with the Provider.') : translate('Currently you need to manage discount with the restaurant.') }}">
                                        <i class="tio-info-outined"></i>
                                    </span>
                                </label>
                                {{-- required is toggled by coupon_type_change() in coupon-edit.js, not
                                     hardcoded here -- a free-delivery coupon has no discount to validate. --}}
                                <input type="number" id="discount" min="1" max="999999999999.99"
                                    step="0.01" value="{{ $coupon['discount'] }}" name="discount"
                                    class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-3 col-sm-6" id="max_discount_wrap">
                            <div class="form-group m-0 error-wrapper">
                                <label class="input-label"
                                    for="exampleFormControlInput1">{{ translate('messages.Max discount') }}
                                    ({{ \App\CentralLogics\Helpers::currency_symbol() }})</label>
                                <input type="number" min="{{ $coupon['discount_type'] == 'percent' ? '0.01' : '0' }}"
                                    max="999999999999.99" step="0.01"
                                    value="{{ $coupon['max_discount'] }}" name="max_discount" id="max_discount"
                                    class="form-control"
                                    {{ $coupon['discount_type'] == 'amount' ? 'readonly="readonly"' : '' }}
                                    {{ $coupon['discount_type'] == 'percent' ? 'required' : '' }}>
                            </div>
                        </div>

                    </div>
                    <div class="btn--container justify-content-end mt-4">
                        <button type="reset" id="reset_btn"
                            class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                        <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('Update') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <input type="hidden" id="min-purchase-toast"
        value="{{ translate('messages.Discount amount cannot be greater than minimum purchase amount') }}">

@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/js/view-pages/coupon-edit.js"></script>
    <script>
        "use strict";
        coupon_type_change('{{ $coupon->coupon_type }}');

        $(document).on('ready', function() {
            let module_id = 0;
            @if ($coupon['expire_date'])
                $('#date_from').attr('max', '{{ date('Y-m-d', strtotime($coupon['expire_date'])) }}');
            @endif
            @if ($coupon['start_date'])
                $('#date_to').attr('min', '{{ date('Y-m-d', strtotime($coupon['start_date'])) }}');
            @endif
            @if ($coupon['discount_type'] == 'amount')
                $('#max_discount').attr("readonly", "true");
                $('#max_discount').val(0);
            @endif


            $('.js-data-example-ajax').select2({
                ajax: {
                    url: '{{ route('admin.store.get-stores') }}',
                    data: function(params) {
                        return {
                            q: params.term, // search term
                            page: params.page,
                            module_id: module_id,
                            include_addon_providers: 1
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    __port: function(params, success, failure) {
                        let $request = $.ajax(params);

                        $request.then(success);
                        $request.fail(failure);

                        return $request;
                    }
                }
            });
            // INITIALIZATION OF FLATPICKR
            // =======================================================
            $('.js-flatpickr').each(function() {
                $.HSCore.components.HSFlatpickr.init($(this));
            });
        });
    </script>
@endpush

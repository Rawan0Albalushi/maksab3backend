@extends('layouts.admin.app')

@section('title', translate('messages.coupons'))

@php($isServiceModule = \Illuminate\Support\Facades\Config::get('module.current_module_type') == 'service')
@php($isRentalModule = \Illuminate\Support\Facades\Config::get('module.current_module_type') == 'rental')

@php($coupon_type_labels = [
    'store_wise' => $isServiceModule ? translate('Provider wise') : translate('messages.store_wise'),
    'zone_wise' => translate('Zone wise'),
    'free_delivery' => translate('Free delivery'),
    'first_order' => $isServiceModule ? translate('First booking') : translate('messages.first_order'),
    'pro_customer' => translate('Pro customer'),
    'default' => translate('Default'),
])
@php($lifecycle_labels = [
    'running' => translate('messages.Running'),
    'scheduled' => translate('messages.Scheduled'),
    'ended' => translate('messages.Ended'),
])

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/add.png') }}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('Add new coupon') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('A code customers type at checkout for money off, with the limits you set on it.') }}</p>
        </div>
        <div class="row g-2">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('admin.coupon.store') }}" method="POST" class="">
                            @csrf
                            <div class="row">
                                <div class="col-12">
                                    @if ($language)
                                        <ul class="nav nav-tabs mb-3 border-0">
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
                                                <label class="input-label"
                                                    for="default_title">{{ translate('messages.Title') }}
                                                     ({{ translate('Default') }})
                                                </label>
                                                <input type="text" value="{{ old('title.0') }}" name="title[]"
                                                    id="default_title" required class="form-control"
                                                    placeholder="{{ translate('messages.New coupon') }}">
                                            </div>
                                            <input type="hidden" name="lang[]" value="default">
                                        </div>
                                        @foreach ($language as $key => $lang)
                                            <div class="d-none lang_form" id="{{ $lang }}-form">
                                                <div class="form-group error-wrapper">
                                                    <label class="input-label"
                                                        for="{{ $lang }}_title">{{ translate('messages.Title') }}
                                                        ({{ strtoupper($lang) }})
                                                    </label>
                                                    <input type="text" name="title[]"
                                                        value="{{ old('title.' . $key + 1) }}"
                                                        id="{{ $lang }}_title" class="form-control"
                                                        placeholder="{{ translate('messages.New coupon') }}">
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
                                                    placeholder="{{ translate('messages.New coupon') }}">
                                            </div>
                                            <input type="hidden" name="lang[]" value="default">
                                        </div>
                                    @endif
                                </div>
                                <div class="col-md-4 col-lg-3 col-sm-6">
                                    <div class="form-group error-wrapper">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('Coupon type') }}</label>
                                        <select name="coupon_type" id="coupon_type" class="form-control js-select2-custom" required>
                                            <option disabled selected>{{ translate('messages.Select coupon type') }}
                                            </option>
                                            <option value="store_wise"
                                                {{ old('coupon_type') == 'store_wise' ? 'selected' : '' }}>
                                                {{ $isServiceModule ? translate('Provider wise') : translate('messages.Store wise') }}</option>
                                            <option value="zone_wise"
                                                {{ old('coupon_type') == 'zone_wise' ? 'selected' : '' }}>
                                                {{ translate('Zone wise') }}</option>
                                            @if (!$isServiceModule && !$isRentalModule)
                                                <option value="free_delivery"
                                                    {{ old('coupon_type') == 'free_delivery' ? 'selected' : '' }}>
                                                    {{ translate('Free delivery') }}
                                                </option>
                                            @endif
                                            <option value="first_order"
                                                {{ old('coupon_type') == 'first_order' ? 'selected' : '' }}>
                                                {{ $isServiceModule ? translate('First booking') : translate('messages.First order') }}</option>
                                            @if (\App\CentralLogics\Helpers::get_business_settings('pro_member_status') == 1)
                                                <option value="pro_customer"
                                                    {{ old('coupon_type') == 'pro_customer' ? 'selected' : '' }}>
                                                    {{ translate('Pro customer') }}</option>
                                            @endif
                                            <option value="default"
                                                {{ old('coupon_type') == 'default' ? 'selected' : '' }}>
                                                {{ translate('Default') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 col-lg-3 col-sm-6" id="store_wise">
                                    <div class="form-group error-wrapper">
                                        <label class="input-label"
                                            for="exampleFormControlSelect1">{{ $isServiceModule ? translate('Provider') : translate('messages.Store') }}<span
                                                class="input-label-secondary"></span></label>
                                        <select name="store_ids[]" id="store_id" class="js-data-example-ajax form-control"
                                            data-placeholder="{{ $isServiceModule ? translate('Select provider') : translate('Select store') }}"
                                            title="{{ $isServiceModule ? translate('Select provider') : translate('Select store') }}">
                                            <option disabled selected>{{ $isServiceModule ? translate('Select provider') : translate('Select store') }}
                                            </option>
                                            @foreach ($selected_stores as $store)
                                                <option value="{{ $store->id }}" data-verified="{{ (int) $store->verified_seller }}" selected>{{ $store->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 col-lg-3 col-sm-6" id="zone_wise">
                                    <div class="form-group error-wrapper">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('Select zone') }}</label>
                                        <select name="zone_ids[]" id="choice_zones" class="form-control multiple-select2"
                                            multiple="multiple" data-placeholder="{{ translate('Select zone') }}">
                                            @foreach ($zones as $zone)
                                                <option value="{{ $zone->id }}"
                                                    {{ old('zone_ids') && in_array($zone->id, old('zone_ids')) ? 'selected' : '' }}>
                                                    {{ $zone->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-6 col-sm-6" id="customer_wise">

                                    <div class="form-group pickup-zone-tag error-wrapper">
                                        <label class="input-label"
                                            for="select_customer">{{ translate('Select customer') }}</label>
                                        <select name="customer_ids[]" id="select_customer"
                                            class="form-control  multiple-select2" multiple="multiple"
                                            data-ajax-url="{{ route('admin.users.customer.select-list') }}"
                                            data-placeholder="{{ translate('Select customer') }}">
                                            <option value="all"
                                                {{ old('customer_ids') && in_array('all', old('customer_ids')) ? 'selected' : '' }}>
                                                {{ translate('All') }} </option>
                                            @foreach ($selected_customers as $user)
                                                <option class="select_customer_option" value="{{ $user->id }}" selected>
                                                    {{ $user->f_name . ' ' . $user->l_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 col-lg-3 col-sm-6">
                                    <div class="form-group error-wrapper">
                                        <div class="d-flex justify-content-between">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('messages.code') }}</label>
                                            <label class="input-label generate-code" id="generate_code"
                                                data-url="{{ route('admin.coupon.generate-check-code') }}"
                                                data-success-message="{{ translate('messages.Coupon code generated successfully') }}"
                                                style="cursor: pointer;"><i
                                                    class="tio-hand-draw"></i>{{ translate('Generate code') }}</label>
                                        </div>

                                        <input type="text" name="code" class="form-control"
                                            value="{{ old('code') }}"
                                            placeholder="{{ \Illuminate\Support\Str::random(8) }}" required
                                            maxlength="100">
                                    </div>
                                </div>
                                <div id="limit_for_same_user" class="col-md-4 col-lg-3 col-sm-6">
                                    <div class="form-group error-wrapper">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('Limit for same user') }}</label>
                                        <input type="number" name="limit" value="{{ old('limit') }}"
                                            id="coupon_limit" class="form-control" placeholder="EX: 10" min="1"
                                            max="100">
                                    </div>
                                </div>
                                <div class="col-md-4 col-lg-3 col-sm-6" id="start_date_wrap">
                                    <div class="form-group error-wrapper">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('Start date') }}</label>
                                        <input type="date" name="start_date" value="{{ old('start_date') }}"
                                            class="form-control" id="date_from" required>
                                    </div>
                                </div>
                                <div class="col-md-4 col-lg-3 col-sm-6" id="expire_date_wrap">
                                    <div class="form-group error-wrapper">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.Expire date') }}</label>
                                        <input type="date" name="expire_date" value="{{ old('expire_date') }}"
                                            class="form-control" id="date_to" required>
                                    </div>
                                </div>
                                <div class="col-md-4 col-lg-3 col-sm-6" id="discount_type_wrap">
                                    <div class="form-group error-wrapper">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('Discount type') }}</label>
                                        <select name="discount_type" class="form-control js-select2-custom" id="discount_type" required>
                                            <option value="amount"
                                                {{ old('discount_type') == 'amount' ? 'selected' : '' }}>
                                                {{ translate('Amount') }}
                                                ({{ \App\CentralLogics\Helpers::currency_symbol() }})
                                            </option>
                                            <option value="percent"
                                                {{ old('discount_type') == 'percent' ? 'selected' : '' }}>
                                                {{ translate('Percent') }} (%)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 col-lg-3 col-sm-6">
                                    <div class="form-group error-wrapper">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.Min purchase') }}
                                            ({{ \App\CentralLogics\Helpers::currency_symbol() }})</label>
                                        <input type="number" step="0.01" id="min_purchase"
                                            value="{{ old('min_purchase') }}" name="min_purchase" min="1"
                                            max="999999999999.99" class="form-control" placeholder="100">
                                    </div>
                                </div>

                                <div class="col-md-4 col-lg-3 col-sm-6" id="discount_wrap">
                                    <div class="form-group error-wrapper">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('Discount') }}
                                            <span class="input-label-secondary text--title" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ $isServiceModule ? translate('Currently you need to manage discount with the Provider.') : translate('Currently you need to manage discount with the store.') }}">
                                                <i class="tio-info-outined"></i>
                                            </span>
                                        </label>
                                        {{-- required is toggled by coupon_type_change() in coupon-index.js, not
                                             hardcoded here -- a free-delivery coupon has no discount to validate. --}}
                                        <input type="number" step="0.01" min="1" max="999999999999.99"
                                            value="{{ old('discount') }}" name="discount" id="discount"
                                            class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4 col-lg-3 col-sm-6" id="max_discount_wrap">
                                    <div class="form-group error-wrapper">
                                        <label class="input-label"
                                            for="max_discount">{{ translate('messages.Max discount') }}
                                            ({{ \App\CentralLogics\Helpers::currency_symbol() }})</label>
                                        <input type="number" step="0.01"
                                            min="{{ old('discount_type') == 'percent' ? '0.01' : '0' }}"
                                            value="{{ old('max_discount') ?? 0 }}" max="999999999999.99"
                                            name="max_discount" id="max_discount" class="form-control"
                                            {{ old('discount_type') == 'percent' ? 'required' : 'readonly' }}>
                                    </div>
                                </div>

                            </div>
                            <div class="btn--container justify-content-end">
                                <button type="reset" id="reset_btn"
                                    class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                                <button type="submit"
                                    class="btn btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Submit') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <div class="search--button-wrapper">
                            @include('partials._table-head', [
                                'title'    => translate('Coupon list'),
                                'subtitle' => translate('messages.Discount coupons customers can apply at checkout.'),
                                'count'    => $coupons->total(),
                                'count_id' => 'itemCount',
                            ])
                            <form class="search-form min--270">

                                <div class="input-group input--group">
                                    <input id="datatableSearch" type="search" name="search"
                                        value="{{ request()?->search ?? null }}" class="form-control"
                                        placeholder="{{ translate('messages.Ex') . ': ' . translate('Coupon title or code') }}"
                                        aria-label="{{ translate('Search') }}">
                                    <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                                </div>
                            </form>
                            @if (request()->input('search'))
                                <button type="reset" class="btn btn--primary ml-2 location-reload-to-base"
                                    data-url="{{ url()->full() }}"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                            @endif


                            <div class="hs-unfold mr-2">
                                <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                                    href="javascript:;"
                                    data-hs-unfold-options='{
                                            "target": "#usersExportDropdown",
                                            "type": "css-animation"
                                        }'>
                                    <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                                </a>

                                <div id="usersExportDropdown"
                                    class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">

                                    <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                    <a id="export-excel" class="dropdown-item"
                                        href="
                                        {{ route('admin.coupon.coupon_export', ['type' => 'excel', request()->getQueryString()]) }}
                                        ">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                            src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                            alt="Image Description">
                                        Excel
                                    </a>
                                    <a id="export-csv" class="dropdown-item"
                                        href="
                                    {{ route('admin.coupon.coupon_export', ['type' => 'csv', request()->getQueryString()]) }}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                            src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                            alt="Image Description">
                                        CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive datatable-custom" id="table-div">
                        <table id="columnSearchDatatable"
                            class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                            data-hs-datatables-options='{
                                "order": [],
                                "orderCellsTop": true,

                                "entries": "#datatableEntries",
                                "isResponsive": false,
                                "isShowPaging": false,
                                "paging":false
                               }'>
                            <thead class="thead-light">
                                <tr>
                                    <th class="border-0">{{ translate('messages.Title') }}</th>
                                    <th class="border-0">{{ translate('messages.code') }}</th>
                                    <th class="border-0">{{ translate('Type') }}</th>
                                    <th class="border-0 col--numeric">{{ translate('Discount') }}</th>
                                    <th class="border-0 col--numeric">{{ Config::get('module.current_module_type') == 'rental' ? translate('Min trip amount') : translate('messages.Min purchase') }}</th>
                                    <th class="border-0 col--numeric">{{ translate('messages.Total uses') }}</th>
                                    <th class="border-0">{{ translate('messages.Validity') }}</th>
                                    <th class="border-0">{{ translate('messages.Status') }}</th>
                                    <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                                </tr>
                            </thead>

                            <tbody id="set-rows">
                                @foreach ($coupons as $coupon)
                                    @php($expires = $coupon['expire_date'] ? \Carbon\Carbon::parse($coupon['expire_date']) : null)
                                    @php($starts = $coupon['start_date'] ? \Carbon\Carbon::parse($coupon['start_date']) : null)
                                    @php($days_left = $expires ? (int) \Carbon\Carbon::now()->startOfDay()->diffInDays($expires->copy()->startOfDay(), false) : null)
                                    @php($not_started = $starts && $starts->copy()->startOfDay()->gt(\Carbon\Carbon::now()->startOfDay()))
                                    @php($lifecycle = is_null($days_left) ? null : ($days_left < 0 ? 'ended' : ($not_started ? 'scheduled' : 'running')))
                                    @php($zone_count = $coupon->coupon_type == 'zone_wise' ? count(\App\CentralLogics\Helpers::decodeJsonToArray($coupon['data']) ?? []) : 0)
                                    <tr>
                                        <td>
                                            <span title="{{ $coupon['title'] }}" class="d-block text-title font-semibold">
                                                {{ Str::limit($coupon['title'], 22, '...') }}
                                            </span>
                                            <span class="d-block fs-12 text-muted">ID:{{ $coupon['id'] }}</span>
                                        </td>
                                        <td>
                                            <span class="cell-chips"><span class="cell-chip">{{ $coupon['code'] }}</span></span>
                                        </td>

                                        <td>
                                            <span class="d-block text-title">{{ $coupon_type_labels[$coupon->coupon_type] ?? $coupon->coupon_type }}</span>
                                            @if ($coupon->coupon_type == 'store_wise')
                                                <span class="d-block fs-12 text-muted" title="{{ $coupon->store?->name }}">
                                                    {{ $coupon->store ? Str::limit($coupon->store->name, 20, '...') : translate('messages.N/A') }}
                                                </span>
                                            @elseif ($zone_count)
                                                <span class="d-block fs-12 text-muted">{{ translate('messages.zones') }}: {{ $zone_count }}</span>
                                            @endif
                                        </td>
                                        <td class="col--numeric" data-order="{{ $coupon['discount'] }}">
                                            <span class="d-block text-title font-semibold">
                                                {{ $coupon['discount_type'] == 'amount' ? \App\CentralLogics\Helpers::format_currency($coupon['discount']) : $coupon['discount'] . '%' }}
                                            </span>
                                            @if ($coupon['discount_type'] == 'percent' && $coupon['max_discount'] > 0)
                                                <span class="d-block fs-12 text-muted">{{ translate('Max discount') }}: {{ \App\CentralLogics\Helpers::format_currency($coupon['max_discount']) }}</span>
                                            @endif
                                        </td>
                                        <td class="col--numeric" data-order="{{ $coupon['min_purchase'] }}">
                                            {{ \App\CentralLogics\Helpers::format_currency($coupon['min_purchase']) }}
                                        </td>
                                        <td class="col--numeric" data-order="{{ $coupon->total_uses }}">
                                            <span class="d-block text-title font-semibold">{{ $coupon->total_uses }}</span>
                                            @if ($coupon['limit'])
                                                <span class="d-block fs-12 text-muted">{{ translate('Usage limit per customer') }}: {{ $coupon['limit'] }}</span>
                                            @endif
                                        </td>
                                        <td data-order="{{ $coupon['expire_date'] }}">
                                            @if ($expires)
                                                <span class="table-when{{ $days_left < 0 ? ' table-when--stale' : '' }}">
                                                    <span class="table-when__day">{{ ($starts ? \App\CentralLogics\Helpers::date_format($starts) . ' - ' : '') . \App\CentralLogics\Helpers::date_format($expires) }}</span>
                                                    <span class="table-when__ago">
                                                        @if ($days_left > 1)
                                                            {{ translate('Expires') }} {{ $expires->copy()->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}
                                                        @elseif ($days_left === 1)
                                                            {{ translate('Expires tomorrow') }}
                                                        @elseif ($days_left === 0)
                                                            {{ translate('Expires today') }}
                                                        @elseif ($days_left === -1)
                                                            {{ translate('Expired yesterday') }}
                                                        @else
                                                            {{ translate('Expired') }} {{ $expires->copy()->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}
                                                        @endif
                                                    </span>
                                                </span>
                                            @else
                                                <span class="text-muted font-size-sm">{{ translate('messages.N/A') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="status-toggle" data-status="{{ $coupon->status ? 1 : 0 }}">
                                                <label class="toggle-switch toggle-switch-sm"
                                                    for="couponCheckbox{{ $coupon->id }}">
                                                    <input type="checkbox"
                                                        data-url="{{ route('admin.coupon.status', [$coupon['id'], $coupon->status ? 0 : 1]) }}"
                                                        class="toggle-switch-input redirect-url"
                                                        id="couponCheckbox{{ $coupon->id }}"
                                                        {{ $coupon->status ? 'checked' : '' }}>
                                                    <span class="toggle-switch-label">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                                </label>
                                                <span class="status-toggle__text" aria-live="polite">
                                                    {{ $coupon->status ? translate('messages.Active') : translate('messages.Inactive') }}
                                                </span>
                                            </div>
                                            @if ($lifecycle)
                                                <span class="cell-chips d-block mt-1">
                                                    <span class="cell-chip">{{ $lifecycle_labels[$lifecycle] }}</span>
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn--container justify-content-center">
                                                <a class="ml-2 btn btn-sm action-btn action-btn--view data-info-show"
                                                    href="#0" data-toggle="modal" data-target="#coupon_btn"
                                                    data-id="{{ $coupon['id'] }}"
                                                    data-url="{{ route('admin.coupon.viewCoupon', [$coupon['id']]) }}">
                                                    <i class="tio-visible-outlined"></i>
                                                </a>
                                                <a class="btn action-btn action-btn--edit"
                                                    href="{{ route('admin.coupon.update', [$coupon['id']]) }}"title="{{ translate('Edit coupon') }}"><i
                                                        class="tio-edit"></i>
                                                </a>
                                                <a class="btn action-btn action-btn--delete form-alert"
                                                    href="javascript:" data-id="coupon-{{ $coupon['id'] }}"
                                                    data-message="{{ translate('Want to delete this coupon?') }}"
                                                    title="{{ translate('messages.Delete coupon') }}"><i
                                                        class="tio-delete-outlined"></i>
                                                </a>
                                                <form action="{{ route('admin.coupon.delete', [$coupon['id']]) }}"
                                                    method="post" id="coupon-{{ $coupon['id'] }}">
                                                    @csrf @method('delete')
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (count($coupons) !== 0)
                        <hr>
                    @endif
                    <div class="page-area">
                        {!! $coupons->links() !!}
                    </div>
                    @if (count($coupons) === 0)
                        <div class="empty--data">
                            <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                            <h5>
                                {{ translate('No data found') }}
                            </h5>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal shedule-modal fade" id="coupon_btn" tabindex="-1" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content pb-1">
                <div class="d-flex align-items-center justify-content-between gap-2 py-3 px-3">
                    <p class="m-0 d-xl-block d-none"></p>
                    <div class="text-center">
                        <h3 class="title-clr mb-0">{{ translate('Coupon details') }}</h3>
                    </div>
                    <button type="button" class="close bg-light w-30px h-30 rounded-circle" data-dismiss="modal"
                        aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div id="data-view">

                </div>

            </div>
        </div>
    </div>
    <input type="hidden" id="min-purchase-toast"
        value="{{ translate('messages.Discount amount cannot be greater than minimum purchase amount') }}">

@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/js/view-pages/coupon-index.js"></script>
    <script>
        "use strict";

        $(document).on('ready', function() {

            let module_id = {{ Config::get('module.current_module_id') }};

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
                        var $request = $.ajax(params);

                        $request.then(success);
                        $request.fail(failure);

                        return $request;
                    }
                }
            });

            coupon_type_change($('#coupon_type').val());
        });

    </script>
@endpush

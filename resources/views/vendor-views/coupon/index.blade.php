@extends('layouts.vendor.app')

@section('title',translate('Add new coupon'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/coupon-form.css') }}">
@endpush

@php($coupon_type_labels = [
    'default' => translate('Default'),
    'free_delivery' => translate('Free delivery'),
    'first_order' => translate('messages.first_order'),
    'store_wise' => translate('messages.store_wise'),
])
@php($lifecycle_labels = [
    'running' => translate('messages.Running'),
    'scheduled' => translate('messages.Scheduled'),
    'ended' => translate('messages.Ended'),
])
@php($currency_symbol = \App\CentralLogics\Helpers::currency_symbol())
@php($currency_position = \App\CentralLogics\Helpers::get_business_settings('currency_symbol_position') ?? 'left')
@php($round_digit = (int) (config('round_up_to_digit') ?? 2))
@php($month_labels = array_map(fn ($month) => \Carbon\Carbon::create(2000, $month, 1)->locale(app()->getLocale())->translatedFormat('M'), range(1, 12)))
@php($free_delivery_available = $store_data->sub_self_delivery == 1 && !in_array($store_data->module?->module_type, ['service', 'rental']))
@php($selected_coupon_type = old('coupon_type', 'default'))
@php($selected_discount_type = old('discount_type', 'amount'))
@php($coupon_preview_lang = [
    'discount' => translate('Discount'),
    'free_delivery' => translate('Free delivery'),
    'max_discount' => translate('Maximum discount'),
    'total_days' => translate('Total days'),
    'one_use' => translate('One use per customer'),
    'placeholder_code' => translate('Your code'),
    'placeholder_title' => translate('messages.New coupon'),
    'empty' => translate('Not set yet'),
])

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title"><i class="tio-add-circle-outlined"></i> {{translate('messages.Add new coupon')}}</h1>
                    <p class="page-header-desc">{{ translate('A code customers type at checkout for money off your items.') }}</p>
                </div>
            </div>
        </div>

        <div class="tps cpn">
            <form action="{{route('vendor.coupon.store')}}" method="post" class="custom-validation" id="coupon_form">
                @csrf
                @unless ($free_delivery_available)
                    <input type="hidden" name="coupon_type" value="default">
                @endunless
                <div class="row g-3">
                    <div class="col-xl-8">
                        <div class="tps-card">
                            <div class="tps-card__body">
                                <div class="tps-group">
                                    <p class="tps-group__label">{{ translate('Coupon basics') }}</p>

                                    @if ($language)
                                        <ul class="nav nav-tabs mb-3 border-0">
                                            <li class="nav-item">
                                                <a class="nav-link lang_link active" href="#"
                                                    id="default-link">{{ translate('Default') }}</a>
                                            </li>
                                            @foreach ($language as $lang)
                                                <li class="nav-item">
                                                    <a class="nav-link lang_link" href="#"
                                                        id="{{ $lang }}-link">{{ $language_labels[$lang] }}</a>
                                                </li>
                                            @endforeach
                                        </ul>

                                        <div class="lang_form" id="default-form">
                                            <div class="tps-field">
                                                <div class="error-wrapper">
                                                    <label class="tps-field__label" for="default_title">
                                                        {{ translate('messages.Title') }} ({{ translate('Default') }})
                                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                            data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                    </label>
                                                    <input type="text" name="title[]" id="default_title" class="form-control"
                                                        value="{{ old('title.0') }}" maxlength="191"
                                                        placeholder="{{ translate('messages.Ex') }}: Weekend treat" required>
                                                </div>
                                                <small class="tps-field__hint">{{ translate('Customers see this name beside the code in their coupon list.') }}</small>
                                            </div>
                                        </div>
                                        <input type="hidden" name="lang[]" value="default">

                                        @foreach ($language as $key => $lang)
                                            <div class="d-none lang_form" id="{{ $lang }}-form">
                                                <div class="tps-field">
                                                    <label class="tps-field__label" for="{{ $lang }}_title">
                                                        {{ translate('messages.Title') }} ({{ strtoupper($lang) }})
                                                        <span class="tps-opt">{{ translate('Optional') }}</span>
                                                    </label>
                                                    <input type="text" name="title[]" id="{{ $lang }}_title" class="form-control"
                                                        value="{{ old('title.' . ($key + 1)) }}" maxlength="191"
                                                        placeholder="{{ translate('messages.New coupon') }}">
                                                    <small class="tps-field__hint">{{ translate('Leave it empty to fall back to the default title.') }}</small>
                                                </div>
                                            </div>
                                            <input type="hidden" name="lang[]" value="{{ $lang }}">
                                        @endforeach
                                    @endif

                                    <div class="row g-3 mt-0">
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <div class="error-wrapper">
                                                    <label class="tps-field__label" for="coupon_code">
                                                        {{ translate('messages.code') }}
                                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                            data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                    </label>
                                                    <div class="tps-input-wrap has-two-actions">
                                                        <input id="coupon_code" type="text" name="code" class="form-control"
                                                            value="{{ old('code') }}" maxlength="100"
                                                            placeholder="{{ translate('messages.Ex') }}: WEEKEND20" required>
                                                        <button type="button" class="tps-input-action copy-to-clipboard"
                                                            data-id="coupon_code"
                                                            data-copied="{{ translate('Code copied') }}"
                                                            data-copy-failed="{{ translate('The code could not be copied') }}"
                                                            title="{{ translate('Copy the code') }}"><i class="tio-copy"></i></button>
                                                        <button type="button" class="tps-input-action" id="generate_code"
                                                            data-url="{{ route('vendor.coupon.generate-check-code') }}"
                                                            data-success-message="{{ translate('messages.Coupon code generated successfully') }}"
                                                            title="{{ translate('Generate code') }}"><i class="tio-magic-wand"></i></button>
                                                    </div>
                                                </div>
                                                <small class="tps-field__hint">{{ translate('Customers type this exactly as written, so keep it short and easy to read.') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if ($free_delivery_available)
                                    <div class="tps-group">
                                        <p class="tps-group__label">{{ translate('Coupon type') }}</p>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="tps-choice">
                                                    <input type="radio" name="coupon_type" value="default"
                                                        {{ $selected_coupon_type === 'default' ? 'checked' : '' }}>
                                                    <span class="tps-choice__box">
                                                        <span class="tps-choice__mark"></span>
                                                        <span>
                                                            <span class="tps-choice__title">{{ translate('Discount on the order') }}</span>
                                                            <span class="tps-choice__desc">{{ translate('Takes a flat sum or a share off what the customer is buying.') }}</span>
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="tps-choice">
                                                    <input type="radio" name="coupon_type" value="free_delivery"
                                                        {{ $selected_coupon_type === 'free_delivery' ? 'checked' : '' }}>
                                                    <span class="tps-choice__box">
                                                        <span class="tps-choice__mark"></span>
                                                        <span>
                                                            <span class="tps-choice__title">{{ translate('Free delivery') }}</span>
                                                            <span class="tps-choice__desc">{{ translate('Waives your delivery charge instead of discounting the items.') }}</span>
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="tps-group" id="discount_group">
                                    <p class="tps-group__label">{{ translate('Discount') }}</p>
                                    <div class="row g-3">
                                        <div class="col-md-4" id="discount_type_div">
                                            <div class="tps-field">
                                                <div class="error-wrapper">
                                                    <label class="tps-field__label" for="discount_type">
                                                        {{ translate('Discount type') }}
                                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                            data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                    </label>
                                                    <select name="discount_type" class="custom-select" id="discount_type">
                                                        <option value="amount" {{ $selected_discount_type === 'amount' ? 'selected' : '' }}>
                                                            {{ translate('Amount') }} ({{ $currency_symbol }})
                                                        </option>
                                                        <option value="percent" {{ $selected_discount_type === 'percent' ? 'selected' : '' }}>
                                                            {{ translate('Percent') }} (%)
                                                        </option>
                                                    </select>
                                                </div>
                                                <small class="tps-field__hint">{{ translate('An amount takes a fixed sum off. A percent takes a share of the order, capped below.') }}</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4" id="discount_div">
                                            <div class="tps-field">
                                                <div class="error-wrapper">
                                                    <label class="tps-field__label" for="discount">
                                                        {{ translate('Discount') }}
                                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                            data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                    </label>
                                                    <div class="cpn-affix">
                                                        <input type="number" step="0.01" min="1" max="999999999999.99"
                                                            name="discount" id="discount" class="form-control"
                                                            value="{{ old('discount') }}" placeholder="{{ translate('messages.Ex') }}: 10" required>
                                                        <span class="cpn-affix__unit" id="discount_unit">{{ $selected_discount_type === 'percent' ? '%' : $currency_symbol }}</span>
                                                    </div>
                                                </div>
                                                <small class="tps-field__hint">{{ translate('How much comes off once the coupon is applied.') }}</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4" id="max_discount_div">
                                            <div class="tps-field">
                                                <div class="error-wrapper">
                                                    <label class="tps-field__label" for="max_discount">
                                                        {{ translate('messages.Max discount') }}
                                                        <span id="max_discount_astaric" class="tps-req" data-toggle="tooltip"
                                                            data-placement="right"
                                                            data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                    </label>
                                                    <div class="cpn-affix">
                                                        <input type="number" step="0.01" min="0" max="999999999999.99"
                                                            name="max_discount" id="max_discount" class="form-control"
                                                            value="{{ old('max_discount', 0) }}" placeholder="{{ translate('messages.Ex') }}: 50">
                                                        <span class="cpn-affix__unit">{{ $currency_symbol }}</span>
                                                    </div>
                                                </div>
                                                <small class="tps-field__hint">{{ translate('The most a percentage discount can take off a single order.') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tps-group">
                                    <p class="tps-group__label">{{ translate('Conditions and validity') }}</p>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <div class="error-wrapper">
                                                    <label class="tps-field__label" for="min_purchase">
                                                        {{ translate('messages.Min purchase') }}
                                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                            data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                    </label>
                                                    <div class="cpn-affix">
                                                        <input id="min_purchase" type="number" step="0.01" name="min_purchase"
                                                            min="1" max="999999999999.99" class="form-control"
                                                            value="{{ old('min_purchase') }}" placeholder="{{ translate('messages.Ex') }}: 100" required>
                                                        <span class="cpn-affix__unit">{{ $currency_symbol }}</span>
                                                    </div>
                                                </div>
                                                <small class="tps-field__hint">{{ translate('The order has to reach this much before the coupon can be applied.') }}</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <div class="error-wrapper">
                                                    <label class="tps-field__label" for="coupon_limit">
                                                        {{ translate('Limit for same user') }}
                                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                            data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                    </label>
                                                    <input type="number" name="limit" id="coupon_limit" class="form-control"
                                                        value="{{ old('limit') }}" min="1" max="100"
                                                        placeholder="{{ translate('messages.Ex') }}: 10" required>
                                                </div>
                                                <small class="tps-field__hint">{{ translate('How many times one customer can use this coupon.') }}</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <div class="error-wrapper">
                                                    <label class="tps-field__label" for="date_from">
                                                        {{ translate('Start date') }}
                                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                            data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                    </label>
                                                    <input type="date" name="start_date" class="form-control" id="date_from"
                                                        value="{{ old('start_date') }}" required>
                                                </div>
                                                <small class="tps-field__hint">{{ translate('The first day customers can use the code.') }}</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <div class="error-wrapper">
                                                    <label class="tps-field__label" for="date_to">
                                                        {{ translate('messages.Expire date') }}
                                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                            data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                    </label>
                                                    <input type="date" name="expire_date" class="form-control" id="date_to"
                                                        value="{{ old('expire_date') }}" required>
                                                </div>
                                                <small class="tps-field__hint">{{ translate('The last day it works. It stops on its own after this.') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tps-card__foot">
                                <span class="tps-foot-note">{{ translate('A coupon code can only be used once across the whole marketplace.') }}</span>
                                <button type="reset" id="reset_btn" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                                <button type="submit" class="btn btn--primary"><i class="tio-add-circle"></i> {{ translate('messages.Submit') }}</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="cpn-aside">
                            <div class="tps-card">
                                <div class="tps-card__head">
                                    <span class="tps-card__brand"><i class="tio-ticket"></i></span>
                                    <div class="tps-card__titles">
                                        <h2 class="tps-card__title">{{ translate('Coupon preview') }}</h2>
                                        <p class="tps-card__subtitle">{{ translate('How this coupon reads to a customer at checkout.') }}</p>
                                    </div>
                                </div>
                                <div class="tps-card__body">
                                    <div class="cpn-ticket">
                                        <div class="cpn-ticket__top">
                                            <span class="cpn-ticket__label">{{ translate('messages.code') }}</span>
                                            <span class="cpn-ticket__code is-empty" id="preview_code">{{ translate('Your code') }}</span>
                                        </div>
                                        <div class="cpn-ticket__rule"></div>
                                        <div class="cpn-ticket__bottom">
                                            <span class="cpn-ticket__offer" id="preview_offer">{{ translate('Not set yet') }}</span>
                                            <span class="cpn-ticket__cap" id="preview_cap"></span>
                                            <span class="cpn-ticket__title" id="preview_title">{{ translate('messages.New coupon') }}</span>
                                        </div>
                                    </div>
                                    <ul class="cpn-sum">
                                        <li class="cpn-sum__row">
                                            <span class="cpn-sum__label"><i class="tio-shopping-cart-outlined"></i> {{ translate('messages.Min purchase') }}</span>
                                            <span class="cpn-sum__value" id="preview_min">{{ translate('Not set yet') }}</span>
                                        </li>
                                        <li class="cpn-sum__row">
                                            <span class="cpn-sum__label"><i class="tio-calendar"></i> {{ translate('messages.Validity') }}</span>
                                            <span class="cpn-sum__value">
                                                <span id="preview_dates">{{ translate('Not set yet') }}</span>
                                                <small class="cpn-sum__note" id="preview_duration"></small>
                                            </span>
                                        </li>
                                        <li class="cpn-sum__row">
                                            <span class="cpn-sum__label"><i class="tio-user-big-outlined"></i> {{ translate('Limit for same user') }}</span>
                                            <span class="cpn-sum__value" id="preview_limit">{{ translate('Not set yet') }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="tps-note tps-note--info mt-3">
                                <i class="tio-info-outined"></i>
                                <p>{{ translate('A new coupon is switched on the moment you add it. Turn it off from the list below whenever you need to.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="card mt-3">
            <div class="card-header py-2">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'title'    => translate('Coupon list'),
                        'subtitle' => translate('messages.Discount coupons your customers can apply at checkout.'),
                        'count'    => $coupons->total(),
                        'count_id' => 'itemCount',
                    ])
                    <form method="get">

                        <div class="input--group input-group input-group-merge input-group-flush">
                            <input id="datatableSearch" type="search" value="{{request()?->search ?? ''}}" name="search" class="form-control" placeholder="{{ translate('messages.Ex') . ' : ' . translate('messages.Search by title or code') }}" aria-label="{{translate('Search')}}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="table-responsive datatable-custom" id="table-div">
                <table id="columnSearchDatatable"
                        class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table table--wrap-head"
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
                        <th>{{translate('messages.Title')}}</th>
                        <th>{{translate('messages.code')}}</th>
                        <th>{{translate('Type')}}</th>
                        <th class="col--numeric">{{translate('Discount')}}</th>
                        <th class="col--numeric">{{translate('messages.Min purchase')}}</th>
                        <th class="col--numeric">{{translate('messages.Total uses')}}</th>
                        <th>{{translate('messages.Validity')}}</th>
                        <th>{{translate('messages.Status')}}</th>
                        <th class="text-center">{{translate('messages.Action')}}</th>
                    </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($coupons as $coupon)
                        @php($expires = $coupon['expire_date'] ? \Carbon\Carbon::parse($coupon['expire_date']) : null)
                        @php($starts = $coupon['start_date'] ? \Carbon\Carbon::parse($coupon['start_date']) : null)
                        @php($days_left = $expires ? (int) \Carbon\Carbon::now()->startOfDay()->diffInDays($expires->copy()->startOfDay(), false) : null)
                        @php($not_started = $starts && $starts->copy()->startOfDay()->gt(\Carbon\Carbon::now()->startOfDay()))
                        @php($lifecycle = is_null($days_left) ? null : ($days_left < 0 ? 'ended' : ($not_started ? 'scheduled' : 'running')))
                        <tr>
                            <td>
                                <span class="d-block text-title font-semibold" title="{{ $coupon['title'] }}">
                                    {{Str::limit($coupon['title'],22,'...')}}
                                </span>
                                <span class="d-block fs-12 text-muted">ID:{{$coupon['id']}}</span>
                            </td>
                            <td>
                                <span class="cell-chips"><span class="cell-chip">{{$coupon['code']}}</span></span>
                            </td>
                            <td>{{ $coupon_type_labels[$coupon->coupon_type] ?? $coupon->coupon_type }}</td>
                            <td class="col--numeric" data-order="{{ $coupon['discount'] }}">
                                <span class="d-block text-title font-semibold">
                                    {{ $coupon['discount_type'] == 'amount' ? \App\CentralLogics\Helpers::format_currency($coupon['discount']) : $coupon['discount'].'%' }}
                                </span>
                                @if ($coupon['discount_type'] == 'percent' && $coupon['max_discount'] > 0)
                                    <span class="d-block fs-12 text-muted">{{ translate('Maximum discount') }}: {{ \App\CentralLogics\Helpers::format_currency($coupon['max_discount']) }}</span>
                                @endif
                            </td>
                            <td class="col--numeric" data-order="{{ $coupon['min_purchase'] }}">
                                {{\App\CentralLogics\Helpers::format_currency($coupon['min_purchase'])}}
                            </td>
                            <td class="col--numeric" data-order="{{ $coupon->total_uses }}">
                                <span class="d-block text-title font-semibold">{{$coupon->total_uses}}</span>
                                @if ($coupon['limit'])
                                    <span class="d-block fs-12 text-muted">{{ translate('Limit for same user') }}: {{ $coupon['limit'] }}</span>
                                @endif
                            </td>
                            <td data-order="{{ $coupon['expire_date'] }}">
                                @if ($expires)
                                    <span class="table-when{{ $days_left < 0 ? ' table-when--stale' : '' }}">
                                        <span class="table-when__day">{{ ($starts ? \App\CentralLogics\Helpers::date_format($starts).' - ' : '').\App\CentralLogics\Helpers::date_format($expires) }}</span>
                                        <span class="table-when__ago">
                                            @if ($days_left > 1)
                                                {{ translate('Expires') }} {{ $expires->copy()->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), ['syntax' => \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW]) }}
                                            @elseif ($days_left === 1)
                                                {{ translate('Expires tomorrow') }}
                                            @elseif ($days_left === 0)
                                                {{ translate('Expires today') }}
                                            @elseif ($days_left === -1)
                                                {{ translate('Expired yesterday') }}
                                            @else
                                                {{ translate('Expired') }} {{ $expires->copy()->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), ['syntax' => \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW]) }}
                                            @endif
                                        </span>
                                    </span>
                                @else
                                    <span class="text-muted font-size-sm">{{ translate('messages.N/A') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="status-toggle" data-status="{{$coupon->status?1:0}}">
                                    <label class="toggle-switch toggle-switch-sm" for="couponCheckbox{{$coupon->id}}">
                                        <input type="checkbox"
                                               data-url="{{route('vendor.coupon.status',[$coupon['id'],$coupon->status?0:1])}}"
                                              class="toggle-switch-input redirect-url" id="couponCheckbox{{$coupon->id}}" {{$coupon->status?'checked':''}}>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                    <span class="status-toggle__text" aria-live="polite">
                                        {{$coupon->status ? translate('messages.Active') : translate('messages.Inactive')}}
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
                                                    data-url="{{ route('vendor.coupon.viewCoupon', [$coupon['id']]) }}">
                                                    <i class="tio-visible-outlined"></i>
                                                </a>
                                    <a class="btn btn-sm action-btn action-btn--edit" href="{{route('vendor.coupon.update',[$coupon['id']])}}" title="{{translate('Edit coupon')}}"><i class="tio-edit"></i>
                                    </a>
                                    <a class="btn btn-sm action-btn action-btn--delete form-alert"
                                       data-id="coupon-{{$coupon['id']}}"
                                       data-message="{{ translate('Want to delete this coupon?') }}"
                                       href="javascript:" title="{{translate('messages.Delete coupon')}}"><i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{route('vendor.coupon.delete',[$coupon['id']])}}"
                                    method="post" id="coupon-{{$coupon['id']}}">
                                    @csrf @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if(count($coupons) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
                @endif
            </div>
            <div class="page-area px-4 pb-3">
                <div class="d-flex align-items-center justify-content-end">
                    <div>
                        {!! $coupons->links() !!}
                    </div>
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
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/view-pages/vendor-coupon.js') }}"></script>
    <script>
        "use strict";

        const couponLang = @json($coupon_preview_lang);
        const couponMonths = @json($month_labels);
        const couponCurrency = {
            symbol: @json($currency_symbol),
            position: @json($currency_position),
            decimals: {{ $round_digit }}
        };

        function couponMoney(value) {
            const amount = Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: couponCurrency.decimals,
                maximumFractionDigits: couponCurrency.decimals
            });

            return couponCurrency.position === 'right'
                ? amount + ' ' + couponCurrency.symbol
                : couponCurrency.symbol + ' ' + amount;
        }

        function couponDate(value) {
            const parts = String(value || '').split('-');

            if (parts.length !== 3) {
                return '';
            }

            return parts[2] + ' ' + (couponMonths[Number(parts[1]) - 1] || parts[1]) + ' ' + parts[0];
        }

        function couponPreview() {
            const code = ($('#coupon_code').val() || '').trim();
            const title = ($('#default_title').val() || '').trim();
            const type = $('input[name="coupon_type"]:checked').val() || 'default';
            const discountType = $('#discount_type').val();
            const discount = Number($('#discount').val()) || 0;
            const maxDiscount = Number($('#max_discount').val()) || 0;
            const minPurchase = Number($('#min_purchase').val()) || 0;
            const limit = Number($('#coupon_limit').val()) || 0;
            const from = $('#date_from').val();
            const to = $('#date_to').val();

            $('#preview_code').text(code || couponLang.placeholder_code).toggleClass('is-empty', code === '');
            $('#preview_title').text(title || couponLang.placeholder_title);

            let offer = couponLang.empty;
            let cap = '';

            if (type === 'free_delivery') {
                offer = couponLang.free_delivery;
            } else if (discount > 0) {
                offer = couponLang.discount + ': ' + (discountType === 'percent'
                    ? discount + '%'
                    : couponMoney(discount));

                if (discountType === 'percent' && maxDiscount > 0) {
                    cap = couponLang.max_discount + ': ' + couponMoney(maxDiscount);
                }
            }

            $('#preview_offer').text(offer);
            $('#preview_cap').text(cap);
            $('#preview_min').text(minPurchase > 0 ? couponMoney(minPurchase) : couponLang.empty);

            const start = couponDate(from);
            const end = couponDate(to);
            $('#preview_dates').text(start && end && start !== end
                ? start + ' – ' + end
                : (start || end || couponLang.empty));

            let duration = '';

            if (from && to) {
                const days = Math.round((Date.parse(to) - Date.parse(from)) / 86400000) + 1;

                if (days >= 1) {
                    duration = couponLang.total_days + ': ' + days;
                }
            }

            $('#preview_duration').text(duration);

            $('#preview_limit').text(limit === 1
                ? couponLang.one_use
                : (limit > 1 ? String(limit) : couponLang.empty));

            $('#discount_unit').text(discountType === 'percent' ? '%' : couponCurrency.symbol);
        }

        $(function () {
            $('#coupon_form').on('input change', 'input, select', couponPreview);

            $('#generate_code').on('click', function () {
                const button = $(this);

                $.get({
                    url: button.data('url'),
                    data: {
                        title: $('#default_title').val()
                    },
                    success: function (code) {
                        $('#coupon_code').val(code).trigger('change');
                        toastr.success(button.data('success-message'));
                    }
                });
            });

            $('#coupon_form').on('reset', function () {
                setTimeout(function () {
                    $('input[name="coupon_type"]:checked').trigger('change');
                    couponPreview();
                }, 0);
            });

            couponPreview();
        });
    </script>
@endpush

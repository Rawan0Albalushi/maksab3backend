@extends('layouts.admin.app')

@section('title', translate('Business setup'))

@section('content')
<div class="content">
    <form class="validate-form" action="{{ route('admin.business-settings.update-setup') }}" method="post" enctype="multipart/form-data">
            @csrf
        <div class="container-fluid">
            <div class="page-header">
                <h1 class="page-header-title mr-3">
                    <span class="page-header-icon">
                        <img src="{{ asset('public/assets/admin/img/outline/business.svg') }}" class="w--26" alt="">
                    </span>
                    <span>
                        {{ translate('Business settings') }}
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('Your business name, logo, address, currency and the basics every screen is built on.') }}</p>
                @include('admin-views.business-settings.partials.nav-menu')
            </div>

            <div class="card mb-3" id="maintenance_mode_section">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                            <div>
                                <h3 class="mb-1">
                                    {{ translate('Maintenance mode') }}
                                </h3>
                                <p class="mb-0 fs-12">
                                    {{ translate('Turn on the maintenance mode will temporarily deactivate your selected systems as of your chosen date and time.') }}
                                </p>
                            </div>
                        </div>
                        <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                            <div
                                class="maintenance-mode-toggle-bar d-flex flex-wrap justify-content-between border rounded align-items-center py-2 px-3">
                                @php($config = \App\CentralLogics\Helpers::get_business_settings('maintenance_mode'))
                                <?php
                                $maintenance_mode_data = \App\Models\DataSetting::where('type', 'maintenance_mode')
                                    ->whereIn('key', ['maintenance_system_setup', 'maintenance_duration_setup', 'maintenance_message_setup'])
                                    ->pluck('value', 'key')
                                    ->map(fn($v) => json_decode($v, true))
                                    ->toArray();
                                $selectedMaintenanceSystem   = data_get($maintenance_mode_data, 'maintenance_system_setup', []);
                                $selectedMaintenanceDuration = data_get($maintenance_mode_data, 'maintenance_duration_setup', []);
                                $selectedMaintenanceMessage  = data_get($maintenance_mode_data, 'maintenance_message_setup', []);
                                ?>
                                <h5 class="text-capitalize m-0 font-weight-normal fs-14 text-dark">
                                    {{ translate('Maintenance mode') }}
                                </h5>
                                <label class="toggle-switch toggle-switch-sm">
                                    <input type="checkbox"
                                        class="toggle-switch-input maintenance-mode-toggle"
                                        id="maintenance_mode"
                                        {{ isset($config) && $config ? 'checked' : '' }}>
                                    <span class="toggle-switch-label text mb-0">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-lg-12">
                    <div class="card" id="basic_information_section">
                        <div class="card-header">
                            <div>
                                <h3 class="mb-1">
                                    {{ translate('Basic information') }}
                                </h3>
                                <p class="mb-0 fs-12">
                                    {{ translate('Here you setup your all business information.') }}
                                </p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-lg-8 shadow-sm">
                                    <div class="p-xxl-20 p-xl-3 p-2 bg-white">
                                        <div class="row g-3">
                                            <div class="col-sm-6 col-md-6">
                                                <div class="form-group mb-0">
                                                    <label class="form-label"
                                                        for="business_name">{{ translate('Business name') }} <span
                                                            class="text-danger">*</span></label>
                                                    <input id="business_name" type="text" name="business_name"
                                                        value="{{ \App\CentralLogics\Helpers::get_business_settings('business_name', false) ?? '' }}" class="form-control"
                                                        placeholder="{{ translate('Type your business name') }}" required>
                                                </div>
                                            </div>
                                            <div class="col-sm-6 col-md-6">
                                                @php($email_address = \App\Models\BusinessSetting::where('key', 'email_address')->first())
                                                <div class="form-group mb-0">
                                                    <label class="form-label" for="email_address">{{ translate('email') }}
                                                        <span class="text-danger">*</span></label>
                                                    <input id="email_address" type="email" value="{{ $email_address->value ?? '' }}"
                                                        name="email_address" class="form-control"
                                                        placeholder="{{ translate('Type your email') }}"
                                                        required>
                                                </div>
                                            </div>
                                            <div class="col-sm-6 col-md-6">
                                                @php($phone = \App\Models\BusinessSetting::where('key', 'phone')->first())
                                                <div class="form-group mb-0">
                                                    <label class="form-label" for="phone">{{ translate('Phone') }}
                                                    </label>
                                                    <input type="tel" value="{{ $phone->value ?? '' }}" id="phone"
                                                        name="phone" class="form-control"
                                                        placeholder="{{ translate('Ex') . ': +3264124565' }}" required>
                                                </div>
                                            </div>
                                            <div class="col-sm-6 col-md-6">
                                                @php($country = \App\Models\BusinessSetting::where('key', 'country')->first())
                                                <div class="form-group mb-0">
                                                    <label class="form-label text-capitalize"
                                                        for="country">{{ translate('Country') }} <span
                                                            class="text-danger">*</span></label>
                                                    <select id="country" name="country"
                                                        class="form-control  js-select2-custom">
                                                        @foreach (\App\CentralLogics\Helpers::getCountries() as $countryCode => $countryName)
                                                            <option value="{{ $countryCode }}"
                                                                {{ $countryCode == $country->value ? 'selected' : '' }}>
                                                                {{ $countryName }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-sm-12 col-md-12">
                                                @php($address = \App\Models\BusinessSetting::where('key', 'address')->first())
                                                <div class="form-group mb-0">
                                                    <label class="form-label"
                                                        for="address">{{ translate('Address') }} <span
                                                            class="text-danger">*</span>
                                                        <span class="" data-toggle="tooltip" data-placement="right"
                                                            data-original-title="{{ translate('The physical location of your business') }}">
                                                            <i class="tio-info text-muted"></i>
                                                        </span>
                                                    </label>
                                                    <textarea type="text" id="address" name="address" class="form-control"
                                                        placeholder="{{ translate('Ex') . ': ' . translate('Address') }}" rows="1"
                                                        required>{{ $address->value ?? '' }}</textarea>
                                                </div>
                                            </div>
                                            <div class="col-md-12 mt-1">
                                                <div class="">
                                                    <div class="position-relative">
                                                        <input id="pac-input" class="controls rounded" data-toggle="tooltip"
                                                            data-placement="right"
                                                            data-original-title="{{ translate('Search your location') }}"
                                                            type="text"
                                                            placeholder="{{ translate('Search') }}" />
                                                        <div id="location_map_canvas"
                                                            class="overflow-hidden rounded height-285px"></div>

                                                        <div
                                                            class="lat-long-adjust py-1 px-1 position-absolute bottom-0 mb-2 flex-sm-nowrap flex-wrap rounded bg-white d-flex justify-content-center align-items-center gap-1">
                                                            @php($default_location = \App\Models\BusinessSetting::where('key', 'default_location')->first())
                                                            @php($default_location = $default_location?->value ? json_decode($default_location->value, true) : 0)
                                                            <div class="form-group mb-0">
                                                                <input type="text" id="latitude" name="latitude"
                                                                    class="w-auto border-0 p-0 m-0 text-center"
                                                                    placeholder="{{ translate('Ex') }}: -94.22213"
                                                                    value="{{ $default_location ? $default_location['lat'] : 0 }}"
                                                                    required readonly>
                                                            </div>
                                                            <div class="line"></div>
                                                            <div class="form-group mb-0">
                                                                <input type="text" name="longitude"
                                                                    class="w-auto border-0 p-0 m-0 text-center"
                                                                    placeholder="{{ translate('Ex') }}: 103.344322"
                                                                    id="longitude"
                                                                    value="{{ $default_location ? $default_location['lng'] : 0 }}"
                                                                    required readonly>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @php($logo = \App\Models\BusinessSetting::where('key', 'logo')->first())

                                <div class="col-lg-4 shadow-sm">
                                    <div class="d-flex flex-column gap-4 shadow-sm h--37px">
                                        <div class="bg-light2 rounded p-20">
                                            <div class="mb-15">
                                                <h4 class="mb-1">{{ translate('Upload logo') }} <span class="text-danger">*</span> </h4>
                                                <p class="mb-0 fs-12 gray-dark">
                                                    {{translate('Upload your business logo')}}
                                                </p>
                                            </div>
                                            @include('admin-views.partials._image-uploader', [
                                                    'id' => 'image-input',
                                                    'name' => 'logo',
                                                    'ratio' => '3:1',
                                                    'isRequired' => true,
                                                    'existingImage' => \App\CentralLogics\Helpers::get_full_url('business', $logo?->value ?? '', $logo?->storage[0]?->value ?? 'public', 'upload_image'),
                                                    'imageExtension' => IMAGE_EXTENSION,
                                                    'imageFormat' => IMAGE_FORMAT,
                                                    'maxSize' => MAX_FILE_SIZE,
                                                    'textPosition' => 'bottom',
                                                    ])
                                        </div>
                                        @php($icon = \App\Models\BusinessSetting::where('key', 'icon')->first())

                                        <div class="bg-light2 rounded p-20">
                                            <div class="text-start">
                                                <div class="mb-15">
                                                    <h4 class="mb-1">{{ translate('Favicon') }} <span class="text-danger">*</span> </h4>
                                                    <p class="mb-0 fs-12 gray-dark">
                                                        {{translate('Upload your website favicon')}}
                                                    </p>
                                                </div>
                                                @include('admin-views.partials._image-uploader', [
                                                    'id' => 'image-input',
                                                    'name' => 'icon',
                                                    'ratio' => '1:1',
                                                    'isRequired' => true,
                                                    'existingImage' => \App\CentralLogics\Helpers::get_full_url('business', $icon?->value ?? '', $icon?->storage[0]?->value ?? 'public', 'upload_image'),
                                                    'imageExtension' => IMAGE_EXTENSION,
                                                    'imageFormat' => IMAGE_FORMAT,
                                                    'maxSize' => MAX_FILE_SIZE,
                                                    'textPosition' => 'bottom',
                                                    ])
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="info-notes-bg px-3 py-2 rounded fz-11  gap-2 align-items-center d-flex mt-20">
                                <img src="{{asset('public/assets/admin/img/info-idea.svg')}}" alt="">
                                <span>
                                    {{ translate('For the address setup you can simply drag the map to pick the perfect value') }}: <strong class="text-title">{{ translate('Latitude & longitude') }}</strong>.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-12">

                    <div class="card " id="general_settings_section">
                        <div class="card-header">
                            <div>
                                <h3 class="mb-1">
                                    {{ translate('General setup') }}
                                </h3>
                                <p class="mb-0 fs-12">
                                    {{ translate('Here you can manage time settings to match with your business criteria') }}
                                </p>
                            </div>
                        </div>
                        <div class="card-body py-xxl-4 py-3 px-xxl-4 px-lg-3 px-0">
                            <div class="shadow-sm p-xxl-20 p-xl-3 p-2 bg-white mb-20">
                                <div class="mb-20">
                                    <h4 class="mb-1">
                                        {{ translate('Time setup') }}
                                    </h4>
                                    <p class="mb-0 fs-12">
                                        {{ translate('Setup your business time zone and format from here') }}
                                    </p>
                                </div>
                                <div class="bg-light2 rounded p-xxl-20 p-3">
                                    <div class="row g-3">
                                        <div class="col-sm-6 col-md-4 col-xl-4">
                                            @php($tz = \App\Models\BusinessSetting::where('key', 'timezone')->first())
                                            @php($settings_timezone = $tz ? $tz->value : 0)
                                            <div class="form-group mb-0">
                                                <label class="input-label d-flex align-items-center gap-1">
                                                    {{ translate('Time zone') }}
                                                    <span class="text-danger">*</span>

                                                        <span class="" data-toggle="tooltip" data-placement="right"
                                                            data-original-title="{{ translate('Time zone impact for this system') }}">
                                                            <i class="tio-info text-muted"></i>
                                                        </span>


                                                </label>
                                                <select name="timezone" class="form-control js-select2-custom">
                                                    @foreach(timezone_identifiers_list() as $tz)
                                                        <?php
                                                            $dt = new DateTime('now', new DateTimeZone($tz));
                                                        $offset = $dt->getOffset();
                                                        $hours = intdiv($offset, 3600);
                                                        $minutes = abs(($offset % 3600) / 60);
                                                        $sign = $hours >= 0 ? '+' : '-';
                                                        $gmt = sprintf('GMT%s%02d:%02d', $sign, abs($hours), $minutes);
                                                        ?>
                                                        <option value="{{ $tz }}" {{ isset($settings_timezone) && $settings_timezone == $tz ? 'selected' : '' }}>
                                                            ({{ $gmt }}) {{ $tz }}
                                                        </option>
                                                    @endforeach
                                                <option value="US/Central" {{ isset($settings_timezone) && $settings_timezone == 'US/Central' ? 'selected' :  '' }}> (GMT-06:00) Central Time (US & Canada)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-4 col-xl-4">
                                            @php($tf = \App\CentralLogics\Helpers::get_business_settings('timeformat', false) ?? '24')
                                            <div class="form-group mb-0">
                                                <label for="timeformat"
                                                    class="form-label text-capitalize">{{ translate('Time format') }}
                                                    <span class="text-danger">*</span></label>
                                                <div class="resturant-type-group bg-white border">
                                                    <label class="form-check form--check mr-2 mr-md-4">
                                                        <input class="form-check-input" type="radio" value="12"
                                                            name="timeformat" {{ $tf == '12' ? 'checked' : '' }}>
                                                        <span class="form-check-label">
                                                            12 {{ translate('hours') }}
                                                        </span>
                                                    </label>
                                                    <label class="form-check form--check mr-2 mr-md-4">
                                                        <input class="form-check-input" type="radio" value="24"
                                                            name="timeformat" {{ $tf == '24' ? 'checked' : '' }}>
                                                        <span class="form-check-label">
                                                            24 {{ translate('hours') }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @include('admin-views.business-settings.settings.partials._measurement-units')

                            <div class="shadow-sm p-xxl-20 p-xl-3 p-2 bg-white mb-20" id="currency-setup">

                                <div class="mb-20">
                                    <h4 class="mb-1">
                                        {{ translate('Currency setup') }}
                                    </h4>
                                    <p class="mb-0 fs-12">
                                        {{ translate('Here you can manage currency settings to match with your business criteria') }}
                                    </p>
                                </div>
                                <div class="bg-light2 rounded p-xxl-20 p-3">
                                    <div class="row g-3">
                                        <div class="col-sm-6 col-md-4 col-xl-4">
                                            @php($currency_code = \App\Models\BusinessSetting::where('key', 'currency')->first())
                                            <div class="form-group mb-0">
                                                <label class="form-label"
                                                    for="currency">{{ translate('Currency symbol') }}</label>
                                                <select id="change_currency" name="currency"
                                                    class="form-control js-select2-custom">
                                                    @foreach (\App\CentralLogics\Helpers::cached_list(\App\Models\Currency::class, orderBy: 'currency_code') as $currency)
                                                        <option value="{{ $currency['currency_code'] }}" {{ $currency_code ? ($currency_code->value == $currency['currency_code'] ? 'selected' : '') : '' }}>
                                                            {{ $currency['currency_code'] }}
                                                            ({{ $currency['currency_symbol'] }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-4 col-xl-4">
                                            @php($currency_symbol_position = \App\Models\BusinessSetting::where('key', 'currency_symbol_position')->first())
                                            <div class="form-group mb-0">
                                                <label class="form-label text-capitalize"
                                                    for="currency_symbol_position">{{ translate('Currency position') }}
                                                </label>
                                                <div class="resturant-type-group bg-white border">
                                                    <label class="form-check form--check mr-2 mr-md-4">
                                                        <input class="form-check-input" type="radio" value="left"
                                                            name="currency_symbol_position" {{ $currency_symbol_position ? ($currency_symbol_position->value == 'left' ? 'checked' : '') : '' }}>
                                                        <span class="form-check-label">
                                                            ({{\App\CentralLogics\Helpers::currency_symbol()}})
                                                            {{translate('Left')}}
                                                        </span>
                                                    </label>
                                                    <label class="form-check form--check mr-2 mr-md-4">
                                                        <input class="form-check-input" type="radio" value="right"
                                                            name="currency_symbol_position" {{ $currency_symbol_position ? ($currency_symbol_position->value == 'right' ? 'checked' : '') : '' }}>
                                                        <span class="form-check-label">
                                                            ({{\App\CentralLogics\Helpers::currency_symbol()}})
                                                            {{translate('Right')}}
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-4 col-xl-4">
                                            @php($digit_after_decimal_point = \App\Models\BusinessSetting::where('key', 'digit_after_decimal_point')->first())
                                            <div class="form-group mb-0">
                                                <label class="form-label text-capitalize"
                                                    for="digit_after_decimal_point">{{ translate('Digit after decimal point') }}
                                                </label>
                                                <span class="form-label-secondary" data-toggle="tooltip"
                                                        data-placement="right"
                                                        data-original-title="{{ translate('How many fractional digit to show after decimal value') }}">
                                                        <i class="tio-info text-muted"></i>
                                                </span>
                                                <input type="number" name="digit_after_decimal_point" class="form-control"
                                                    id="digit_after_decimal_point"
                                                    placeholder="{{ translate('Ex') }}: 2"
                                                    value="{{ $digit_after_decimal_point ? $digit_after_decimal_point->value : 0 }}"
                                                    min="0" max="4" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            @php($subscription_business_model = \App\CentralLogics\Helpers::get_business_settings('subscription_business_model', false) ?? 0)

                            @php($commission_business_model = \App\CentralLogics\Helpers::get_business_settings('commission_business_model', false) ?? 0)
                            <div class="shadow-sm p-xxl-20 p-xl-3 p-2 bg-white mb-20" id="business_model_section">
                                <div class="mb-20">
                                    <h4 class="mb-1">
                                        {{ translate('Business model setup') }}
                                    </h4>
                                    <p class="mb-0 fs-12">
                                        {{ translate('Setup your business model from here') }}
                                    </p>
                                </div>
                                <div class="bg-light2 rounded p-xxl-20 p-3">
                                    <div class="row g-3">
                                        <div class="col-lg-12">
                                            <label class="form-label" for="footer_text">{{translate('Business model')}}
                                                <span class="text-danger">*</span>
                                                <span class="form-label-secondary" data-toggle="tooltip"
                                                    data-placement="right" data-original-title="{{ translate('Choose the model that decides how you earn money and process orders.') }}">
                                                    <i class="tio-info text-muted"></i>
                                                </span>
                                            </label>
                                            <div class="bg-white rounded p-3 border mb-20">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input"
                                                                    id="subs" name="subscription_business_model" {{ $subscription_business_model ? 'checked' : '' }} value="1">
                                                                <label class="custom-control-label" for="subs">
                                                                    <h5 class="mb-1">{{ translate('Subscription') }}</h5>
                                                                    <p class="mb-0 fs-12">
                                                                        {{ translate('By selecting subscription based business model stores can run business with you based on subscription package.') }}
                                                                    </p>
                                                                    <div
                                                                        class="d-flex p-2 px-3 rounded gap-2 bg-opacity-warning-10 mt-3">
                                                                        <i class="tio-info text-warning"></i>
                                                                        <p class="fz-12px mb-0">
                                                                            {{translate('To activate the subscription based business model, first add a subscription package')}}:
                                                                            <a href="{{route('admin.business-settings.subscriptionackage.index')}}"
                                                                                class="fz-12px font-semibold info-dark text-underline">{{translate('Subscription packages')}}</a>
                                                                        </p>
                                                                    </div>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input"
                                                                    id="commission" name="commission_business_model" {{ $commission_business_model ? 'checked' : '' }} value="1">
                                                                <label class="custom-control-label" for="commission">
                                                                    <h5 class="mb-1">{{ translate('Commission') }}</h5>
                                                                    <p class="mb-0 fs-12">
                                                                        {{ translate('By selecting commission based business model stores can run business with you based on commission based payment per order.') }}
                                                                    </p>
                                                                    <div
                                                                        class="info-notes-bg px-3 py-2 rounded fz-11  gap-2 d-flex mt-20">
                                                                        <img src="{{asset('public/assets/admin/img/info-idea.svg')}}"
                                                                            alt="">
                                                                        <span>
                                                                            {{translate('To set different commission for commission based stores.')}}
                                                                            {{translate('Go to')}}: <span
                                                                                class="fz-12px font-semibold info-dark">{{translate('Store list')}}
                                                                                > {{translate('Store details')}} >
                                                                                {{translate('Business plan')}}</span>
                                                                        </span>
                                                                    </div>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-sm-6 col-lg-6">
                                                    @php($admin_commission = \App\Models\BusinessSetting::where('key', 'admin_commission')->first())
                                                    <div class="form-group mb-0">
                                                        <label class="form-label text-capitalize" for="admin_commission">
                                                            {{ translate('Default commission rate on order') }} (%)
                                                            <span class="text-danger">*</span>
                                                            <span class="form-label-secondary" data-toggle="tooltip"
                                                                data-placement="right"
                                                                data-original-title="{{ translate('Set up \'default commission rate\' on every order. Admin can also set store-wise different commission rates from respective store settings.') }}">
                                                                <i class="tio-info text-muted"></i>
                                                            </span>
                                                        </label>
                                                        <input type="number" name="admin_commission" class="form-control"
                                                            id="admin_commission"
                                                            placeholder="{{ translate('Ex') . ': 10' }}"
                                                            value="{{ $admin_commission ? $admin_commission->value : 0 }}"
                                                            min="0" max="100" required>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 col-lg-6">
                                                    @php($delivery_charge_comission = \App\Models\BusinessSetting::where('key', 'delivery_charge_comission')->first())
                                                    <div class="form-group mb-0">
                                                        <label class="input-label text-capitalize d-flex alig-items-center"
                                                            for="delivery_charge_comission">
                                                            {{translate('Commission rate on delivery charge')}} (%)
                                                            <span class="text-danger">*</span>
                                                            <span class="form-label-secondary ml-1" data-toggle="tooltip"
                                                                data-placement="right"
                                                                data-original-title="{{ translate('Set a default \'commission rate\' for freelance deliverymen (under admin) on every deliveryman.') }} ">
                                                                <i class="tio-info text-muted"></i>
                                                            </span>
                                                        </label>
                                                        <input type="number" name="delivery_charge_comission"
                                                            class="form-control" id="delivery_charge_comission"
                                                            placeholder="{{ translate('Ex') . ': 10' }}" min="0"
                                                            max="100" step="{{ \App\CentralLogics\Helpers::getDecimalPlaces() }}"
                                                            value="{{ $delivery_charge_comission ? $delivery_charge_comission->value : 0 }}">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="shadow-sm p-xxl-20 p-xl-3 p-2 bg-white mb-20" id="additional_charge_section">
                                <div class="row g-3">
                                    <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                                        <div>
                                            <h4 class="mb-1">
                                                {{ translate('Additional charge setup') }}
                                            </h4>
                                            <p class="mb-0 fs-12">
                                                {{ translate('By switching this feature ON, customer need to pay the amount you set.') }} 
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                                        @php($additional_charge_status = \App\CentralLogics\Helpers::get_business_settings('additional_charge_status', false) ?? 0)
                                        <div class="form-group mb-0">
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1">
                                                        {{translate('Status') }}
                                                    </span>
                                                </span>
                                                <input type="checkbox" data-id="additional_charge_status" data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/dm-tips-on.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/dm-tips-off.png') }}"
                                                    data-title-on="<strong>{{ translate('Want to enable additional charge?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('Want to disable additional charge?') }}</strong>"
                                                    data-text-on="<p>{{ translate('If you enable this, additional charge will be added with order amount, it will be added in admin wallet') }}</p>"
                                                    data-text-off="<p>{{ translate('If you disable this, additional charge will not be added with order amount.') }}</p>"
                                                    class="status toggle-switch-input dynamic-checkbox-toggle" value="1"
                                                    name="additional_charge_status" id="additional_charge_status" {{ $additional_charge_status == 1 ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-light2 rounded p-xxl-20 p-3 additional__body mt-20">
                                    <div class="row g-3">
                                        <div class="col-sm-6 col-lg-6">
                                            @php($additional_charge_name = \App\Models\BusinessSetting::where('key', 'additional_charge_name')->first())
                                            <div class="form-group mb-0">
                                                <label
                                                    class="form-label d-flex justify-content-between text-capitalize mb-1"
                                                    for="additional_charge_name">
                                                    <span
                                                        class="line--limit-1">{{ translate('Additional charge name') }}
                                                        <span class="text-danger">*</span>
                                                    </span>
                                                </label>

                                                <input type="text" name="additional_charge_name" class="form-control"
                                                    id="additional_charge_name"
                                                    placeholder="{{ translate('Ex') . ': ' . translate('Processing fee') }}" maxlength="50"
                                                    value="{{ $additional_charge_name ? $additional_charge_name->value : '' }}"
                                                    {{ isset($additional_charge_status) ? '' : 'readonly' }} required>
                                                       <span
                                                    class="text-right text-counting color-A7A7A7 d-block mt-1">0/50</span>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-lg-6">
                                            @php($additional_charge = \App\Models\BusinessSetting::where('key', 'additional_charge')->first())
                                            <div class="form-group mb-0">
                                                <label
                                                    class="form-label d-flex justify-content-between text-capitalize mb-1"
                                                    for="additional_charge">
                                                    <span class="line--limit-1">{{ translate('Charge amount') }}
                                                        ({{ \App\CentralLogics\Helpers::currency_symbol() }}) <span
                                                            class="text-danger">*</span>
                                                    </span>
                                                </label>

                                                <input type="number" name="additional_charge" class="form-control"
                                                    id="additional_charge" placeholder="{{ translate('Ex') . ': 10' }}"
                                                    value="{{ $additional_charge ? $additional_charge->value : 0 }}" min="0"
                                                    step="{{ \App\CentralLogics\Helpers::getDecimalPlaces() }}" {{ isset($additional_charge_status) ? '' : 'readonly' }}>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="additional_charge_note">
                                    <div class="info-notes-bg px-3 py-2 rounded fz-11  gap-2 d-flex mt-20">
                                        <img src="{{asset('public/assets/admin/img/info-idea.svg')}}" alt="">
                                        <span>
                                            {{translate('Only admin will get the additional amount & customer must pay the amount.')}}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-20" id="others_setup_section">
                        <div class="card-body">
                            <div class="mb-20">
                                <h4 class="mb-1">
                                    {{ translate('Others setup') }}
                                </h4>
                                <p class="mb-0 fs-12">
                                    {{ translate('Here you set up your other business settings') }}
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
                            <div class="shadow-sm p-xxl-20 p-xl-3 p-2 bg-white" id="content_setup_section">
                                <div class="mb-20">
                                    <h4 class="mb-1">
                                        {{ translate('Copyright & cookies text') }}
                                    </h4>
                                    <p class="mb-0 fs-12">
                                        {{ translate('Add the necessary texts to display in required sections') }}
                                    </p>
                                </div>
                                <div class="bg-light2 rounded p-xxl-20 p-3">
                                    <div class="row g-3">
                                        <div class="col-md-6 col-xl-6">
                                            @php($footer_text = \App\Models\BusinessSetting::where('key', 'footer_text')->first())
                                            <div class="form-group mb-0">
                                                <label class="form-label"
                                                    for="footer_text">{{ translate('Copyright text') }}
                                                    <span class="form-label-secondary" data-toggle="tooltip"
                                                        data-placement="right"
                                                        data-original-title="{{ translate('Make visitors aware of your business\'s rights & legal information.') }}">
                                                        <i class="tio-info text-muted"></i>
                                                    </span>
                                                </label>
                                                <textarea type="text" id="footer_text" maxlength="100" name="footer_text"
                                                    class="form-control" rows="3"
                                                    placeholder="{{ translate('Ex') . ' : ' . translate('Copyright text') }}"
                                                    required>{{ $footer_text->value ?? '' }}</textarea>
                                                <span
                                                    class="text-right text-counting color-A7A7A7 d-block mt-1">0/100</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-xl-6">
                                            @php($cookies_text = \App\Models\BusinessSetting::where('key', 'cookies_text')->first())
                                            <div class="form-group mb-0">
                                                <label class="form-label" for="cookies_text">{{ translate('Cookies text') }}
                                                </label>
                                                <span class="form-label-secondary" data-toggle="tooltip"
                                                        data-placement="right"
                                                        data-original-title="{{ translate('Make visitors aware of your business\'s rights & legal information.') }}">
                                                        <i class="tio-info text-muted"></i>
                                                    </span>
                                                <textarea type="text" id="cookies_text" maxlength="100" name="cookies_text"
                                                    class="form-control " rows="3"
                                                    placeholder="{{ translate('Ex') . ' : ' . translate('Cookies text') }}"
                                                    required>{{ $cookies_text->value ?? '' }}</textarea>
                                                <span
                                                    class="text-right text-counting color-A7A7A7 d-block mt-1">0/100</span>
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
    </form>
</div>



<div class="modal fade" id="currency-warning-modal">
    <div class="modal-dialog modal-dialog-centered status-warning-modal">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto mb-20">
                    <div>
                        <div class="text-center">
                            <img width="80" src="{{  asset('public/assets/admin/img/modal/currency.png') }}"
                                class="mb-20">
                            <h5 class="modal-title"></h5>
                        </div>
                        <div class="text-center">
                            <h3> {{ translate('Are you sure to change the currency?') }}</h3>
                            <div>
                                <p>{{ translate('Activate at least one digital payment method that supports this currency, or nobody can pay digitally.') }}
                                </p>
                            </div>
                        </div>

                        <div class="text-center mb-4">
                            <a class="text--underline"
                                href="{{ route('admin.business-settings.third-party.payment-method') }}">
                                {{ translate('Go to payment method settings.') }}</a>
                        </div>
                    </div>

                    <div class="btn--container justify-content-center">
                        <button data-dismiss="modal" id="confirm-currency-change"
                            class="btn btn--cancel min-w-120"><i class="tio-clear-circle-outlined"></i> {{translate("Cancel")}}</button>
                        <button data-dismiss="modal" type="button"
                            class="btn btn--primary min-w-120"><i class="tio-checkmark-circle-outlined"></i> {{translate('OK')}}</button>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="global_guideline_offcanvas" class="custom-offcanvas d-flex flex-column justify-content-between global_guideline_offcanvas">
    <div>
        <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
            <h3 class="mb-0">{{ translate('Business settings guideline') }}</h3>
            <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0" aria-label="Close">&times;</button>
        </div>

        <div class="custom-offcanvas-body offcanvas-height-100 py-3 px-md-4 px-3">
            <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                    <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed" type="button" data-toggle="collapse" data-target="#maintenance_mode_guide" aria-expanded="true">
                        <div class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                            <i class="tio-down-ui"></i>
                        </div>
                        <span class="font-semibold text-left fs-14 text-title">{{ translate('Maintenance mode') }}</span>
                    </button>
                    <a href="#maintenance_mode_section" class="text-info text-underline fs-12 text-nowrap offcanvas-close offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                </div>
                <div class="collapse mt-3 show" id="maintenance_mode_guide">
                    <div class="card card-body">
                        <div class="">
                            <h5 class="mb-3">{{ translate('Maintenance mode') }}</h5>
                            <ul class="fs-12">
                                <li>{{ translate('Maintenance mode temporarily closes your store while you make updates.') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                    <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed" type="button" data-toggle="collapse" data-target="#basic_information_guide" aria-expanded="true">
                        <div class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                            <i class="tio-down-ui"></i>
                        </div>
                        <span class="font-semibold text-left fs-14 text-title">{{ translate('Basic information') }}</span>
                    </button>
                    <a href="#basic_information_section" class="text-info text-underline fs-12 text-nowrap offcanvas-close offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                </div>
                <div class="collapse mt-3" id="basic_information_guide">
                    <div class="card card-body">
                        <div class="">
                            <h5 class="mb-3">{{ translate('Basic information') }}</h5>
                            <ul class="fs-12">
                                <li><strong>{{ translate('Company name') }}:</strong> {{ translate('Enter your official company name. This name represents your business and is used across the system.') }}</li>
                                <li><strong>{{ translate('email') }}:</strong> {{ translate('Add your company email address. This email is used for business communication and records.') }}</li>
                                <li><strong>{{ translate('Phone') }}:</strong> {{ translate('Contact phone number customers and partners can reach you on.') }}</li>
                                <li><strong>{{ translate('Country') }}:</strong> {{ translate('Select your country. This is important for legal, operational, marketing, and payment-related settings.') }}</li>
                                <li><strong>{{ translate('Address') }}:</strong> {{ translate('This address is used to locate the business\'s physical location.') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                    <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed" type="button" data-toggle="collapse" data-target="#general_settings_guide" aria-expanded="true">
                        <div class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                            <i class="tio-down-ui"></i>
                        </div>
                        <span class="font-semibold text-left fs-14 text-title">{{ translate('General settings') }}</span>
                    </button>
                    <a href="#general_settings_section" class="text-info text-underline fs-12 text-nowrap offcanvas-close offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                </div>
                <div class="collapse mt-3" id="general_settings_guide">
                    <div class="card card-body">
                        <div class="">
                            <h5 class="mb-3">{{ translate('General settings') }}</h5>
                            <p class="fs-12 mb-0">{{ translate('Configure essential business details such as time zone and currency.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                    <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed" type="button" data-toggle="collapse" data-target="#business_model_guide" aria-expanded="true">
                        <div class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                            <i class="tio-down-ui"></i>
                        </div>
                        <span class="font-semibold text-left fs-14 text-title">{{ translate('Business model') }}</span>
                    </button>
                    <a href="#business_model_section" class="text-info text-underline fs-12 text-nowrap offcanvas-close offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                </div>
                <div class="collapse mt-3" id="business_model_guide">
                    <div class="card card-body">
                        <div class="">
                            <h5 class="mb-3">{{ translate('Business model') }}</h5>
                            <ul class="fs-12">
                                <li><strong>{{ translate('Subscription-based model') }}:</strong> {{ translate('Users pay a recurring fee to keep access for as long as their subscription is valid.') }}</li>
                                <li><strong>{{ translate('Commission-based model') }}:</strong> {{ translate('The platform takes a fixed percentage of each completed order, deducted before the vendor is paid.') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                    <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed" type="button" data-toggle="collapse" data-target="#additional_charge_guide" aria-expanded="true">
                        <div class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                            <i class="tio-down-ui"></i>
                        </div>
                        <span class="font-semibold text-left fs-14 text-title">{{ translate('Additional charge setup') }}</span>
                    </button>
                    <a href="#additional_charge_section" class="text-info text-underline fs-12 text-nowrap offcanvas-close offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                </div>
                <div class="collapse mt-3" id="additional_charge_guide">
                    <div class="card card-body">
                        <div class="">
                            <h5 class="mb-3">{{ translate('Additional charge setup') }}</h5>
                            <p class="fs-12 mb-0">{{ translate('Add extra fees to orders under set conditions. They apply at checkout and are visible to everyone.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                    <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed" type="button" data-toggle="collapse" data-target="#others_setup_guide" aria-expanded="true">
                        <div class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                            <i class="tio-down-ui"></i>
                        </div>
                        <span class="font-semibold text-left fs-14 text-title">{{ translate('Others setup') }}</span>
                    </button>
                    <a href="#others_setup_section" class="text-info text-underline fs-12 text-nowrap offcanvas-close offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                </div>
                <div class="collapse mt-3" id="others_setup_guide">
                    <div class="card card-body">
                        <div class="">
                            <h5 class="mb-3">{{ translate('Others setup') }}</h5>
                            <ul class="fs-12">
                                <li><strong>{{ translate('Country picker') }}:</strong> {{ translate('This option allows users to pick their country code while typing a phone number.') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                    <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed" type="button" data-toggle="collapse" data-target="#content_setup_guide" aria-expanded="true">
                        <div class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                            <i class="tio-down-ui"></i>
                        </div>
                        <span class="font-semibold text-left fs-14 text-title">{{ translate('Content setup') }}</span>
                    </button>
                    <a href="#content_setup_section" class="text-info text-underline fs-12 text-nowrap offcanvas-close offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                </div>
                <div class="collapse mt-3" id="content_setup_guide">
                    <div class="card card-body">
                        <div class="">
                            <h5 class="mb-3">{{ translate('Content setup') }}</h5>
                            <ul class="fs-12">
                                <li><strong>{{ translate('Copyright text') }}:</strong> {{ translate('Ownership statement for your site content — usually ©, the year and your company name.') }}</li>
                                <li><strong>{{ translate('Cookies text') }}:</strong> {{ translate('Short notice telling visitors your site uses cookies.') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="offcanvasOverlay" class="offcanvas-overlay"></div>



<div class="modal fade" id="maintenance-off-mode-modal">
    <div class="modal-dialog modal-dialog-centered status-warning-modal">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <form method="post" action="{{ route('admin.maintenance-mode') }}">
                @csrf
                <input type="hidden" name="maintenance_mode_off" value="1">
                <div class="modal-body pb-5 pt-0">
                    <div class="max-349 mx-auto mb-20">
                        <div class="text-center">
                            <img width="80" src="{{ asset('public/assets/admin/img/modal/maintenance-off.png') }}" class="mb-20" alt="">
                            <h5 class="modal-title">{{ translate('Are you sure?') }}</h5>
                        </div>
                        <div class="text-center">
                            <p>{{ translate('Do you want to turn off maintenance mode? Turning it off will activate all systems that were deactivated.') }}</p>
                        </div>
                        <div class="btn--container justify-content-center">
                            <button data-dismiss="modal" type="button" class="btn btn--cancel min-w-120px"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}</button>
                            <button type="submit" class="btn btn--primary min-w-120px"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Yes') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="maintenance-mode-modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header pt-3">
                <h3>{{ translate('Maintenance mode') }}</h3>
                <button type="button" class="close bg-modal-btn w-30px h-30 rounded-circle position-absolute right-0 top-0 m-2 z-2" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <form method="post" action="{{route('admin.maintenance-mode')}}" id="maintenance-mode-form">
                @csrf
                <div class="modal-body pt-3 px-0">
                    <div class="px-4 mb-20">
                        <div class="bg-light rounded p-3">
                            <div class="row g-3">
                                <div class="col-xxl-6 col-lg-8 col-md-7 col-sm-6">
                                    <div>
                                        <p class="mb-0 fs-12">
                                            {{ translate('Turn on the maintenance mode will temporarily deactivate your selected systems as of your chosen date and time.') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-xxl-6 col-lg-4 col-md-5 col-sm-6">
                                    <div class="maintenance-mode-toggle-bar bg-white d-flex py-2 flex-wrap justify-content-between border rounded align-items-center y-2 px-3">
                                        <h5 class="text-capitalize m-0 font-weight-normal fs-14 text-dark">
                                            {{ translate('Maintenance mode') }}
                                        </h5>
                                        <label class="toggle-switch toggle-switch-sm">
                                            <input type="checkbox" class="toggle-switch-input" id="maintenanceModalToggle" {{ isset($config) && $config ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text mb-0">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex px-4 flex-column gap-4" id="maintenanceFormBody">
                        <div class="bg-light rounded p-20">
                            <div class="row mb-4">
                                <div class="col-xl-4">
                                    <h5 class="mb-2">{{ translate('Select system') }} <span class="text-danger">*</span></h5>
                                    <p class="fs-12">{{ translate('Select the systems you want to temporarily deactivate for maintenance') }}</p>
                                </div>
                                <div class="col-xl-8">
                                    <div class="border p-3 p-sm-3 bg-white rounded system_select-inner">
                                        <div class="d-flex flex-wrap gap-4">
                                            <div class="form-group m-0">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input system-checkbox" id="allSystem"
                                                        {{ in_array('vendor_panel', $selectedMaintenanceSystem) &&
                                                           in_array('user_mobile_app', $selectedMaintenanceSystem) &&
                                                           in_array('user_web_app', $selectedMaintenanceSystem) &&
                                                           in_array('deliveryman_app', $selectedMaintenanceSystem) &&
                                                           in_array('vendor_app', $selectedMaintenanceSystem) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="allSystem">
                                                        <h5 class="mb-0 font-light lh-24 text-reset">{{ translate('All system') }}</h5>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="form-group m-0">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input system-checkbox" name="user_mobile_app" id="mobile_app"
                                                        {{ in_array('user_mobile_app', $selectedMaintenanceSystem) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="mobile_app">
                                                        <h5 class="mb-0 font-light lh-24 text-reset">{{ translate('Mobile app') }}</h5>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="form-group m-0">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input system-checkbox" name="user_web_app" id="web_app"
                                                        {{ in_array('user_web_app', $selectedMaintenanceSystem) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="web_app">
                                                        <h5 class="mb-0 font-light lh-24 text-reset">{{ translate('Web app') }}</h5>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="form-group m-0">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input system-checkbox" name="vendor_panel" id="vendor_panel"
                                                        {{ in_array('vendor_panel', $selectedMaintenanceSystem) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="vendor_panel">
                                                        <h5 class="mb-0 font-light lh-24 text-reset">{{ translate('Vendor panel') }}</h5>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="form-group m-0">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input system-checkbox" name="vendor_app" id="vendor_app"
                                                        {{ in_array('vendor_app', $selectedMaintenanceSystem) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="vendor_app">
                                                        <h5 class="mb-0 font-light lh-24 text-reset">{{ translate('Vendor app') }}</h5>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="form-group m-0">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input system-checkbox" name="deliveryman_app" id="deliveryman_app"
                                                        {{ in_array('deliveryman_app', $selectedMaintenanceSystem) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="deliveryman_app">
                                                        <h5 class="mb-0 font-light lh-24 text-reset">{{ translate('Deliveryman app') }}</h5>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="form-group m-0">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input system-checkbox" name="react_website" id="react_website"
                                                        {{ in_array('react_website', $selectedMaintenanceSystem) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="react_website">
                                                        <h5 class="mb-0 font-light lh-24 text-reset">{{ translate('React website') }}</h5>
                                                    </label>
                                                </div>
                                            </div>
                                            @if (addon_published_status('RideShare'))
                                            <div class="form-group m-0">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input system-checkbox" name="rider_app" id="rider_app"
                                                        {{ in_array('rider_app', $selectedMaintenanceSystem) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="rider_app">
                                                        <h5 class="mb-0 font-light lh-24 text-reset">{{ translate('Rider app') }}</h5>
                                                    </label>
                                                </div>
                                            </div>
                                            @endif
                                            @if (addon_published_status('Service'))
                                            <div class="form-group m-0">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input system-checkbox" name="serviceman_app" id="serviceman_app"
                                                        {{ in_array('serviceman_app', $selectedMaintenanceSystem) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="serviceman_app">
                                                        <h5 class="mb-0 font-light lh-24 text-reset">{{ translate('Serviceman app') }}</h5>
                                                    </label>
                                                </div>
                                            </div>
                                            @endif
                                            @if (addon_published_status('Builder'))
                                            <div class="form-group m-0">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input system-checkbox" name="vendor_storefront" id="vendor_storefront"
                                                        {{ in_array('vendor_storefront', $selectedMaintenanceSystem) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="vendor_storefront">
                                                        <h5 class="mb-0 font-light lh-24 text-reset">{{ translate('Vendor storefront') }}</h5>
                                                    </label>
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="border-bottom mb-4"></div>
                            <div class="row mb-4">
                                <div class="col-xl-4">
                                    <h5 class="mb-2">{{ translate('Maintenance date') }} & {{ translate('Time') }} <span class="text-danger">*</span></h5>
                                    <p class="fs-12">{{ translate('Choose the maintenance mode duration for your selected system.') }}</p>
                                </div>
                                <div class="col-xl-8">
                                    <div class="d-flex flex-wrap gap-4 min-h-45px align-items-center bg-white border px-3 py-2 rounded mb-3">
                                        <div class="form-check form--check">
                                            <input class="form-check-input" type="radio" name="maintenance_duration"
                                                    {{ isset($selectedMaintenanceDuration['maintenance_duration']) && $selectedMaintenanceDuration['maintenance_duration'] == 'one_day' ? 'checked' : '' }}
                                                    value="one_day" id="one_day">
                                            <label class="form-check-label opacity-100" for="one_day">{{ translate('For one day') }}</label>
                                        </div>
                                        <div class="form-check form--check">
                                            <input class="form-check-input" type="radio" name="maintenance_duration"
                                                    {{ isset($selectedMaintenanceDuration['maintenance_duration']) && $selectedMaintenanceDuration['maintenance_duration'] == 'one_week' ? 'checked' : '' }}
                                                    value="one_week" id="one_week">
                                            <label class="form-check-label opacity-100" for="one_week">{{ translate('For one week') }}</label>
                                        </div>
                                        <div class="form-check form--check">
                                            <input class="form-check-input" type="radio" name="maintenance_duration"
                                                    {{ isset($selectedMaintenanceDuration['maintenance_duration']) && $selectedMaintenanceDuration['maintenance_duration'] == 'until_change' ? 'checked' : '' }}
                                                    value="until_change" id="until_change">
                                            <label class="form-check-label opacity-100" for="until_change">{{ translate('Until I change') }}</label>
                                        </div>
                                        <div class="form-check form--check">
                                            <input class="form-check-input" type="radio" name="maintenance_duration"
                                                    {{ isset($selectedMaintenanceDuration['maintenance_duration']) && $selectedMaintenanceDuration['maintenance_duration'] == 'customize' ? 'checked' : '' }}
                                                    value="customize" id="customize">
                                            <label class="form-check-label opacity-100" for="customize">{{ translate('Customize') }}</label>
                                        </div>
                                    </div>
                                    <div class="">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">{{ translate('Start date') }} <span class="text-danger">*</span></label>
                                                <input type="datetime-local" class="form-control h-40" name="start_date" id="startDate"
                                                        value="{{ old('start_date', $selectedMaintenanceDuration['start_date'] ?? '') }}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">{{ translate('End date') }} <span class="text-danger">*</span></label>
                                                <input type="datetime-local" class="form-control h-40" name="end_date" id="endDate"
                                                        value="{{ old('end_date', $selectedMaintenanceDuration['end_date'] ?? '') }}" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <small id="dateError" class="form-text text-danger" style="display: none;">{{ translate('Start date cannot be greater than end date.') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion" id="accordionExample">
                                <div id="collapseThree" class="collapse" aria-labelledby="headingThree" data-parent="#accordionExample">
                                    <div class="border-top pt-4">
                                        <div class="row align-items-center">
                                            <div class="col-xl-4">
                                                <h5 class="mb-2">{{ translate('Maintenance message') }}</h5>
                                                <p>{{ translate('Select and type the message you want your selected system to show when maintenance mode is active.') }}</p>
                                            </div>
                                            <div class="col-xl-8">
                                                <div class="">
                                                    <div class="mb-20">
                                                        <label class="form-label">{{ translate('Show contact information') }}</label>
                                                        <div class="d-flex flex-wrap gap-5 align-items-center border rounded bg-white py-2 px-3 min-h-45px">
                                                            <div class="form-group m-0">
                                                                <div class="custom-control custom-checkbox">
                                                                    <input type="checkbox" class="custom-control-input" name="business_number" id="business_number"
                                                                        {{ isset($selectedMaintenanceMessage['business_number']) && $selectedMaintenanceMessage['business_number'] == 1 ? 'checked' : '' }}>
                                                                    <label class="custom-control-label" for="business_number">
                                                                        <h5 class="mb-0 font-light lh-24 text-reset">{{ translate('Business number') }}</h5>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <div class="form-group m-0">
                                                                <div class="custom-control custom-checkbox">
                                                                    <input type="checkbox" class="custom-control-input" name="business_email" id="business_email"
                                                                        {{ isset($selectedMaintenanceMessage['business_email']) && $selectedMaintenanceMessage['business_email'] == 1 ? 'checked' : '' }}>
                                                                    <label class="custom-control-label" for="business_email">
                                                                        <h5 class="mb-0 font-light lh-24 text-reset">{{ translate('Business email') }}</h5>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="form-group mb-0">
                                                        <label class="form-label">{{ translate('Message title') }} <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control h-40" name="maintenance_message" id="maintenance_message"
                                                            placeholder="{{ translate('We are working on something special!') }}"
                                                            maxlength="100" value="{{ $selectedMaintenanceMessage['maintenance_message'] ?? '' }}">
                                                        <div class="d-flex justify-content-end">
                                                            <span class="text-counting text-body-light text-right d-block mt-1">0/100</span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group mt-3">
                                                        <label class="form-label">{{ translate('Message body') }} <span class="text-danger">*</span></label>
                                                        <div class="character-count">
                                                            <textarea class="form-control character-count-field h-40" rows="1" name="message_body" id="message_body" maxlength="100" placeholder="{{ translate('We are working on something special!') }}">{{ $selectedMaintenanceMessage['message_body'] ?? '' }}</textarea>
                                                            <div class="d-flex justify-content-end">
                                                                <span class="text-counting text-body-light text-right d-block mt-1">0/100</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="m-0 text-center d-center" id="headingThree">
                                    <div class="mb-0">
                                        <button class="advance-button font-weight-medium outline-0 shadow-none d-block mb-3 btn p-0 text-underline text--primary collapsed" type="button" data-toggle="collapse" data-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                            <span class="advance-text">
                                                {{ translate('Advanced settings') }}
                                            </span>
                                            <span class="basic-text">
                                                {{ translate('Basic settings') }}
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="btn--container justify-content-end py-3">
                        <button type="reset" class="btn btn--reset min-w-120px" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}</button>
                        <button type="{{ getEnvMode() != 'demo' ? 'submit' : 'button' }}" class="btn btn--primary {{ getEnvMode() == 'demo' ? 'demo_check' : '' }} min-w-120px" id="submit">
                            <i class="tio-save"></i> {{ translate('Save') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection



@push('script_2')

<script>
    "use strict";
    (function () {
        function clampToMin(el) {
            var min = parseFloat(el.getAttribute('min'));
            if (!isNaN(min) && el.value !== '' && parseFloat(el.value) < min) {
                el.value = min;
            }
        }
        $(document).on('keydown', 'input[type="number"][min]', function (e) {
            if (parseFloat(this.getAttribute('min')) >= 0 && (e.key === '-' || e.key === 'e' || e.key === 'E')) {
                e.preventDefault();
            }
        });
        $(document).on('input change blur', 'input[type="number"][min]', function () {
            clampToMin(this);
        });
        $('input[type="number"][min]').each(function () { clampToMin(this); });
    })();
</script>

<script>
    "use strict";

    $(document).ready(function () {
        let selectedCurrency = "{{ $currency_code ? $currency_code->value : 'USD' }}";
        let currencyConfirmed = false;
        let updatingCurrency = false;

        $("#change_currency").change(function () {
            if (!updatingCurrency) check_currency($(this).val());
        });

        $("#confirm-currency-change").click(function () {
            currencyConfirmed = true;
            update_currency(selectedCurrency);
            $('#currency-warning-modal').modal('hide');
        });

        function check_currency(currency) {
            $.ajax({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                url: "{{route('admin.system_currency')}}",
                method: 'GET',
                data: { currency: currency },
                success: function (response) {
                    if (response.data) {
                        $('#currency-warning-modal').modal('show');
                    } else {
                        update_currency(currency);
                    }
                }
            });
        }

        function update_currency(currency) {
            if (currencyConfirmed) {
                updatingCurrency = true;
                $("#change_currency").val(currency).trigger('change');
                updatingCurrency = false;
                currencyConfirmed = false;
            }
        }
    });

</script>

<script
    src="https://maps.googleapis.com/maps/api/js?key={{ \App\CentralLogics\Helpers::get_business_settings('map_api_key', false) }}&libraries=places,marker&v=3.61">
    </script>
<script>
    "use strict";

    @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)
    let language = <?php echo $language; ?>;
    $('[id=language]').val(language);





    function readURL(input, viewer) {
        if (input.files && input.files[0]) {
            let reader = new FileReader();
            reader.onload = function (e) {
                $('#' + viewer).attr('src', e.target.result);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    $("#customFileEg1").change(function () {
        readURL(this, 'viewer');
    });

    $("#favIconUpload").change(function () {
        readURL(this, 'iconViewer');
    });

    function initAutocomplete() {
        const mapId = "{{ \App\CentralLogics\Helpers::get_business_settings('map_api_key', false) }}"

        var myLatLng = {
            lat: {{ $default_location ? $default_location['lat'] : '-33.8688' }},
            lng: {{ $default_location ? $default_location['lng'] : '151.2195' }}
            };
        const map = new google.maps.Map(document.getElementById("location_map_canvas"), {
            center: {
                lat: {{ $default_location ? $default_location['lat'] : '-33.8688' }},
                lng: {{ $default_location ? $default_location['lng'] : '151.2195' }}
                },
            zoom: 13,
            mapTypeId: "roadmap",
            mapId: mapId,
        });

        const { AdvancedMarkerElement } = google.maps.marker;

        var marker = new AdvancedMarkerElement({
            position: myLatLng,
            map: map,
        });

        var geocoder = geocoder = new google.maps.Geocoder();
        google.maps.event.addListener(map, 'click', function (mapsMouseEvent) {
            var coordinates = JSON.stringify(mapsMouseEvent.latLng.toJSON(), null, 2);
            var coordinates = JSON.parse(coordinates);
            var latlng = new google.maps.LatLng(coordinates['lat'], coordinates['lng']);
            marker.position = latlng;
            marker.map = map;
            map.panTo(latlng);

            markers.forEach((m) => {
                m.map = null;
            });
            markers = [];

            document.getElementById('latitude').value = coordinates['lat'];
            document.getElementById('longitude').value = coordinates['lng'];


            geocoder.geocode({
                'latLng': latlng
            }, function (results, status) {
                if (status == google.maps.GeocoderStatus.OK) {
                    if (results[1]) {
                        document.getElementById('address').value = results[1].formatted_address;
                    }
                }
            });
        });
        // Create the search box and link it to the UI element.
        const input = document.getElementById("pac-input");
        const searchBox = new google.maps.places.SearchBox(input);
        map.controls[google.maps.ControlPosition.TOP_CENTER].push(input);
        // Bias the SearchBox results towards current map's viewport.
        map.addListener("bounds_changed", () => {
            searchBox.setBounds(map.getBounds());
        });
        let markers = [];
        // Listen for the event fired when the user selects a prediction and retrieve
        // more details for that place.
        searchBox.addListener("places_changed", () => {
            const places = searchBox.getPlaces();

            if (places.length == 0) {
                return;
            }
            // Clear out the old markers.
            markers.forEach((m) => {
                m.map = null;
            });
            markers = [];
            marker.map = null;
            // For each place, get the icon, name and location.
            const bounds = new google.maps.LatLngBounds();
            places.forEach((place) => {
                if (!place.geometry || !place.geometry.location) {
                    console.log("Returned place contains no geometry");
                    return;
                }
                const { AdvancedMarkerElement } = google.maps.marker;
                var mrkr = new AdvancedMarkerElement({
                    map,
                    title: place.name,
                    position: place.geometry.location,
                });
                google.maps.event.addListener(mrkr, "click", function (event) {
                    document.getElementById('latitude').value = this.position.lat();
                    document.getElementById('longitude').value = this.position.lng();
                });

                markers.push(mrkr);

                if (place.geometry.viewport) {
                    // Only geocodes have viewport.
                    bounds.union(place.geometry.viewport);
                } else {
                    bounds.extend(place.geometry.location);
                }
            });
            map.fitBounds(bounds);
        });
    };

    $(document).on('ready', function () {
        initAutocomplete();
    });

    $(document).on("keydown", "input", function (e) {
        if (e.which === 13) e.preventDefault();
    });

    // Business Model Validation
    $('#subs, #commission').on('change', function (e) {

            if (!$('#subs').is(':checked') && !$('#commission').is(':checked')) {
                e.preventDefault();
                toastr.error('{{ translate("At least one business model must be selected") }}');
                $(this).prop('checked', true);
            }

    });

    $(document).on('click', '.confirm-Toggle', function () {
        setTimeout(function () {
            let toggle_id = $("#toggle-ok-button").attr("toggle-ok-button");
            if (toggle_id === "additional_charge_status") {
                if ($("#additional_charge_status").is(":checked")) {
                    $('.additional__body').slideDown();
                    $('#additional_charge_note').slideDown();
                } else {
                    $('.additional__body').slideUp();
                    $('#additional_charge_note').slideUp();
                }
            } else if (toggle_id === 'maintenance_mode') {
                if ($('#maintenance_mode').is(':checked')) {
                    $('#maintenance-mode-modal').modal('show');
                } else {
                    $('#maintenance-off-mode-modal').modal('show');
                }
            }
        }, 0);
    });

    $(document).ready(function () {
        if ($('#additional_charge_status').is(':checked')) {
            $('.additional__body').show();
            $('#additional_charge_note').show();
        } else {
            $('.additional__body').hide();
            $('#additional_charge_note').hide();
        }
    });

    const maintenanceIsOn = {{ isset($config) && $config ? 'true' : 'false' }};

    function setMaintenanceFormDisabled(disabled) {
        const $body = $('#maintenanceFormBody');
        const $save = $('#submit');
        $body.find('input, select, textarea, button').not('#maintenanceModalToggle').prop('disabled', disabled);
        $body.css({ 'opacity': disabled ? '0.5' : '1', 'pointer-events': disabled ? 'none' : '' });
        $save.prop('disabled', disabled);
    }

    $(document).on('click', '.maintenance-mode-toggle', function (e) {
        e.preventDefault();
        $('#maintenance-mode-modal').modal('show');
    });

    $('#maintenanceModalToggle').on('change', function () {
        if ($(this).prop('checked')) {
            setMaintenanceFormDisabled(false);
        } else {
            if (maintenanceIsOn) {
                $(this).prop('checked', true);
                $('#maintenance-mode-modal').modal('hide');
                $('#maintenance-off-mode-modal').modal('show');
            } else {
                setMaintenanceFormDisabled(true);
            }
        }
    });

    $(document).on('click', '.demo_check', function (e) {
        e.preventDefault();
        toastr.warning('{{ translate('Sorry! You cannot enable maintenance mode in demo!') }}');
    });

    // All-system checkbox toggle
    $(document).on('change', '.system-checkbox', function () {
        const $all = $('#allSystem');
        const $others = $('.system-checkbox').not($all);
        if ($(this).is($all)) {
            $others.prop('checked', $all.prop('checked'));
        } else {
            $all.prop('checked', $others.length === $others.filter(':checked').length);
        }
    });

    const fmtMaintenanceDT = dt => {
        const pad = n => String(n).padStart(2, '0');
        return dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate()) + 'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
    };

    const applyMaintenanceDateMin = () => {
        const min = fmtMaintenanceDT(new Date());
        $('#startDate').attr('min', min);
        $('#endDate').attr('min', $('#startDate').val() || min);
    };

    $('input[name="maintenance_duration"]').on('change', function () {
        const val = $(this).val();
        const fmt = fmtMaintenanceDT;
        const now = new Date();
        const $dateRow = $('#startDate').closest('.row');
        if (val === 'one_day') {
            $('#startDate').val(fmt(now));
            $('#endDate').val(fmt(new Date(now.getTime() + 86400000)));
            $dateRow.hide();
            $('#startDate, #endDate').removeAttr('required');
        } else if (val === 'one_week') {
            $('#startDate').val(fmt(now));
            $('#endDate').val(fmt(new Date(now.getTime() + 604800000)));
            $dateRow.hide();
            $('#startDate, #endDate').removeAttr('required');
        } else if (val === 'until_change') {
            $dateRow.hide();
            $('#startDate, #endDate').removeAttr('required');
        } else {
            $('#startDate').val('').attr('required', true);
            $('#endDate').val('').attr('required', true);
            applyMaintenanceDateMin();
            $dateRow.show();
        }
    });

    $('#startDate').on('change', function () {
        const min = fmtMaintenanceDT(new Date());
        if (this.value && this.value < min) {
            this.value = min;
        }
        $('#endDate').attr('min', this.value || min);
        if ($('#endDate').val() && $('#endDate').val() < this.value) {
            $('#endDate').val('');
        }
    });

    $('#maintenance-mode-modal').on('show.bs.modal', function () {
        setMaintenanceFormDisabled(!maintenanceIsOn);

        const val = $('input[name="maintenance_duration"]:checked').val();
        if (val === 'customize') {
            $('#startDate').closest('.row').show();
            $('#startDate, #endDate').attr('required', true);
            applyMaintenanceDateMin();
        } else {
            $('#startDate').closest('.row').hide();
            $('#startDate, #endDate').removeAttr('required');
        }
        $('#collapseThree').removeClass('show');
        $('#maintenance_message, #message_body').removeAttr('required');
        $('#accordionExample .advance-button').addClass('collapsed').attr('aria-expanded', 'false');
    });

    $('#collapseThree').on('shown.bs.collapse', function () {
        $('#maintenance_message, #message_body').attr('required', true);
    }).on('hidden.bs.collapse', function () {
        $('#maintenance_message, #message_body').removeAttr('required');
    });

    $('#maintenance-mode-form').on('submit', function (e) {
        if ($('.system-checkbox').not('#allSystem').filter(':checked').length === 0) {
            e.preventDefault();
            toastr.error('{{ translate("Please select at least one system") }}');
            return false;
        }
        if ($('input[name="maintenance_duration"]:checked').length === 0) {
            e.preventDefault();
            toastr.error('{{ translate("Please select a maintenance date & time duration") }}');
            return false;
        }
    });
</script>
@endpush



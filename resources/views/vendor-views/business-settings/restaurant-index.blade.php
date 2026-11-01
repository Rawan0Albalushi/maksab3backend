@use('App\Support\Settings\BusinessRules')
@extends('layouts.vendor.app')

@section('title', translate('Settings'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/store-setup.css') }}">
@endpush

@php($isServiceStore = ($store->module_type ?? $store->module?->module_type) === 'service' && service_addon_active())
@php($module_type = $store->module->module_type)
@php($module_config = config('module.' . $module_type))
@php([$delivery_min, $delivery_rest] = array_pad(explode('-', (string) $store->delivery_time, 2), 2, ''))
@php([$delivery_max, $delivery_unit] = array_pad(explode(' ', trim($delivery_rest), 2), 2, ''))
@php($has_processing_time = (bool) $module_config['order_place_to_schedule_interval'])
@php($has_extra_packaging = ($extra_packaging_data[$module_type] ?? '0') == '1')
@php($order_options = [
    ['id' => 'schedule_order', 'label' => translate('Scheduled order'), 'hint' => translate('Customers can order now for a later time.'), 'on' => (bool) $store->schedule_order],
    ['id' => 'delivery', 'label' => translate('messages.delivery'), 'hint' => translate('Customers can have orders delivered to their address.'), 'on' => (bool) $store->delivery],
    ['id' => 'take_away', 'label' => translate('messages.Take away'), 'hint' => translate('Customers can collect orders from your store.'), 'on' => (bool) $store->take_away],
    ...($module_type == 'pharmacy' && $prescription_order_status ? [
        ['id' => 'prescription_order', 'label' => translate('messages.Prescription order'), 'hint' => translate('Customers can upload a prescription to order.'), 'on' => (bool) $store->prescription_order],
    ] : []),
    ...($store->sub_self_delivery == 1 ? [
        ['id' => 'free_delivery', 'label' => translate('Free delivery'), 'hint' => translate('Customers pay no delivery charge on your orders.'), 'on' => (bool) $store->free_delivery],
    ] : []),
    ...(BusinessRules::vegNonVegEnabled() && $module_config['veg_non_veg'] ? [
        ['id' => 'veg', 'label' => translate('Veg'), 'hint' => translate('You sell vegetarian items.'), 'on' => (bool) $store->veg],
        ['id' => 'non_veg', 'label' => translate('Non veg'), 'hint' => translate('You sell non-vegetarian items.'), 'on' => (bool) $store->non_veg],
    ] : []),
    ...($module_config['cutlery'] ? [
        ['id' => 'cutlery', 'label' => translate('messages.cutlery'), 'hint' => translate('Customers can ask for cutlery with their order.'), 'on' => (bool) $store->cutlery],
    ] : []),
    ...($module_config['halal'] ? [
        ['id' => 'halal_tag_status', 'label' => translate('messages.Halal tag status'), 'hint' => translate('Show the halal tag on items marked halal.'), 'on' => (bool) $store->storeConfig?->halal_tag_status],
    ] : []),
])

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/config.png') }}" class="w--30" alt="">
                </span>
                <span>{{ $isServiceStore ? translate('Provider setup') : translate('messages.Store setup') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Your opening hours, delivery area, charges and the basics customers see.') }}</p>
        </div>

        <div class="tps sts">
            <div class="tps-card sts-status {{ $store->active ? 'is-open' : 'is-closed' }}">
                <span class="sts-status__icon"><i class="tio-shop"></i></span>
                <div class="sts-status__text">
                    <div class="sts-status__title">
                        <h2 class="tps-card__title">{{ $isServiceStore ? translate('Provider availability') : translate('Store availability') }}</h2>
                        <span class="tps-pill {{ $store->active ? 'tps-pill--on' : 'tps-pill--warn' }}">
                            {{ $store->active ? translate('Open') : translate('Temporarily closed') }}
                        </span>
                    </div>
                    <p class="tps-card__subtitle">
                        {{ $store->active ? translate('Customers can order during your opening hours.') : translate('Customers cannot order until you reopen.') }}
                    </p>
                </div>
                <label class="toggle-switch toggle-switch-sm sts-status__switch" for="restaurant-open-status">
                    <span class="sts-status__switch-text">{{ translate('Temporarily close') }}</span>
                    <input type="checkbox" id="restaurant-open-status" class="toggle-switch-input restaurant-open-status"
                        {{ $store->active ? '' : 'checked' }}>
                    <span class="toggle-switch-label">
                        <span class="toggle-switch-indicator"></span>
                    </span>
                </label>
            </div>

            @if ($isServiceStore)
                @php($providerConfig = $store->storeConfig)
                @php($atProviderPlaceEnabled = service_setting_enabled('service_at_provider_place'))
                @php($servicemanCancelEnabled = service_setting_enabled('service_serviceman_cancel_booking_req'))
                @php($chosenLocations = $providerConfig?->choose_service_location ?: ['customer'])
                @php($booking_options = array_filter([
                    'instant_booking' => service_setting_enabled('service_instant_booking') ? translate('Instant booking') : null,
                    'repeat_booking' => service_setting_enabled('service_repeat_booking') ? translate('Repeat booking') : null,
                    'schedule_booking' => service_setting_enabled('service_schedule_booking') ? translate('Schedule booking') : null,
                ]))
                <form action="{{ route('vendor.service.business.provider-settings.update') }}" method="post" class="tps-card">
                    @csrf
                    <input type="hidden" name="choose_service_location_present" value="1">
                    @if ($servicemanCancelEnabled)
                        <input type="hidden" name="serviceman_permission_present" value="1">
                    @endif
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-settings-outlined"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Provider settings') }}</h2>
                            <p class="tps-card__subtitle">{{ translate('How customers can book you and what your servicemen may do.') }}</p>
                        </div>
                    </div>
                    <div class="tps-card__body">
                        @if ($booking_options)
                            <div class="tps-group">
                                <p class="tps-group__label">{{ translate('Booking types') }}</p>
                                <div class="sts-options">
                                    @foreach ($booking_options as $booking_key => $booking_label)
                                        <label class="sts-option" for="{{ $booking_key }}">
                                            <span class="sts-option__title">{{ $booking_label }}</span>
                                            <span class="toggle-switch toggle-switch-sm">
                                                <input type="checkbox" class="toggle-switch-input" name="{{ $booking_key }}" value="1"
                                                    id="{{ $booking_key }}" {{ $providerConfig?->{$booking_key} ? 'checked' : '' }}>
                                                <span class="toggle-switch-label">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <div class="tps-group">
                            <p class="tps-group__label">{{ translate('Choose your service location') }}</p>
                            <p class="sts-group-hint">{{ translate('messages.Select the option where you want to provide your service') }}</p>
                            <div class="sts-options">
                                <label class="sts-option sts-option--check">
                                    <input type="checkbox" class="service-location-option" name="choose_service_location[]" value="customer"
                                        {{ in_array('customer', $chosenLocations) ? 'checked' : '' }}>
                                    <span class="sts-option__title">{{ translate('Go to customer location') }}</span>
                                </label>
                                @if ($atProviderPlaceEnabled)
                                    <label class="sts-option sts-option--check">
                                        <input type="checkbox" class="service-location-option" name="choose_service_location[]" value="provider"
                                            {{ in_array('provider', $chosenLocations) ? 'checked' : '' }}>
                                        <span class="sts-option__title">{{ translate('Customer will come to my location') }}</span>
                                    </label>
                                @endif
                            </div>
                        </div>
                        @if ($servicemanCancelEnabled)
                            <div class="tps-group">
                                <p class="tps-group__label">{{ translate('Servicemen permission') }}</p>
                                <p class="sts-group-hint">{{ translate('messages.Manage what this provider\'s servicemen are allowed to do') }}</p>
                                <div class="sts-options">
                                    <label class="sts-option sts-option--check">
                                        <input type="checkbox" name="serviceman_can_cancel_booking" value="1"
                                            {{ $providerConfig?->serviceman_can_cancel_booking ? 'checked' : '' }}>
                                        <span class="sts-option__title">{{ translate('Can cancel booking') }}</span>
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="tps-card__foot">
                        <span class="tps-foot-note">{{ translate('Changes here apply once you save.') }}</span>
                        <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                        <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('Save') }}</button>
                    </div>
                </form>
            @endif

            @if (!$isServiceStore)
                <div class="tps-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-shopping-cart"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Order options') }}</h2>
                            <p class="tps-card__subtitle">{{ translate('How customers can order from you. Each switch saves as soon as you flip it.') }}</p>
                        </div>
                    </div>
                    <div class="tps-card__body">
                        <div class="sts-options">
                            @foreach ($order_options as $option)
                                <label class="sts-option" for="{{ $option['id'] }}">
                                    <span class="sts-option__text">
                                        <span class="sts-option__title">{{ $option['label'] }}</span>
                                        <span class="sts-option__desc">{{ $option['hint'] }}</span>
                                    </span>
                                    <span class="toggle-switch toggle-switch-sm">
                                        <input type="checkbox" class="toggle-switch-input redirect-url" id="{{ $option['id'] }}"
                                            data-url="{{ route('vendor.business-settings.toggle-settings', [$store->id, $option['on'] ? 0 : 1, $option['id']]) }}"
                                            {{ $option['on'] ? 'checked' : '' }}>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <form action="{{ route('vendor.business-settings.update-setup', [$store['id']]) }}" method="post"
                    class="tps-card" id="store-setup-form">
                    @csrf
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-receipt-outlined"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Orders and charges') }}</h2>
                            <p class="tps-card__subtitle">{{ translate('The minimum order, delivery time and what is added at checkout.') }}</p>
                        </div>
                    </div>
                    <div class="tps-card__body">
                        <div class="tps-group">
                            <p class="tps-group__label">{{ translate('Orders') }}</p>
                            <div class="row g-3">
                                <div class="col-md-6 {{ $has_processing_time ? 'col-lg-4' : '' }}">
                                    <div class="tps-field">
                                        <label class="tps-field__label" for="minimum_order">
                                            {{ translate('messages.Minimum order amount') }} <span class="tps-req">*</span>
                                        </label>
                                        <div class="sts-affix">
                                            <span class="sts-affix__unit">{{ $currency_symbol }}</span>
                                            <input type="number" id="minimum_order" name="minimum_order" step="0.01" min="0.01"
                                                max="999999999" class="form-control" placeholder="100" required
                                                value="{{ $store->minimum_order > 0 ? $store->minimum_order : 0 }}">
                                        </div>
                                        <span class="tps-field__hint">{{ translate('Specify the minimum order amount required for customers when ordering from this store.') }}</span>
                                    </div>
                                </div>
                                @if ($has_processing_time)
                                    <div class="col-md-6 col-lg-4">
                                        <div class="tps-field">
                                            <label class="tps-field__label" for="order_place_to_schedule_interval">
                                                {{ translate('messages.Minimum processing time') }}
                                            </label>
                                            <input type="text" id="order_place_to_schedule_interval" name="order_place_to_schedule_interval"
                                                class="form-control" value="{{ $store->order_place_to_schedule_interval }}">
                                            <span class="tps-field__hint">{{ translate('Set the total time to process the order after order confirmation.') }}</span>
                                        </div>
                                    </div>
                                @endif
                                <div class="{{ $has_processing_time ? 'col-lg-4' : 'col-md-6' }}">
                                    <div class="tps-field">
                                        <label class="tps-field__label" for="minimum_delivery_time">
                                            {{ translate('Approximate delivery time') }} <span class="tps-req">*</span>
                                        </label>
                                        <div class="sts-range">
                                            <input type="number" id="minimum_delivery_time" name="minimum_delivery_time" min="0"
                                                class="form-control" placeholder="{{ translate('Min') }}: 10" required
                                                value="{{ $delivery_min }}" aria-label="{{ translate('messages.Minimum delivery time') }}">
                                            <span class="sts-range__sep">–</span>
                                            <input type="number" name="maximum_delivery_time" min="0" class="form-control"
                                                placeholder="{{ translate('Max') }}: 20" required
                                                value="{{ $delivery_max }}" aria-label="{{ translate('messages.Maximum delivery time') }}">
                                            <select name="delivery_time_type" class="custom-select" required>
                                                <option value="min" {{ $delivery_unit == 'min' ? 'selected' : '' }}>{{ translate('messages.minutes') }}</option>
                                                <option value="hours" {{ $delivery_unit == 'hours' ? 'selected' : '' }}>{{ translate('messages.hours') }}</option>
                                                <option value="days" {{ $delivery_unit == 'days' ? 'selected' : '' }}>{{ translate('messages.days') }}</option>
                                            </select>
                                        </div>
                                        <span class="tps-field__hint">{{ translate('Set the total time to deliver products.') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($store->sub_self_delivery)
                            <div class="tps-group">
                                <p class="tps-group__label">{{ translate('Delivery charges') }}</p>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="tps-field">
                                            <label class="tps-field__label" for="minimum_shipping_charge">{{ translate('messages.Minimum shipping charge') }}</label>
                                            <div class="sts-affix">
                                                <span class="sts-affix__unit">{{ $currency_symbol }}</span>
                                                <input type="number" id="minimum_shipping_charge" min="0" max="99999999.99" step="0.01"
                                                    name="minimum_delivery_charge" class="form-control shipping_input" placeholder="0"
                                                    value="{{ $store?->minimum_shipping_charge ?? '' }}">
                                            </div>
                                            <span class="tps-field__hint">{{ translate('The least a customer pays for delivery.') }}</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="tps-field">
                                            <label class="tps-field__label" for="per_km_delivery_charge">{{ translate('messages.Delivery charge per') }} {{ $distanceUnitLabel }}</label>
                                            <div class="sts-affix">
                                                <span class="sts-affix__unit">{{ $currency_symbol }}</span>
                                                <input type="number" id="per_km_delivery_charge" name="per_km_delivery_charge" step="0.01"
                                                    min="0" max="999999999" class="form-control" placeholder="100"
                                                    value="{{ $store->per_km_shipping_charge ?? '0' }}">
                                            </div>
                                            <span class="tps-field__hint">{{ translate('Charged for each unit of distance.') }}</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="tps-field">
                                            <label class="tps-field__label" for="maximum_shipping_charge">{{ translate('messages.Maximum delivery charge') }}</label>
                                            <div class="sts-affix">
                                                <span class="sts-affix__unit">{{ $currency_symbol }}</span>
                                                <input type="number" id="maximum_shipping_charge" name="maximum_shipping_charge" step="0.01"
                                                    min="0" max="999999999" class="form-control" placeholder="10000"
                                                    value="{{ $store->maximum_shipping_charge ?? '' }}">
                                            </div>
                                            <span class="tps-field__hint">{{ translate('It will add a limit on total delivery charge.') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="tps-group">
                            <p class="tps-group__label">{{ translate('Tax and packaging') }}</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="tps-field">
                                        <div class="sts-switch-field__head">
                                            <label class="tps-field__label" for="gst">{{ translate('messages.GST') }}</label>
                                            <label class="toggle-switch toggle-switch-sm" for="gst_status">
                                                <input type="checkbox" class="toggle-switch-input" name="gst_status" id="gst_status"
                                                    value="1" {{ $store->gst_status ? 'checked' : '' }}>
                                                <span class="toggle-switch-label">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                        <input type="text" id="gst" name="gst" class="form-control" maxlength="100"
                                            value="{{ $store->gst_code }}" {{ $store->gst_status ? 'required' : 'readonly' }}>
                                        <span class="tps-field__hint">{{ translate('messages.If GST is enabled, the GST number will show on the invoice') }}</span>
                                    </div>
                                </div>
                                @if ($has_extra_packaging)
                                    <div class="col-md-6">
                                        <div class="tps-field">
                                            <div class="sts-switch-field__head">
                                                <label class="tps-field__label" for="extra_packaging_amount">{{ translate('messages.Extra packaging charge amount') }}</label>
                                                <label class="toggle-switch toggle-switch-sm" for="extra_packaging_status">
                                                    <input type="checkbox" data-id="extra_packaging_status" data-type="toggle"
                                                        data-image-on="{{ asset('/public/assets/admin/img/modal/schedule-on.png') }}"
                                                        data-image-off="{{ asset('/public/assets/admin/img/modal/schedule-off.png') }}"
                                                        data-title-on="{{ translate('Want to enable extra packaging status for this restaurant?') }}"
                                                        data-title-off="{{ translate('Want to disable extra packaging status for this restaurant?') }}"
                                                        data-text-on="<p>{{ translate('If enabled, customers have to pay extra packaging charge on order') }}</p>"
                                                        data-text-off="<p>{{ translate('If disabled, customers do not have to pay extra packaging charge on order.') }}</p>"
                                                        class="toggle-switch-input dynamic-checkbox-toggle"
                                                        name="extra_packaging_status" value="1" id="extra_packaging_status"
                                                        {{ $store->storeConfig?->extra_packaging_status == 1 ? 'checked' : '' }}>
                                                    <span class="toggle-switch-label">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                                </label>
                                            </div>
                                            <div class="sts-affix">
                                                <span class="sts-affix__unit">{{ $currency_symbol }}</span>
                                                <input type="number" id="extra_packaging_amount" name="extra_packaging_amount" step="0.01"
                                                    min="0" max="9999999999" class="form-control" placeholder="100"
                                                    {{ $store->storeConfig?->extra_packaging_status == 1 ? 'required' : 'readonly' }}
                                                    value="{{ $store->storeConfig?->extra_packaging_amount }}">
                                            </div>
                                            <span class="tps-field__hint">{{ translate('Customers can choose an extra packaging charge when placing an order.') }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="tps-card__foot">
                        <span class="tps-foot-note">{{ translate('Changes here apply once you save.') }}</span>
                        <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                        <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('Save') }}</button>
                    </div>
                </form>
            @endif

            @if (!$module_config['always_open'])
                <div class="tps-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-time"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Opening hours') }}</h2>
                            <p class="tps-card__subtitle">{{ translate('The hours customers can order from you. A day with no hours is closed.') }}</p>
                        </div>
                    </div>
                    <div class="tps-card__body" id="schedule">
                        @include('vendor-views.business-settings.partials._schedule')
                    </div>
                </div>
            @endif

            @if (!$isServiceStore && $module_type != 'food')
                <form action="{{ route('vendor.business-settings.update-stock-setup', [$store['id']]) }}" method="post" class="tps-card">
                    @csrf
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-archive"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Stock setup') }}</h2>
                            <p class="tps-card__subtitle">{{ translate('When an item counts as low on stock, and whether customers see it.') }}</p>
                        </div>
                    </div>
                    <div class="tps-card__body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="tps-field">
                                    <label class="tps-field__label" for="minimum_stock_for_warning_stock_card">{{ translate('messages.Minimum stock for warning') }}</label>
                                    <input type="number" id="minimum_stock_for_warning_stock_card" name="minimum_stock_for_warning"
                                        min="0" max="999999999" class="form-control" placeholder="{{ translate('messages.Ex') }}: 5"
                                        value="{{ $store?->storeConfig?->minimum_stock_for_warning ?? '' }}">
                                    <span class="tps-field__hint">{{ translate('When the stock of a product reaches its minimum value that you have set, you will receive a warning to update the stock. Additionally, these products will appear in the admin\'s low stock list.') }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="sts-option" for="show_low_stock_count">
                                    <span class="sts-option__text">
                                        <span class="sts-option__title">{{ translate('Show low stock count') }}</span>
                                        <span class="sts-option__desc">{{ translate('messages.If enabled, low stock count and warning products will be visible to customer.') }}</span>
                                    </span>
                                    <span class="toggle-switch toggle-switch-sm">
                                        <input type="checkbox" class="toggle-switch-input" name="show_low_stock_count"
                                            id="show_low_stock_count" value="1"
                                            {{ $store?->storeConfig?->show_low_stock_count == 1 ? 'checked' : '' }}>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="tps-card__foot">
                        <span class="tps-foot-note">{{ translate('Changes here apply once you save.') }}</span>
                        <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                        <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('Save') }}</button>
                    </div>
                </form>
            @endif

            @if (!$isServiceStore && addon_published_status('Builder') && $admin_website_builder_status == 1)
                <div class="tps-card" id="admin_website_builder_section">
                    <form action="{{ route('vendor.business-settings.website-builder-status', [$store->id, $store->storeConfig?->website_builder_status ? 0 : 1]) }}"
                        method="get" id="website_builder_status_form"></form>
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-globe"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Website builder') }}</h2>
                            <p class="tps-card__subtitle">{{ translate('Build and manage your own storefront website.') }}</p>
                        </div>
                        <div class="tps-card__aside">
                            <label class="toggle-switch toggle-switch-sm m-0" for="website_builder_status">
                                <input type="checkbox" data-id="website_builder_status" data-type="status"
                                    data-image-on="{{ asset('/public/assets/admin/img/modal/store-reg-on.png') }}"
                                    data-image-off="{{ asset('/public/assets/admin/img/modal/store-reg-off.png') }}"
                                    data-title-on="<strong>{{ translate('Are you sure to enable vendor website setup?') }}</strong>"
                                    data-title-off="<strong>{{ translate('Are you sure to disable vendor website setup?') }}</strong>"
                                    data-text-on="<p>{{ translate('If enabled, vendors will have the freedom to create, edit, and manage their own websites independently.') }}</p>"
                                    data-text-off="<p>{{ translate('If disabled, vendors cannot create or manage their own websites.') }}</p>"
                                    class="toggle-switch-input dynamic-checkbox" value="1"
                                    name="website_builder_status" id="website_builder_status"
                                    {{ $store->storeConfig?->website_builder_status == 1 ? 'checked' : '' }}>
                                <span class="toggle-switch-label">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            @endif

            <form action="{{ route('vendor.business-settings.update-meta-data', [$store['id']]) }}" method="post"
                enctype="multipart/form-data" class="sts-meta">
                @csrf
                @include('admin-views.business-settings.landing-page-settings.partial._meta_data', ['submit' => true])
            </form>

            <div class="modal fade" id="add-schedule-modal" tabindex="-1" role="dialog" aria-labelledby="add-schedule-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <form class="modal-content sts-modal" id="add-schedule" action="javascript:" method="post">
                        @csrf
                        <input type="hidden" name="day" id="day_id_input">
                        <div class="tps-card__head">
                            <span class="tps-card__brand"><i class="tio-time"></i></span>
                            <div class="tps-card__titles">
                                <h3 class="tps-card__title" id="add-schedule-title">{{ translate('Add opening hours') }}</h3>
                                <p class="tps-card__subtitle" id="add-schedule-day"></p>
                            </div>
                            <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Cancel') }}">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="tps-card__body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="tps-field">
                                        <label class="tps-field__label" for="schedule_start_time">{{ translate('messages.Start time') }}</label>
                                        <input type="time" id="schedule_start_time" class="form-control" name="start_time" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="tps-field">
                                        <label class="tps-field__label" for="schedule_end_time">{{ translate('messages.End time') }}</label>
                                        <input type="time" id="schedule_end_time" class="form-control" name="end_time" required>
                                    </div>
                                </div>
                            </div>
                            <span class="tps-field__hint">{{ translate('Time slots on the same day cannot overlap.') }}</span>
                        </div>
                        <div class="tps-card__foot">
                            <button type="button" class="btn btn--reset" data-dismiss="modal">{{ translate('Cancel') }}</button>
                            <button type="submit" class="btn btn--primary"><i class="tio-add"></i> {{ translate('Add hours') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        "use strict";

        $(document).on('click', '.restaurant-open-status', function (event) {
            event.preventDefault();
            Swal.fire({
                title: '{{ translate('messages.Are you sure?') }}',
                text: '{{ $store->active ? translate('messages.You want to temporarily close this store') : translate('messages.You want to open this store') }}',
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#00868F',
                cancelButtonText: '{{ translate('messages.No') }}',
                confirmButtonText: '{{ translate('messages.Yes') }}',
                reverseButtons: true
            }).then((result) => {
                if (!result.value) {
                    return;
                }
                $.get({
                    url: '{{ route('vendor.business-settings.update-active-status') }}',
                    beforeSend: function () {
                        $('#loading').show();
                    },
                    success: function (data) {
                        toastr.success(data.message);
                    },
                    complete: function () {
                        location.reload();
                    },
                });
            });
        });

        $(document).on('click', '.delete-schedule', function () {
            let route = $(this).data('url');
            Swal.fire({
                title: '{{ translate('Want to delete this schedule?') }}',
                text: '{{ translate('If you select yes, the time schedule will be deleted.') }}',
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#00868F',
                cancelButtonText: '{{ translate('messages.No') }}',
                confirmButtonText: '{{ translate('messages.Yes') }}',
                reverseButtons: true
            }).then((result) => {
                if (!result.value) {
                    return;
                }
                $.get({
                    url: route,
                    beforeSend: function () {
                        $('#loading').show();
                    },
                    success: function (data) {
                        if (data.errors) {
                            data.errors.forEach((error) => toastr.error(error.message, { CloseButton: true, ProgressBar: true }));
                            return;
                        }
                        $('#schedule').html(data.view);
                        toastr.success('{{ translate('Deleted successfully') }}', { CloseButton: true, ProgressBar: true });
                    },
                    error: function () {
                        toastr.error('{{ translate('No data found') }}', { CloseButton: true, ProgressBar: true });
                    },
                    complete: function () {
                        $('#loading').hide();
                    },
                });
            });
        });

        function syncLinkedField(switchSelector, fieldSelector) {
            const isOn = $(switchSelector).is(':checked');
            $(fieldSelector).prop('readonly', !isOn).prop('required', isOn);
        }

        function syncStoreSetupFields() {
            syncLinkedField('#gst_status', '#gst');
            syncLinkedField('#extra_packaging_status', '#extra_packaging_amount');
        }

        $(document).on('change', '#gst_status, #extra_packaging_status', syncStoreSetupFields);
        $(document).on('reset', '#store-setup-form', function () {
            setTimeout(syncStoreSetupFields);
        });

        $('#add-schedule-modal').on('show.bs.modal', function (event) {
            let button = $(event.relatedTarget);
            document.getElementById('add-schedule').reset();
            $('#add-schedule-day').text(button.data('day'));
            $('#day_id_input').val(button.data('dayid'));
        });

        $('#add-schedule').on('submit', function (event) {
            event.preventDefault();
            $.post({
                url: '{{ route('vendor.business-settings.add-schedule') }}',
                data: new FormData(this),
                cache: false,
                contentType: false,
                processData: false,
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    if (data.errors) {
                        data.errors.forEach((error) => toastr.error(error.message, { CloseButton: true, ProgressBar: true }));
                        return;
                    }
                    $('#schedule').html(data.view);
                    $('#add-schedule-modal').modal('hide');
                    toastr.success('{{ translate('Added successfully') }}', { CloseButton: true, ProgressBar: true });
                },
                error: function (xhr) {
                    toastr.error(xhr.responseText, { CloseButton: true, ProgressBar: true });
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        });

        $(document).on('change', '#instant_booking, #schedule_booking', function () {
            let $instant = $('#instant_booking');
            let $schedule = $('#schedule_booking');
            if ($instant.length && $schedule.length && !$instant.is(':checked') && !$schedule.is(':checked')) {
                $(this).prop('checked', true);
                toastr.warning('{{ translate('At least one of instant booking or schedule booking must be enabled.') }}');
            }
        });

        $(document).on('change', '.service-location-option', function () {
            if ($('.service-location-option:checked').length === 0) {
                $(this).prop('checked', true);
                toastr.warning('{{ translate('At least one service location must be selected.') }}');
            }
        });
    </script>
@endpush

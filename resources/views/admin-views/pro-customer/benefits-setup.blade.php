@extends('layouts.admin.app')

@section('title', translate('Pro customer benefits setup'))
@section('pro_customer_benefits_setup', 'active')

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')

@php
    $activeBenefit = (int) ($settings['delivery_fee_status'] ?? 0) === 1
        ? 'delivery_fee'
        : ((int) ($settings['coupon_status'] ?? 0) === 1 ? 'coupon' : 'discount');

    $setupMode = ($settings['discount_setup_mode'] ?? 'central') === 'individual' ? 'individual' : 'central';

    $benefitChoices = [
        'discount' => [
            'icon'  => 'tio-money',
            'title' => translate('Discount'),
            'desc'  => translate('A percentage off every order, capped at an amount you set.'),
        ],
        'coupon' => [
            'icon'  => 'tio-ticket',
            'title' => translate('Coupon'),
            'desc'  => translate('Coupons created with the Pro customer type, redeemable only by Pro members.'),
        ],
        'delivery_fee' => [
            'icon'  => 'tio-truck',
            'title' => translate('Delivery fee'),
            'desc'  => translate('Free or reduced delivery fee, set for each module.'),
        ],
    ];
@endphp

<div class="content container-fluid tps">
    <div class="tps-head">
        <div class="tps-head__title">
            <span class="tps-head__icon"><i class="tio-star"></i></span>
            <span class="tps-head__text">
                <h1>{{ translate('Pro customer benefits setup') }}</h1>
                <p>{{ translate('Pick the single perk Pro customers get, and set the numbers behind it.') }}</p>
            </span>
        </div>
    </div>

    <form action="{{ route('admin.pro-customer.benefits-setup.update') }}" method="post" id="pro-benefits-form">
        @csrf

        <div class="tps-card mb-3">
            <div class="tps-card__head">
                <span class="tps-card__brand"><i class="tio-tune"></i></span>
                <div class="tps-card__titles">
                    <h2 class="tps-card__title">{{ translate('Active benefit') }}</h2>
                    <p class="tps-card__subtitle">
                        {{ translate('Pro customers get one benefit at a time. Choose it here, then set it up below.') }}
                    </p>
                </div>
            </div>
            <div class="tps-card__body">
                <div class="row g-3">
                    @foreach ($benefitChoices as $key => $choice)
                        <div class="col-lg-4">
                            <label class="tps-choice">
                                <input type="radio" name="active_benefit" value="{{ $key }}"
                                    class="js-active-benefit" {{ $activeBenefit === $key ? 'checked' : '' }}>
                                <span class="tps-choice__box">
                                    <span class="tps-choice__mark"></span>
                                    <span>
                                        <span class="tps-choice__title">
                                            <i class="{{ $choice['icon'] }}"></i> {{ $choice['title'] }}
                                        </span>
                                        <span class="tps-choice__desc">{{ $choice['desc'] }}</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="tps-card mb-3 js-benefit-panel" data-benefit="discount"
            style="{{ $activeBenefit === 'discount' ? '' : 'display:none' }}">
            <div class="tps-card__head">
                <span class="tps-card__brand"><i class="tio-money"></i></span>
                <div class="tps-card__titles">
                    <h2 class="tps-card__title">{{ translate('messages.Discount Setup') }}</h2>
                    <p class="tps-card__subtitle">{{ translate('Set how much Pro customers save on every order.') }}</p>
                </div>
                <div class="tps-card__aside">
                    <div class="tps-seg">
                        <input type="radio" id="discount_mode_central" name="discount_setup_mode" value="central"
                            class="js-discount-mode" {{ $setupMode === 'central' ? 'checked' : '' }}>
                        <label for="discount_mode_central">{{ translate('messages.Central Setup') }}</label>
                        <input type="radio" id="discount_mode_individual" name="discount_setup_mode" value="individual"
                            class="js-discount-mode" {{ $setupMode === 'individual' ? 'checked' : '' }}>
                        <label for="discount_mode_individual">{{ translate('messages.Individual Setup') }}</label>
                    </div>
                </div>
            </div>

            <div class="tps-card__body">
                <div class="js-discount-central" style="{{ $setupMode === 'central' ? '' : 'display:none' }}">
                    @include('admin-views.pro-customer.partials._discount-fields', [
                        'prefix'     => 'discount_central',
                        'fieldId'    => 'discount_central',
                        'config'     => $discountConfig['central'] ?? [],
                        'pctTooltip' => translate('messages.Percentage discount pro customers receive on every order'),
                        'maxTooltip' => translate('messages.Maximum discount amount a customer can receive per order'),
                        'minLabel'   => translate('messages.Minimum order amount'),
                        'minTooltip' => translate('messages.Minimum order, ride or trip total required to qualify for the discount'),
                    ])
                </div>

                <div class="js-discount-individual" style="{{ $setupMode === 'individual' ? '' : 'display:none' }}">
                    @foreach ($discountModules as $mod)
                        <div class="tps-group">
                            <p class="tps-group__label">{{ $moduleLabels[$mod] ?? ucfirst($mod) }} {{ translate('messages.Module') }}</p>
                            @include('admin-views.pro-customer.partials._discount-fields', [
                                'prefix'     => 'discount_individual[' . $mod . ']',
                                'fieldId'    => 'discount_' . str_replace('-', '_', $mod),
                                'config'     => $discountConfig[$mod] ?? [],
                                'pctTooltip' => translate('messages.Percentage discount pro customers receive on orders in this module'),
                                'maxTooltip' => translate('messages.Maximum discount amount a customer can receive per order in this module'),
                                'minLabel'   => $minOrderLabels[$mod] ?? translate('messages.Minimum order amount'),
                                'minTooltip' => $minOrderTooltips[$mod] ?? translate('messages.Minimum order total required to qualify for the discount in this module'),
                            ])
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="tps-card mb-3 js-benefit-panel" data-benefit="coupon"
            style="{{ $activeBenefit === 'coupon' ? '' : 'display:none' }}">
            <div class="tps-card__head">
                <span class="tps-card__brand"><i class="tio-ticket"></i></span>
                <div class="tps-card__titles">
                    <h2 class="tps-card__title">{{ translate('Coupon') }}</h2>
                    <p class="tps-card__subtitle">{{ translate('Pro members can redeem coupons that nobody else can.') }}</p>
                </div>
            </div>
            <div class="tps-card__body">
                <div class="tps-note tps-note--info">
                    <i class="tio-info-outined"></i>
                    <div>
                        {{ translate('Create special coupons and choose Pro Customer as the coupon type') }}:
                        <a href="{{ route('admin.coupon.add-new') }}" class="text-underline font-weight-medium">{{ translate('messages.coupons') }}</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="tps-card mb-3 js-benefit-panel" data-benefit="delivery_fee"
            style="{{ $activeBenefit === 'delivery_fee' ? '' : 'display:none' }}">
            <div class="tps-card__head">
                <span class="tps-card__brand"><i class="tio-truck"></i></span>
                <div class="tps-card__titles">
                    <h2 class="tps-card__title">{{ translate('Delivery fee') }}</h2>
                    <p class="tps-card__subtitle">{{ translate('Set how the delivery fee is reduced for Pro customers in each module.') }}</p>
                </div>
            </div>

            <div class="tps-card__body">
                @foreach ($deliveryFeeModules as $mod)
                    @php
                        $isParcel  = $mod === 'parcel';
                        $offerType = $deliveryFeeConfig[$mod]['offer_type'] ?? 'full_free';
                        $isPartial = $isParcel || $offerType === 'partial_free';
                        $fieldId   = 'delivery_' . str_replace('-', '_', $mod);
                    @endphp
                    <div class="tps-group">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <p class="tps-group__label mb-0">{{ $moduleLabels[$mod] ?? ucfirst($mod) }} {{ translate('messages.Module') }}</p>
                            @if ($isParcel)
                                <input type="hidden" name="delivery_fee[parcel][offer_type]" value="partial_free">
                                <small class="tps-field__hint m-0">{{ translate('Parcel always uses a percentage discount on the delivery charge.') }}</small>
                            @else
                                <div class="tps-seg">
                                    <input type="radio" id="{{ $fieldId }}_full_free" class="js-delivery-type"
                                        data-mod="{{ $mod }}" name="delivery_fee[{{ $mod }}][offer_type]" value="full_free"
                                        {{ $isPartial ? '' : 'checked' }}>
                                    <label for="{{ $fieldId }}_full_free">{{ translate('messages.Full Free') }}</label>
                                    <input type="radio" id="{{ $fieldId }}_partial_free" class="js-delivery-type"
                                        data-mod="{{ $mod }}" name="delivery_fee[{{ $mod }}][offer_type]" value="partial_free"
                                        {{ $isPartial ? 'checked' : '' }}>
                                    <label for="{{ $fieldId }}_partial_free">{{ translate('messages.Partial Free') }}</label>
                                </div>
                            @endif
                        </div>

                        <div class="row g-3 align-items-end">
                            <div class="col-xl-4 col-md-6 js-partial-field-{{ $mod }}"
                                style="{{ $isPartial ? '' : 'display:none' }}">
                                <div class="tps-field">
                                    <label class="tps-field__label" for="{{ $fieldId }}_charge_discount">
                                        {{ translate('messages.Discount (%)') }} <span class="tps-req">*</span>
                                        <span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                                            data-title="{{ translate('messages.Percentage of delivery fee that will be discounted for pro customers') }}"><i class="tio-info text-muted"></i></span>
                                    </label>
                                    <input type="number" id="{{ $fieldId }}_charge_discount"
                                        name="delivery_fee[{{ $mod }}][charge_discount]"
                                        class="form-control" min="{{ $minStep }}" step="{{ $minStep }}" max="100"
                                        placeholder="{{ translate('messages.Ex') . ': 20' }}"
                                        value="{{ $deliveryFeeConfig[$mod]['charge_discount'] ?? '' }}"
                                        {{ $isPartial ? '' : 'disabled' }}>
                                </div>
                            </div>

                            <div class="col-xl-4 col-md-6">
                                <div class="tps-field">
                                    <div class="tps-field__label justify-content-between">
                                        <label class="mb-0 d-flex align-items-center gap-1" for="{{ $fieldId }}_min_order_amount">
                                            {{ $minOrderLabels[$mod] ?? translate('messages.Minimum order amount') }} ({{ $currencySymbol }})
                                            <span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                                                data-title="{{ $isParcel ? translate('messages.Minimum delivery charge required to qualify for this delivery benefit') : translate('messages.Minimum order total required to qualify for this delivery benefit') }}"><i class="tio-info text-muted"></i></span>
                                        </label>
                                        <label class="toggle-switch toggle-switch-sm m-0">
                                            <input type="checkbox" name="delivery_fee[{{ $mod }}][min_order_status]" value="1"
                                                class="toggle-switch-input js-min-toggle"
                                                {{ ($deliveryFeeConfig[$mod]['min_order_status'] ?? 0) ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text"><span class="toggle-switch-indicator"></span></span>
                                        </label>
                                    </div>
                                    <input type="number" id="{{ $fieldId }}_min_order_amount"
                                        name="delivery_fee[{{ $mod }}][min_order_amount]"
                                        class="form-control js-min-field" min="{{ $minStep }}" step="{{ $minStep }}"
                                        placeholder="{{ translate('messages.Ex') . ': 100' }}"
                                        value="{{ $deliveryFeeConfig[$mod]['min_order_amount'] ?? '' }}"
                                        {{ ($deliveryFeeConfig[$mod]['min_order_status'] ?? 0) ? '' : 'disabled' }}>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @include('admin-views.partials._floating-submit-button')
    </form>
</div>

@endsection

@push('script_2')
<script>
(function () {
    'use strict';

    var $form              = $('#pro-benefits-form');
    var discountModules    = @json($discountModules);
    var deliveryFeeModules = @json($deliveryFeeModules);

    function activeBenefit() {
        return $('input[name="active_benefit"]:checked').val() || 'discount';
    }

    function syncPanels() {
        var active = activeBenefit();
        $('.js-benefit-panel').each(function () {
            $(this).toggle($(this).data('benefit') === active);
        });
    }

    function syncDiscountMode() {
        var individual = $('input[name="discount_setup_mode"]:checked').val() === 'individual';
        $('.js-discount-central').toggle(!individual);
        $('.js-discount-individual').toggle(individual);
    }

    function syncMinField($toggle) {
        var $field = $toggle.closest('.tps-field').find('.js-min-field');
        $field.prop('disabled', !$toggle.prop('checked'));
        if (!$toggle.prop('checked')) $field.val('').removeClass('is-invalid');
    }

    function syncDeliveryType(mod) {
        var partial = $('input[name="delivery_fee[' + mod + '][offer_type]"]:checked').val() === 'partial_free';
        $('.js-partial-field-' + mod).toggle(partial)
            .find('input').prop('disabled', !partial).filter(':disabled').removeClass('is-invalid');
    }

    function clearInvalid() {
        $form.find('.is-invalid').removeClass('is-invalid');
    }

    $(document).on('change', '.js-active-benefit', function () {
        clearInvalid();
        syncPanels();
    });

    $(document).on('change', '.js-discount-mode', function () {
        clearInvalid();
        syncDiscountMode();
    });

    $(document).on('change', '.js-min-toggle', function () { syncMinField($(this)); });
    $(document).on('change', '.js-delivery-type', function () { syncDeliveryType($(this).data('mod')); });

    $form.on('reset', function () {
        window.setTimeout(function () {
            clearInvalid();
            syncPanels();
            syncDiscountMode();
            $('.js-min-toggle').each(function () { syncMinField($(this)); });
            deliveryFeeModules.forEach(function (mod) {
                if (mod !== 'parcel') syncDeliveryType(mod);
            });
        }, 0);
    });

    function checkVal($input) {
        var ok = $.trim($input.val()) !== '';
        $input.toggleClass('is-invalid', !ok);
        return ok;
    }

    $(document).on('input change', '.is-invalid', function () {
        if ($.trim($(this).val()) !== '') $(this).removeClass('is-invalid');
    });

    $form.on('submit', function (e) {
        var valid     = true;
        var $firstErr = null;
        var benefit   = activeBenefit();

        function check(selector) {
            var $input = $(selector);
            if (!$input.length || $input.prop('disabled')) return;
            if (!checkVal($input)) {
                valid = false;
                if (!$firstErr) $firstErr = $input;
            }
        }

        if (benefit === 'discount') {
            if ($('input[name="discount_setup_mode"]:checked').val() === 'individual') {
                discountModules.forEach(function (mod) {
                    check('[name="discount_individual[' + mod + '][percentage]"]');
                    check('[name="discount_individual[' + mod + '][max_amount]"]');
                    check('[name="discount_individual[' + mod + '][min_order_amount]"]');
                });
            } else {
                check('[name="discount_central[percentage]"]');
                check('[name="discount_central[max_amount]"]');
                check('[name="discount_central[min_order_amount]"]');
            }
        }

        if (benefit === 'delivery_fee') {
            deliveryFeeModules.forEach(function (mod) {
                check('[name="delivery_fee[' + mod + '][charge_discount]"]');
                check('[name="delivery_fee[' + mod + '][min_order_amount]"]');
            });
        }

        if (!valid) {
            e.preventDefault();
            $firstErr[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            $firstErr.trigger('focus');
        }
    });

}());
</script>
@endpush

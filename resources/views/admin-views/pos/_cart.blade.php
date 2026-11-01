<?php
    $subtotal = 0;
    $addon_price = 0;
    $tax = session()->get('tax_amount');
    $discount = 0;
    $discount_type = 'amount';
    $discount_on_product = 0;
    $variation_price  = 0;

    $cart_lines = [];
    $cart_quantity = 0;

    if (session()->has('cart') && count(session()->get('cart')) > 0) {
        $cart = session()->get('cart');
        if (isset($cart['discount'])) {
            $discount = $cart['discount'];
            $discount_type = $cart['discount_type'];
        }

        foreach ($cart as $key => $cartItem) {
            if (!is_array($cartItem)) {
                continue;
            }

            $variation_price     += $cartItem['variation_price'] ?? 0;
            $product_subtotal     = $cartItem['price'] * $cartItem['quantity'];
            $discount_on_product += $cartItem['discount'] * $cartItem['quantity'];
            $subtotal            += $product_subtotal;
            $addon_price         += $cartItem['addon_price'];
            $cart_quantity       += (int) $cartItem['quantity'];

            $cart_lines[$key] = [
                'item'     => $cartItem,
                'subtotal' => $product_subtotal,
                'max'      => (isset($cartItem['stock_quantity']) && $cartItem['stock_quantity'] > 0)
                    ? ($cartItem['maximum_cart_quantity']
                        ? min($cartItem['stock_quantity'], $cartItem['maximum_cart_quantity'])
                        : $cartItem['stock_quantity'])
                    : ($cartItem['maximum_cart_quantity'] ?? 9999999999),
            ];
        }
    }

    $total = $subtotal + $addon_price;

    if ($discount_type == 'percent' && $discount > 0) {
        $discount_amount = (($total - $discount_on_product) * $discount) / 100;
    } else {
        $discount_amount = $discount;
    }

    $total -= ($discount_amount + $discount_on_product);

    $tax_amount = session()->get('tax_amount') ?? 0;
    $tax_included = session()->get('tax_included');

    $base_delivery_fee = (float) session()->get('address.delivery_fee', 0);
    $pos_surge_note     = session()->get('address.surge_note');
    $pos_order_type    = (string) (session('order_type') ?? 'delivery');

    $pos_eligible_amount = max(0, $subtotal + $addon_price - $discount_on_product - ($discount_amount ?? 0));
    $pos_free_delivery   = app(\App\Services\Order\OrderService::class)->effectiveFee(
        $base_delivery_fee,
        $store ?? null,
        $pos_eligible_amount,
        app(\App\Services\Order\OrderService::class)->resolveCouponCodeFromSession(),
    );
    $delivery_fee              = (float) $pos_free_delivery['fee'];
    $pos_free_delivery_by      = $pos_free_delivery['free_by'];
    $pos_is_free_delivery      = (bool) $pos_free_delivery['is_free'];
    $delivery_fee_for_selector = $delivery_fee;

    $pos_module_zone_pivot = ($store && $store->zone_id)
        ? \App\Models\ModuleZone::query()
            ->where('module_id', $store->module_id)
            ->where('zone_id', $store->zone_id)
            ->first()
        : null;
    // The Additional Charge setup answers this, not `additional_delivery_option_status` -- that
    // column belongs to the predecessor feature and nothing writes it any more.
    $pos_saver_enabled = $pos_module_zone_pivot
        && app(\App\Services\Zone\AdditionalDeliveryChargeService::class)
            ->activeSetup($pos_module_zone_pivot->zone_id, $pos_module_zone_pivot->module_id) !== null;
    // Matches DeliveryChargeService::quote()'s own rate resolution (and
    // POSDeliveryTypeTrait::applySaverToOrder(), which the order actually placed here goes
    // through): an active DeliveryRule's minimum supersedes the pivot's own column. Reading
    // the pivot column directly let this preview show a floor the order itself did not use.
    $pos_min_charge    = $pos_module_zone_pivot
        ? (float) (app(\App\Services\Order\DeliveryChargeService::class)->deliveryFloor($pos_module_zone_pivot->zone_id, $pos_module_zone_pivot->module_id, $pos_module_zone_pivot) ?? 0)
        : 0.0;

    $is_delivery_context = $pos_saver_enabled && $pos_order_type === 'delivery';

    // Neither option is gated on how $delivery_fee compares to $pos_min_charge — matching
    // POSDeliveryTypeTrait::applySaverToOrder(), which applies both unconditionally "on purpose"
    // (TC_445). Express is a flat premium independent of the base fee entirely. Slightly Delay's
    // reduction is separately clamped below at max(0, fee - floor), so a fee already at or below
    // the floor (a Pro customer's delivery-fee benefit, a coupon, ...) just reduces by $0 there —
    // still a valid, selectable choice, not one to gate out here. An earlier version of this
    // gated Slightly Delay on `$delivery_fee >= $pos_min_charge`, which silently made it
    // unselectable for exactly the customers most likely to have a fee under the floor.
    $is_express_eligible        = $is_delivery_context;
    $is_slightly_delay_eligible = $is_delivery_context;

    $deliveryType       = session()->get('delivery_type', '');
    $deliveryTypeCharge = ($is_express_eligible || $is_slightly_delay_eligible) ? (float) session()->get('delivery_type_charge', 0) : 0;
    $isExpressDelivery  = $is_express_eligible        && $deliveryType === 'express'        && $deliveryTypeCharge > 0;
    $isSlightlyDelay    = $is_slightly_delay_eligible && $deliveryType === 'slightly_delay' && $deliveryTypeCharge > 0;

    $pos_pro_discount             = (float) session()->get('pos_pro_discount', 0);
    $pos_pro_benefit_type         = session()->get('pos_pro_benefit_type');

    $adjustedDeliveryFee = $delivery_fee;
    if ($isExpressDelivery) {
        $adjustedDeliveryFee = $delivery_fee + $deliveryTypeCharge;
    } elseif ($isSlightlyDelay) {
        // The reduction actually applied is capped at max(0, fee - floor), matching
        // POSDeliveryTypeTrait::applySaverToOrder() exactly — re-clamped here rather than trusted
        // from session, since a stale or directly-posted $deliveryTypeCharge could still carry an
        // unclamped value. The old `max($pos_min_charge, fee - charge)` clamp was wrong whenever
        // the fee already sat below the floor (a Pro customer's delivery-fee benefit, a coupon,
        // ...): it returned the floor itself — a "discount" priced higher than the undiscounted
        // fee — instead of leaving the fee unchanged.
        $reduceApplied = min($deliveryTypeCharge, max(0, $delivery_fee - $pos_min_charge));
        $adjustedDeliveryFee = $delivery_fee - $reduceApplied;
    }

    // Applied against $adjustedDeliveryFee — the fee as the customer actually sees it, Express
    // premium or Slightly Delay reduction already folded in — not against the pre-saver
    // $delivery_fee. A Slightly Delay reduction already lowers what is charged before Pro ever
    // runs, so discounting the pre-reduction base overstated the benefit (and, symmetrically, an
    // Express premium the customer is already paying belongs in what Pro's percentage applies to
    // as well). Matches POSController::place_order()'s own equivalent step.
    $pos_pro_offer = [
        'status'  => $pos_pro_benefit_type !== null,
        'benefit' => [
            'type'                       => $pos_pro_benefit_type,
            'offer_type'                 => session()->get('pos_pro_delivery_offer_type'),
            'charge_discount_percentage' => session()->get('pos_pro_delivery_percentage'),
            'min_order_amount'           => session()->get('pos_pro_min_order_amount'),
            'min_order_status'           => session()->get('pos_pro_min_order_status'),
        ],
    ];
    $proDeliveryApply = app(\App\Services\Order\OrderService::class)->applyProCustomerDeliveryFee(
        $pos_pro_offer,
        $adjustedDeliveryFee,
        $total - $pos_pro_discount,
        null,
        $store->module_type ?? null,
    );
    $pos_pro_delivery_savings = (float) ($proDeliveryApply['savings'] ?? 0);

    $total -= $pos_pro_discount;
    $total += $adjustedDeliveryFee;
    $total -= $pos_pro_delivery_savings;

    $deliveryTypeLabels = [
        'standard'       => translate('messages.standard'),
        'express'        => translate('messages.express'),
        'slightly_delay' => translate('messages.Slightly delay'),
    ];
    $deliveryTypeSuffix = ($isExpressDelivery || $isSlightlyDelay)
        ? ' (' . ($deliveryTypeLabels[$deliveryType] ?? '') . ')'
        : '';

    if ($tax_included == 1) {
        $tax_amount = 0;
    }

    // Same fields and gating as PlaceNewOrderTrait::makeOrderDetails() (app/Traits/Order/PlaceNewOrderTrait.php:380-389),
    // so a POS order charges exactly what a customer order charges for the same store. POS has no
    // packaging opt-in step of its own -- unlike the customer app, which requires
    // extra_packaging_amount > 0 on the request -- so it applies automatically whenever the store
    // has packaging active, matching stackfood's unconditional-when-enabled behaviour.
    $pos_additional_charge_settings = \App\CentralLogics\Helpers::get_business_settings_many([
        'additional_charge_status',
        'additional_charge',
        'additional_charge_name',
        'extra_packaging_data',
    ]);
    $additional_charge_status = $pos_additional_charge_settings['additional_charge_status'] ?? null;
    $additional_charge_name   = $pos_additional_charge_settings['additional_charge_name'] ?: translate('messages.Additional Charge');
    $additional_charge        = $additional_charge_status == 1 ? (float) ($pos_additional_charge_settings['additional_charge'] ?? 0) : 0.0;

    $pos_extra_packaging_data = json_decode($pos_additional_charge_settings['extra_packaging_data'] ?? '', true) ?: [];
    $extra_packaging_amount   = (
        !empty($pos_extra_packaging_data)
        && $store
        && ($pos_extra_packaging_data[$store->module->module_type ?? ''] ?? null) == '1'
        && ($store->storeConfig?->extra_packaging_status == '1')
    ) ? (float) ($store->storeConfig?->extra_packaging_amount ?? 0) : 0.0;

    $cart_has_address = session()->has('address') && is_array(session('address')) && isset(session('address')['delivery_fee']);
    $has_items        = count($cart_lines) > 0;
?>

{{-- Read back by the delivery-type selector and by the ticket header. --}}
<input type="hidden" id="cart_delivery_fee" data-value="{{ $delivery_fee_for_selector }}" value="{{ $delivery_fee_for_selector }}">
<input type="hidden" id="cart_order_type" data-value="{{ $pos_order_type }}" value="{{ $pos_order_type }}">
<input type="hidden" id="cart_has_address" data-value="{{ $cart_has_address ? 1 : 0 }}" value="{{ $cart_has_address ? 1 : 0 }}">
<input type="hidden" id="cart_item_count" value="{{ $cart_quantity }}">

@if ($has_items)
    <div class="pos-items">
        <div class="pos-items-head">
            <h3 class="pos-block-title">
                <i class="tio-shopping-cart-outlined"></i>{{ translate('messages.Order items') }}
                <span class="pos-count-pill">{{ $cart_quantity }}</span>
            </h3>
        </div>

    <div class="pos-cart-list">
        @foreach ($cart_lines as $key => $line)
            @php($cartItem = $line['item'])
            <div class="pos-cart-row">
                <img class="pos-cart-thumb onerror-image quick-View-Cart-Item"
                     data-product-id="{{ $cartItem['id'] }}" data-item-key="{{ $key }}"
                     src="{{ $cartItem['image_full_url'] }}"
                     data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.png') }}"
                     width="40" height="40" alt="{{ $cartItem['name'] }}">

                <div class="pos-cart-info quick-View-Cart-Item"
                     data-product-id="{{ $cartItem['id'] }}" data-item-key="{{ $key }}"
                     title="{{ $cartItem['name'] }}">
                    <span class="pos-cart-name">{{ $cartItem['name'] }}</span>
                    @if (filled($cartItem['variant']))
                        <span class="pos-cart-variant">{{ $cartItem['variant'] }}</span>
                    @endif
                    <span class="pos-cart-unit">
                        {{ \App\CentralLogics\Helpers::format_currency($cartItem['price']) }}
                        {{ translate('messages.each') }}
                    </span>
                </div>

                <div class="pos-qty">
                    <button type="button" class="pos-qty-btn pos-qty-step" data-step="-1"
                            aria-label="{{ translate('messages.decrease') }}"
                            {{ $cartItem['quantity'] <= 1 ? 'disabled' : '' }}>
                        <i class="tio-remove"></i>
                    </button>
                    <input type="number" class="pos-qty-input form-control update-Quantity"
                           data-key="{{ $key }}" data-oldvalue="{{ $cartItem['quantity'] }}"
                           value="{{ $cartItem['quantity'] }}" min="1" max="{{ $line['max'] }}"
                           aria-label="{{ translate('messages.quantity') }}">
                    <button type="button" class="pos-qty-btn pos-qty-step" data-step="1"
                            aria-label="{{ translate('messages.increase') }}"
                            {{ $cartItem['quantity'] >= $line['max'] ? 'disabled' : '' }}>
                        <i class="tio-add"></i>
                    </button>
                </div>

                <div class="pos-cart-amount">
                    <span>{{ \App\CentralLogics\Helpers::format_currency($line['subtotal']) }}</span>
                    <a href="javascript:" data-product-id="{{ $key }}"
                       class="pos-cart-remove remove-From-Cart"
                       aria-label="{{ translate('messages.Delete') }}">
                        <i class="tio-delete-outlined"></i>
                    </a>
                </div>
            </div>
        @endforeach
    </div>
    </div>
@else
    <div class="pos-empty pos-empty--compact">
        <span class="pos-empty-icon"><i class="tio-shopping-cart-outlined"></i></span>
        <h3 class="pos-empty-title">{{ translate('messages.Cart is empty') }}</h3>
        <p class="pos-empty-text">{{ translate('messages.Pick products from the left to start this order.') }}</p>
    </div>
@endif

<div class="pos-summary">
    @if (Config::get('module.current_module_type') == 'food')
        <div class="pos-summary-row">
            <span>{{ translate('Addon') }}</span>
            <span>{{ \App\CentralLogics\Helpers::format_currency($addon_price) }}</span>
        </div>
    @endif

    <div class="pos-summary-row">
        <span>
            {{ translate('messages.subtotal') }}
            @if ($tax_included == 1)
                <small>({{ translate('TAX included') }})</small>
            @endif
        </span>
        <span>{{ \App\CentralLogics\Helpers::format_currency($subtotal + $addon_price) }}</span>
    </div>

    <div class="pos-summary-row pos-summary-row--credit">
        <span>{{ translate('Discount') }}</span>
        <span>- {{ \App\CentralLogics\Helpers::format_currency(round($discount_on_product, 2)) }}</span>
    </div>

    @if ($pos_pro_discount > 0)
        <div class="pos-summary-row pos-summary-row--credit">
            <span>{{ translate('messages.Pro discount') }}</span>
            <span>- {{ \App\CentralLogics\Helpers::format_currency(round($pos_pro_discount, 2)) }}</span>
        </div>
    @endif

    @if ($pos_pro_delivery_savings > 0)
        <div class="pos-summary-row pos-summary-row--credit">
            <span>{{ translate('messages.Pro delivery discount') }}</span>
            <span>- {{ \App\CentralLogics\Helpers::format_currency(round($pos_pro_delivery_savings, 2)) }}</span>
        </div>
    @endif

    @if ($tax_included != 1)
        <div class="pos-summary-row">
            <span>{{ translate('messages.tax') }}</span>
            <span>{{ \App\CentralLogics\Helpers::format_currency(round($tax_amount, 2)) }}</span>
        </div>
    @endif

    @if ($additional_charge_status)
        <div class="pos-summary-row">
            <span>{{ $additional_charge_name }}</span>
            <span>{{ \App\CentralLogics\Helpers::format_currency(round($additional_charge, 2)) }}</span>
        </div>
    @endif

    @if ($extra_packaging_amount > 0)
        <div class="pos-summary-row">
            <span>{{ translate('messages.Extra Packaging Amount') }}</span>
            <span>{{ \App\CentralLogics\Helpers::format_currency(round($extra_packaging_amount, 2)) }}</span>
        </div>
    @endif

    <div class="pos-summary-row">
        <span>
            {{ translate('Delivery fee') }}{{ $deliveryTypeSuffix }}
            @if ($pos_is_free_delivery && $pos_order_type === 'delivery')
                <span class="pos-summary-tag">{{ translate('Free delivery') }}</span>
            @endif
            @if (!$pos_is_free_delivery && filled($pos_surge_note))
                <i class="tio-info-outined text-warning" data-toggle="tooltip"
                   title="{{ $pos_surge_note }}"></i>
            @endif
        </span>
        <span id="delivery_price">{{ \App\CentralLogics\Helpers::format_currency($adjustedDeliveryFee) }}</span>
    </div>

    <div class="pos-summary-total">
        <span>{{ translate('messages.Total') }}</span>
        <span>{{ \App\CentralLogics\Helpers::format_currency(round($total + $tax_amount + $additional_charge + $extra_packaging_amount, 2)) }}</span>
    </div>
</div>

<form action="{{ route('admin.pos.order') }}?store_id={{ request('store_id') }}" id="order_place" method="post">
    @csrf
    <input type="hidden" name="user_id" id="customer_id">

    <div class="pos-payment">
        <h4 class="pos-block-title">
            <i class="tio-wallet-outlined"></i>{{ translate('Paid by') }}
        </h4>
        <ul class="pos-payment-options">
            @php($cod = \App\CentralLogics\Helpers::get_business_settings('cash_on_delivery'))
            @if ($cod['status'])
                <li>
                    <label>
                        <input type="radio" name="type" value="cash" hidden checked>
                        <span><i class="tio-money"></i>{{ translate('Cash on delivery') }}</span>
                    </label>
                </li>
            @endif
            @php($wallet = \App\CentralLogics\Helpers::get_business_settings('wallet_status'))
            @if ($wallet)
                <li>
                    <label>
                        <input type="radio" name="type" value="wallet" hidden {{ $cod['status'] ? '' : 'checked' }}>
                        <span><i class="tio-wallet"></i>{{ translate('Wallet') }}</span>
                    </label>
                </li>
            @endif
        </ul>
    </div>

</form>

{{-- The Clear cart / Place order bar is NOT rendered here. It lives outside
     the ticket's scroll region (see index.blade.php) so it stays pinned to the
     bottom of the panel no matter how long the cart gets; posSyncCounts()
     copies these two values into it after every refresh. --}}
<input type="hidden" id="cart_payable"
       value="{{ \App\CentralLogics\Helpers::format_currency(round($total + $tax_amount + $additional_charge + $extra_packaging_amount, 2)) }}">

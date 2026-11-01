<input type="hidden" id="cart_delivery_fee" data-value="{{ $delivery_fee_for_selector }}" value="{{ $delivery_fee_for_selector }}">
<input type="hidden" id="cart_order_type" data-value="{{ $pos_order_type }}" value="{{ $pos_order_type }}">
<input type="hidden" id="cart_has_address" data-value="{{ $cart_has_address ? 1 : 0 }}" value="{{ $cart_has_address ? 1 : 0 }}">
<input type="hidden" id="cart_item_count" value="{{ $cart_quantity }}">

@if (count($cart_rows))
    <div class="pos-items">
        <div class="pos-items-head">
            <h3 class="pos-block-title">
                <i class="tio-shopping-cart-outlined"></i>{{ translate('messages.Order items') }}
                <span class="pos-count-pill">{{ $cart_quantity }}</span>
            </h3>
        </div>

        <div class="pos-cart-list">
            @foreach ($cart_rows as ['key' => $key, 'item' => $cartItem, 'product_subtotal' => $product_subtotal, 'max' => $max])
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
                               value="{{ $cartItem['quantity'] }}" min="1" max="{{ $max }}"
                               aria-label="{{ translate('messages.quantity') }}">
                        <button type="button" class="pos-qty-btn pos-qty-step" data-step="1"
                                aria-label="{{ translate('messages.increase') }}"
                                {{ $cartItem['quantity'] >= $max ? 'disabled' : '' }}>
                            <i class="tio-add"></i>
                        </button>
                    </div>

                    <div class="pos-cart-amount">
                        <span>{{ \App\CentralLogics\Helpers::format_currency($product_subtotal) }}</span>
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
    @if ($module_type == 'food')
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

    <div class="pos-summary-row">
        <span>
            {{ translate('Delivery fee') }}{{ $deliveryTypeSuffix }}
            @if ($pos_pro_delivery_savings > 0)
                {{-- Same label and amount as the "Pro delivery discount" row below, rather than a
                     generic sentence describing the offer type — one number for this order,
                     stated once and repeated consistently everywhere it shows up. --}}
                <i class="tio-info-outined text-info" data-toggle="tooltip"
                   title="{{ translate('messages.Pro delivery discount') }}: - {{ \App\CentralLogics\Helpers::format_currency(round($pos_pro_delivery_savings, 2)) }}"></i>
            @endif
            @if (filled($pos_surge_note))
                <i class="tio-info-outined text-warning" data-toggle="tooltip"
                   title="{{ $pos_surge_note }}"></i>
            @endif
        </span>
        <span id="delivery_price">{{ \App\CentralLogics\Helpers::format_currency($adjustedDeliveryFee) }}</span>
    </div>

    @if ($pos_pro_delivery_savings > 0)
        <div class="pos-summary-row pos-summary-row--credit">
            <span>{{ translate('messages.Pro delivery discount') }}</span>
            <span>- {{ \App\CentralLogics\Helpers::format_currency(round($pos_pro_delivery_savings, 2)) }}</span>
        </div>
    @endif

    <div class="pos-summary-row pos-summary-row--credit">
        <span>
            {{ translate('Extra discount') }}
            <button type="button" class="pos-icon-btn pos-icon-btn--inline" data-toggle="modal"
                    data-target="#add-discount" aria-label="{{ translate('messages.Update discount') }}">
                <i class="tio-edit"></i>
            </button>
        </span>
        <span>- {{ \App\CentralLogics\Helpers::format_currency(round($extra_discount_amount, 2)) }}</span>
    </div>

    @if ($tax_included != 1)
        <div class="pos-summary-row">
            <span>{{ translate('messages.tax') }}</span>
            <span>{{ \App\CentralLogics\Helpers::format_currency(round($tax_amount, 2)) }}</span>
        </div>
    @endif

    <div class="pos-summary-total">
        <span>{{ translate('messages.Total') }}</span>
        <span>{{ \App\CentralLogics\Helpers::format_currency(round($total + $tax_amount, 2)) }}</span>
    </div>

    @if (!$add)
        <div class="pos-summary-row">
            <span>
                {{ translate('Paid amount') }}
                <button type="button" class="pos-icon-btn pos-icon-btn--inline" data-toggle="modal"
                        data-target="#insertPayableAmount" aria-label="{{ translate('messages.Payment') }}">
                    <i class="tio-edit"></i>
                </button>
            </span>
            <span>{{ \App\CentralLogics\Helpers::format_currency($paid) }}</span>
        </div>
        <div class="pos-summary-row">
            <span>{{ translate('Change amount') }}</span>
            <span>{{ \App\CentralLogics\Helpers::format_currency($change) }}</span>
        </div>
    @endif
</div>

<form action="{{ route('vendor.pos.order') }}" id="order_place" method="post">
    @csrf
    <input type="hidden" name="user_id" id="customer_id">

    <div class="pos-payment">
        <h4 class="pos-block-title">
            <i class="tio-wallet-outlined"></i>{{ translate($add ? 'messages.Payment method' : 'Paid by') }}
        </h4>
        <ul class="pos-payment-options">
            @if ($add)
                @if ($cod['status'])
                    <li>
                        <label>
                            <input type="radio" name="type" value="cash_on_delivery" hidden checked>
                            <span><i class="tio-money"></i>{{ translate('Cash on delivery') }}</span>
                        </label>
                    </li>
                @endif
            @else
                <li id="payment_cash">
                    <label>
                        <input type="radio" name="type" value="cash" hidden checked>
                        <span><i class="tio-money"></i>{{ translate('messages.Cash') }}</span>
                    </label>
                </li>
                <li id="payment_card">
                    <label>
                        <input type="radio" name="type" value="card" hidden>
                        <span><i class="tio-credit-card"></i>{{ translate('messages.Card') }}</span>
                    </label>
                </li>
            @endif
        </ul>
    </div>

    @if (!$add)
        <input type="hidden" name="amount" value="{{ $paid }}">
    @endif
</form>

<input type="hidden" id="cart_payable"
       value="{{ \App\CentralLogics\Helpers::format_currency(round($total + $tax_amount, 2)) }}">
<input type="hidden" id="cart_total_amount" value="{{ round($total + $tax_amount, 2) }}">
<input type="hidden" id="cart_paid_amount" value="{{ $paid }}">
<input type="hidden" id="cart_extra_discount"
       value="{{ $extra_discount_type == 'percent' ? $extra_discount : $extra_discount_amount }}">
<input type="hidden" id="cart_extra_discount_type" value="{{ $extra_discount_type }}">

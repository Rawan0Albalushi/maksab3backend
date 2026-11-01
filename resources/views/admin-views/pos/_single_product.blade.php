@php
    /* The cart line key is read straight off the session cart rather than
       derived from the position of the product inside `cart_product_ids`.
       Those two only line up while nothing has been removed: `$cart->forget()`
       leaves the cart keys sparse (0, 2, 3) while `cart_product_ids` reindexes,
       so the positional lookup handed the quick-view a key that no longer
       exists and the request 500'd. */
    $pos_cart      = session()->get('cart');
    $pos_cart_key  = null;
    $pos_cart_qty  = 0;

    if (is_iterable($pos_cart)) {
        foreach ($pos_cart as $line_key => $line) {
            if (is_array($line) && isset($line['id']) && $line['id'] == $product->id) {
                $pos_cart_key ??= $line_key;
                $pos_cart_qty += (int) ($line['quantity'] ?? 0);
            }
        }
    }

    $in_cart = $pos_cart_key !== null;

    $discount      = \App\CentralLogics\Helpers::product_discount_calculate($product, $product['price'], $store_data);
    $final_price   = $product['price'] - $discount['discount_amount'];
    $has_discount  = $discount['discount_amount'] > 0;
    $off_percent   = ($discount['original_discount_type'] ?? null) === 'percent'
        ? (float) ($discount['discount_percentage'] ?? 0)
        : 0;

    /* Stock is only meaningful outside the food module — get_stocks() returns
       null for food, and food carts are stored with a null stock_quantity.
       Read from config, not $product->module: the listing query does not eager
       load the relation and lazy loading is disabled. The whole screen is
       scoped to one module anyway. */
    $tracks_stock = config('module.current_module_type') !== 'food';
    $stock        = $tracks_stock ? (int) ($product->stock ?? 0) : null;
@endphp

<article
    class="pos-card {{ $in_cart ? 'is-in-cart quick-View-Cart-Item' : 'quick-View' }}"
    role="button" tabindex="0"
    aria-label="{{ $product['name'] }}"
    data-id="{{ $product->id }}"
    data-product-id="{{ $product->id }}"
    data-item-key="{{ $pos_cart_key }}"
    data-item-count="{{ $pos_cart_qty }}">

    <div class="pos-card-media">
        <img class="onerror-image"
             src="{{ $product['image_full_url'] ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"
             data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
             loading="lazy" width="160" height="160" alt="{{ $product['name'] }}">

        @if ($off_percent > 0)
            <span class="pos-card-badge">-{{ rtrim(rtrim(number_format($off_percent, 2, '.', ''), '0'), '.') }}%</span>
        @endif

        @if (!is_null($stock))
            @if ($stock <= 0)
                <span class="pos-card-badge pos-card-badge--stock pos-card-badge--stock-out">
                    {{ translate('Out of stock') }}
                </span>
            @elseif ($stock <= 5)
                <span class="pos-card-badge pos-card-badge--stock pos-card-badge--stock-low">
                    {{ $stock }} {{ translate('messages.left') }}
                </span>
            @else
                <span class="pos-card-badge pos-card-badge--stock">{{ $stock }}</span>
            @endif
        @endif

        <span class="pos-card-qty">{{ $pos_cart_qty }}</span>
    </div>

    <div class="pos-card-body">
        <h3 class="pos-card-name">{{ $product['name'] }}</h3>
        <p class="pos-card-price">
            <span class="pos-card-price-now">{{ \App\CentralLogics\Helpers::format_currency($final_price) }}</span>
            @if ($has_discount)
                <span class="pos-card-price-was">{{ \App\CentralLogics\Helpers::format_currency($product['price']) }}</span>
            @endif
        </p>
    </div>

    <span class="pos-card-action" aria-hidden="true">
        <i class="tio-{{ $in_cart ? 'edit' : 'add' }}"></i>
    </span>
</article>

@php
    $has_search = filled(request('search'));
@endphp

<input type="hidden" id="pos_product_total" value="{{ $products->total() }}">

@if (!$store)
    <div class="pos-empty">
        <span class="pos-empty-icon"><i class="tio-shop"></i></span>
        <h3 class="pos-empty-title">{{ translate('messages.Please select a store first') }}</h3>
        <p class="pos-empty-text">
            {{ translate('messages.Pick a store above to load its products and start a new order.') }}
        </p>
    </div>
@elseif (count($products) === 0)
    <div class="pos-empty">
        <span class="pos-empty-icon"><i class="tio-search"></i></span>
        <h3 class="pos-empty-title">{{ translate('messages.No products on pos search') }}</h3>
        <p class="pos-empty-text">
            @if ($has_search)
                {{ translate('messages.Nothing matched this search. Try a different keyword or clear the filters.') }}
            @else
                {{ translate('messages.This store has no products available for the selected category right now.') }}
            @endif
        </p>
        @if ($has_search)
            <button type="button" class="btn btn-sm btn--primary pos-reset-filters">
                <i class="tio-clear-circle-outlined"></i> {{ translate('messages.Clear filters') }}
            </button>
        @endif
    </div>
@else
    <div class="pos-grid" id="single-list">
        @foreach ($products as $product)
            @include('admin-views.pos._single_product', ['product' => $product, 'store_data' => $store])
        @endforeach
    </div>

    <div class="pos-scroll-more" id="pos-scroll-more" data-has-more="{{ $products->hasMorePages() ? 1 : 0 }}">
        <span class="pos-scroll-spinner" aria-hidden="true"></span>
    </div>
@endif

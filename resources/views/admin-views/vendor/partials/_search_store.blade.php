@forelse ($stores as $store)
    <button type="button" class="rcs-result" onclick="selected_stores({{ $store->id }})">
        <img class="rcs-result__img onerror-image" alt=""
             data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"
             src="{{ $store['logo_full_url'] ?? asset('public/assets/admin/img/160x160/img1.jpg') }}">
        <span class="rcs-result__body">
            <span class="rcs-result__name" title="{{ $store['name'] }}">{{ $store['name'] }}</span>
            <span class="rcs-result__meta">
                @if($store->ratings['total'])
                    <span><i class="tio-star"></i> {{ number_format($store->ratings['rating'], 1) }} ({{ $store->ratings['total'] }})</span>
                @else
                    <span>{{ translate('messages.Not rated yet') }}</span>
                @endif
                <span class="rcs-result__dot"></span>
                <span>{{ $store->items_count }} {{ translate('messages.Items') }}</span>
                <span class="rcs-result__dot"></span>
                <span>{{ $store->orders_count }} {{ translate('messages.Orders') }}</span>
            </span>
            @if($store->address)
                <span class="rcs-result__address" title="{{ $store->address }}">{{ Str::limit($store->address, 42, '...') }}</span>
            @endif
        </span>
        <span class="rcs-result__add"><i class="tio-add"></i></span>
    </button>
@empty
    <p class="rcs-results__status">{{ translate('No store matches this search') }}</p>
@endforelse

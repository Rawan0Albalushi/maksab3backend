@foreach ($stores as $store)
    <span class="rcs-chip">
        <img class="rcs-chip__img onerror-image" alt=""
             data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"
             src="{{ $store['logo_full_url'] ?? asset('public/assets/admin/img/160x160/img1.jpg') }}">
        <span class="rcs-chip__name" title="{{ $store['name'] }}">{{ $store['name'] }}</span>
        @if($store->ratings['total'])
            <span class="rcs-chip__rating"><i class="tio-star"></i> {{ number_format($store->ratings['rating'], 1) }}</span>
        @endif
        <button type="button" class="rcs-chip__remove" onclick="selected_stores({{ $store->id }}, true)"
                aria-label="{{ translate('Remove') }}" title="{{ translate('Remove') }}"><i class="tio-clear"></i></button>
    </span>
@endforeach

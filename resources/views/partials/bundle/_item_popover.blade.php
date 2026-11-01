@use('App\CentralLogics\Helpers')

<div class="bundle-popover">
    <div class="bundle-popover__head">
        {{ $items->count() }} {{ translate('messages.Items') }}
    </div>

    @foreach ($items as $item)
        <div class="bundle-item-row d-flex align-items-center gap-3">
            <img class="bundle-item-row__thumb onerror-image"
                data-onerror-image="{{ asset('public/assets/admin/img/100x100/1.png') }}"
                src="{{ $item->item_image_full_url }}" alt="">
            <div class="flex-grow-1" style="min-width:0">
                <strong class="d-block bundle-item-row__name">{{ $item->item_name }}</strong>
                @if ($item->variation_label)
                    <span class="d-block bundle-item-row__meta">{{ $item->variation_label }}</span>
                @endif
                <span class="d-block bundle-item-row__price">{{ Helpers::format_currency($item->unit_price) }}</span>
            </div>
        </div>
    @endforeach
</div>

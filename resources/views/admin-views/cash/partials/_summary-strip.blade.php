{{-- Summary strip for a Cash Operations screen.

     @include('admin-views.cash.partials._summary-strip', ['tiles' => [
         ['icon' => 'tio-money',   'tone' => 'in', 'value' => $total, 'label' => translate('messages.Collected all time')],
         ['icon' => 'tio-shop-outlined',        'value' => $stores, 'label' => translate('messages.From stores')],
     ]])

     A flat list rather than fixed slots: the four screens measure genuinely
     different things, and the collect-cash strip also drops its rider tile
     when RideShare is off. `tone` is optional — `in` / `out` / `info` / `off`
     tint the icon badge; omitted leaves it in the panel's accent colour.

     Every tile's numbers come from one grouped query in the controller, and
     the strip describes the whole ledger — it deliberately ignores the search
     box, which the count badge on the table card tracks.

     Styles: `cash.css` §1. --}}

<div class="csh-stats">
    @foreach($tiles as $tile)
        <div class="csh-stat {{ isset($tile['tone']) ? 'csh-stat--'.$tile['tone'] : '' }}">
            <span class="csh-stat__icon"><i class="{{ $tile['icon'] }}"></i></span>
            <span class="csh-stat__text">
                <span class="csh-stat__value">{{ $tile['value'] }}</span>
                <span class="csh-stat__label">{{ $tile['label'] }}</span>
            </span>
        </div>
    @endforeach
</div>

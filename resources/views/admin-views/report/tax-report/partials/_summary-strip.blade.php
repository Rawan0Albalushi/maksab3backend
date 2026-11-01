{{-- Summary strip for a tax report: the totals the query returned.

     @include('admin-views.report.tax-report.partials._summary-strip', ['tiles' => [
         ['icon' => 'tio-receipt-outlined', 'value' => $totalOrders, 'label' => translate('messages.Total orders')],
         ['icon' => 'tio-money', 'tone' => 'income', 'value' => $amount, 'label' => translate('messages.Total order amount')],
     ]])

     A flat list rather than fixed slots: the admin report measures income and
     tax, the vendor and parcel reports also count orders. `tone` is optional —
     `income` / `tax` / `info` tint the icon badge; omitted leaves it in the
     panel's accent colour.

     This replaces three hand-rolled `bg--secondary` boxes per page, each with
     its own PNG. Styles: `tax.css` §1. --}}

<div class="txr-stats">
    @foreach($tiles as $tile)
        <div class="txr-stat {{ isset($tile['tone']) ? 'txr-stat--'.$tile['tone'] : '' }}">
            <span class="txr-stat__icon"><i class="{{ $tile['icon'] }}"></i></span>
            <span class="txr-stat__text">
                <span class="txr-stat__value">{{ $tile['value'] }}</span>
                <span class="txr-stat__label">{{ $tile['label'] }}</span>
            </span>
        </div>
    @endforeach
</div>

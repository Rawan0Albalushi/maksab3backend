{{-- The weight band and size class a parcel was charged by, as one line under its category.

     A partial rather than four copies: the customer invoice, the storefront invoice and two
     email templates all render the identical block beside the category name, and the
     `partials.pro-delivery-discount-row` include sitting next to each of them is the same
     pattern. Four copies would be four places to update when the separator or the wording moves.

     Inline styles, not classes — two of the four callers are email templates, and mail clients
     do not load the stylesheet.

     `band_label` / `size_label` carry the configured unit. Never print the raw numbers: a band
     stored as `1 - 2` is kilograms or pounds depending on the setting, and the stored value does
     not change when the setting does.

     Renders nothing at all when the order carries neither — a non-parcel order, a parcel placed
     before the tiers were recorded, or one in a zone that prices by neither. --}}
@if ($order->weight || $order->dimension)
    <div style="font-size: 12px; color: #777777; margin-top: 3px; line-height: 1.5;">
        @if ($order->weight)
            {{ translate('messages.Weight') }}: {{ $order->weight->band_label }}
        @endif
        @if ($order->weight && $order->dimension)
            &nbsp;&middot;&nbsp;
        @endif
        @if ($order->dimension)
            {{ translate('messages.Dimension') }}: {{ $order->dimension->size_label }}
        @endif
    </div>
@endif

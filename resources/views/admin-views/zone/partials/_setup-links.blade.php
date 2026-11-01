{{-- The shortcuts to whatever a zone is still missing. Shown in the row's warning popover and in
     the Connect Module drawer, so they live in one place. Both carry the zone, so the setup form
     opens already pointed at it rather than making the admin find it in a dropdown. --}}
@unless ($hasRule)
    <a href="{{ route('admin.business-settings.zone.delivery-rule.create', ['zone_id' => $zoneId]) }}"
        class="font-semibold text-info text-underline d-block">
        {{ translate('Add delivery charge setup') }}
    </a>
@endunless
@unless ($hasEta)
    <a href="{{ route('admin.business-settings.zone.eta-configuration.create', ['zone_id' => $zoneId]) }}"
        class="font-semibold text-info text-underline d-block">
        {{ translate('Add ETA configuration') }}
    </a>
@endunless

@if (($store?->verified_seller ?? 0) == 1)
    <i class="tio-verified text-success" data-toggle="tooltip" data-placement="top"
        title="{{ translate('verified') }} {{ translate(in_array($store?->module_type ?? null, ['rental', 'service']) ? 'Provider' : 'Store') }}"></i>
@endif

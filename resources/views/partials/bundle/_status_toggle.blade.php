<label class="toggle-switch toggle-switch-sm mb-0" for="{{ $idPrefix }}{{ $bundle->id }}">
    <input type="checkbox" class="toggle-switch-input bundle-status-toggle"
        data-form="{{ $idPrefix }}Form{{ $bundle->id }}"
        data-checked="{{ $bundle->status ? 'true' : 'false' }}"
        data-title="{{ $bundle->status
            ? translate('Do you want to turn off this bundle?')
            : translate('messages.Do You Want To Turn On This Bundle?') }}"
        data-message="{{ $bundle->status
            ? translate("messages.Customers will no longer see this bundle, and anyone who already added it to their cart won't be able to place an order with it until they remove it.")
            : translate('messages.Customers will see this bundle again while its schedule is running.') }}"
        id="{{ $idPrefix }}{{ $bundle->id }}" {{ $bundle->status ? 'checked' : '' }}>
    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
</label>
<form class="d-none" method="post" id="{{ $idPrefix }}Form{{ $bundle->id }}"
    action="{{ route($routePrefix.'.status', [$bundle->id, $bundle->status ? 0 : 1]) }}">
    @csrf @method('patch')
</form>

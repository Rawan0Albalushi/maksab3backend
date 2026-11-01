<div>
    @php($deliverymanTemplateLabel = \App\CentralLogics\Helpers::formatDeliverymanText(translate('Delivery Man Mail Templates'), null, true))
    <select id="mail-route-selector" class="custom-select w-auto min-width-170px">
        <option value="admin" {{ Request::is('admin/business-settings/email-setup/admin*') ? 'selected' : '' }}><a href="https://support.6amtech.com/">{{ translate('Admin Mail Templates') }}</a></option>
        <option value="store" {{ Request::is('admin/business-settings/email-setup/store*') ? 'selected' : '' }}><a href="https://support.6amtech.com/">{{ translate('Store Mail Templates') }}</a></option>
        <option value="dm" {{ Request::is('admin/business-settings/email-setup/dm*') ? 'selected' : '' }}><a href="https://support.6amtech.com/">{{ $deliverymanTemplateLabel }}</a></option>
        <option value="user" {{ Request::is('admin/business-settings/email-setup/user*') ? 'selected' : '' }}><a href="https://support.6amtech.com/">{{ translate('Customer Mail Templates') }}</a></option>
    </select>
    @empty($moveSeeHowToTitle)
    <div class="d-flex justify-content-end mt-2">
        <div class="text--primary-2 py-1 d-flex flex-wrap align-items-center" type="button"   id="see-how-it-works"  >
            <strong class="mr-2">{{translate('See how it works')}}</strong>
            <div>
                <i class="tio-info-outined"></i>
            </div>
        </div>
    </div>
    @endempty
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center tabs-slide-wrap position-relative mb-2 __gap-12px">
    <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
        <ul class="nav nav-tabs tabs-inner border-0 nav--tabs nav--pills">
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/admin/forgot-password') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['admin','forgot-password']) }}">
                    {{translate('Forgot password')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/admin/store-registration') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['admin','store-registration']) }}">
                    {{translate('New store registration')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/admin/dm-registration') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['admin','dm-registration']) }}">
                    {{ \App\CentralLogics\Helpers::formatDeliverymanText(translate('New Deliveryman Registration'), null, true) }}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/admin/withdraw-request') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['admin','withdraw-request']) }}">
                    {{translate('Withdraw request')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/admin/dm-withdraw-request') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['admin','dm-withdraw-request']) }}">
                    {{ \App\CentralLogics\Helpers::formatDeliverymanText(translate('Deliveryman withdraw request'), null, true) }}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/admin/campaign-request') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['admin','campaign-request']) }}">
                    {{translate('Campaign Join Request')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/admin/refund-request') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['admin','refund-request']) }}">
                    {{translate('Refund request')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/admin/new-advertisement') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['admin','new-advertisement']) }}">
                    {{translate('New advertisement request')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/admin/update-advertisement') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['admin','update-advertisement']) }}">
                    {{translate('Advertisement update request')}}
                </a>
            </li>
        </ul>
    </div>
    <div class="arrow-area">
        <div class="button-prev align-items-center">
            <button type="button"
                class="btn btn-click-prev mr-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                <i class="tio-chevron-left fs-24"></i>
            </button>
        </div>
        <div class="button-next align-items-center">
            <button type="button"
                class="btn btn-click-next ml-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                <i class="tio-chevron-right fs-24"></i>
            </button>
        </div>
    </div>
</div>

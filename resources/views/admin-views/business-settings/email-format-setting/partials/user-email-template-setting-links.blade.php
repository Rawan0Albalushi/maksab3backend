<div class="d-flex flex-wrap justify-content-between align-items-center tabs-slide-wrap position-relative mb-2 __gap-12px">
    <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
        <ul class="nav nav-tabs tabs-inner border-0 nav--tabs nav--pills">
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/user/registration') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['user','registration']) }}">
                    {{translate('New Customer Registration')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/user/pos-registration') ? 'active' : '' }}"
                   href="{{ route('admin.business-settings.email-setup', ['user','pos-registration']) }}">
                    {{translate('POS New Customer Registration')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/user/registration-otp') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['user','registration-otp']) }}">
                    {{translate('Registration OTP')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/user/forgot-password') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['user','forgot-password']) }}">
                    {{translate('Forgot password')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/user/order-verification') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['user','order-verification']) }}">
                    {{translate('Delivery Verification?')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/user/new-order') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['user','new-order']) }}">{{translate('Order Placement')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/user/refund-order') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['user','refund-order']) }}">{{translate('messages.Refund order')}}</a>
            </li>

            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/user/refund-request-deny') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['user','refund-request-deny']) }}">
                    {{translate('Refund Request Rejected')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/user/add-fund') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['user','add-fund']) }}">
                    {{translate('Fund added')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/user/offline-payment-approve') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['user','offline-payment-approve']) }}">
                    {{translate('Offline Payment Approve')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/user/offline-payment-deny') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['user','offline-payment-deny']) }}">
                    {{translate('Offline Payment Deny')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/user/suspend') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['user','suspend']) }}">
                    {{translate('Account Suspension')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/user/unsuspend') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['user','unsuspend']) }}">
                    {{translate('Account Unsuspension')}}
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

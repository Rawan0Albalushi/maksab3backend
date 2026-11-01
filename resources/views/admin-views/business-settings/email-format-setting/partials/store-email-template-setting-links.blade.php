<div class="d-flex flex-wrap justify-content-between align-items-center tabs-slide-wrap position-relative mb-2 __gap-12px">
    <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
        <ul class="nav nav-tabs tabs-inner border-0 nav--tabs nav--pills">
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/registration') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','registration']) }}">
                    {{translate('New store registration')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/approve') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','approve']) }}">
                    {{translate('New Store Approval')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/deny') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','deny']) }}">
                    {{translate('New Store Rejection')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/suspend') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','suspend']) }}">
                    {{translate('Account Suspend')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/unsuspend') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','unsuspend']) }}">
                    {{translate('Account Unsuspend')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/withdraw-approve') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','withdraw-approve']) }}">
                    {{translate('Withdraw Approval')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/withdraw-deny') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','withdraw-deny']) }}">
                    {{translate('Withdraw Rejection')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/campaign-request') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','campaign-request']) }}">
                    {{translate('Campaign Join Request')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/campaign-approve') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','campaign-approve']) }}">
                    {{translate('Campaign Join Approval')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/campaign-deny') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','campaign-deny']) }}">
                    {{translate('Campaign Join Rejection')}}
                </a>
            </li>

            @if (\App\CentralLogics\Helpers::get_business_settings('product_approval'))
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/product-approved') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','product-approved']) }}">
                    {{translate('Product approved')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/product-deny') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','product-deny']) }}">
                    {{translate('Product Rejection')}}
                </a>
            </li>
            @endif

            @if (\App\CentralLogics\Helpers::subscription_check())
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/subscription-successful') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','subscription-successful']) }}">
                    {{translate('Subscription successful')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/subscription-renew') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','subscription-renew']) }}">
                    {{translate('Subscription Renew')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/subscription-shift') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','subscription-shift']) }}">
                    {{translate('Subscription Shift')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/subscription-cancel') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','subscription-cancel']) }}">
                    {{translate('Subscription Cancel')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/subscription-plan_upadte') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','subscription-plan_upadte']) }}">
                    {{translate('Subscription Plan Upadte')}}
                </a>
            </li>
            @endif

            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/advertisement-create') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','advertisement-create']) }}">
                    {{translate('Advertisement Create By Admin')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/advertisement-approved') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','advertisement-approved']) }}">
                    {{translate('Advertisement Approval')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/advertisement-deny') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','advertisement-deny']) }}">
                    {{translate('Advertisement denied')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/advertisement-resume') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','advertisement-resume']) }}">
                    {{translate('Advertisement Resume')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/email-setup/store/advertisement-pause') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.email-setup', ['store','advertisement-pause']) }}">
                    {{translate('Advertisement Pause')}}
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

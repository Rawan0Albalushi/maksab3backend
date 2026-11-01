<div class="tabs-slide-wrap position-relative">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 mt-3 __gap-12px">
        <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
            <ul class="nav nav-tabs tabs-inner border-0 nav--tabs nav--pills">
                <li class="nav-item">
                    <a class="nav-link  {{ Request::is('admin/business-settings/business-setup') ?'active':'' }}" href="{{ route('admin.business-settings.business-setup') }}"   aria-disabled="true">{{translate('Business information')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link  {{ Request::is('admin/business-settings/business-setup/payment') ?'active':'' }}" href="{{ route('admin.business-settings.business-setup',  ['tab' => 'payment']) }}"   aria-disabled="true">{{translate('Payment')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Request::is('admin/business-settings/business-setup/store') ?'active':'' }}" href="{{ route('admin.business-settings.business-setup',  ['tab' => 'store']) }}"  aria-disabled="true">{{translate('vendor')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Request::is('admin/business-settings/business-setup/order') ?'active':'' }}" href="{{ route('admin.business-settings.business-setup',  ['tab' => 'order']) }}"  aria-disabled="true">{{translate('Order')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Request::is('admin/business-settings/business-setup/refund-settings') ?'active':'' }}" href="{{ route('admin.business-settings.business-setup',  ['tab' => 'refund-settings']) }}"  aria-disabled="true">{{translate('Refund')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Request::is('admin/business-settings/business-setup/deliveryman') ?'active':'' }}" href="{{ route('admin.business-settings.business-setup',  ['tab' => 'deliveryman']) }}"  aria-disabled="true">{{translate('Deliveryman')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Request::is('admin/business-settings/business-setup/customer') ?'active':'' }}" href="{{ route('admin.business-settings.business-setup',  ['tab' => 'customer']) }}"  aria-disabled="true">{{translate('Customer')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Request::is('admin/business-settings/business-setup/priority') ?'active':'' }}" href="{{ route('admin.business-settings.business-setup',  ['tab' => 'priority']) }}"  aria-disabled="true">{{translate('Priority setup')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Request::is('admin/business-settings/business-setup/disbursement') ?'active':'' }}" href="{{ route('admin.business-settings.business-setup',  ['tab' => 'disbursement']) }}"  aria-disabled="true">{{translate('Disbursement')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Request::is('admin/business-settings/business-setup/automated-message') ?'active':'' }}" href="{{ route('admin.business-settings.business-setup',  ['tab' => 'automated-message']) }}"  aria-disabled="true">{{translate('Automated message')}}</a>
                </li>
            </ul>
             <div class="arrow-area">
                <div class="button-prev top-18 align-items-center">
                    <button type="button"
                        class="btn btn-click-prev mr-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                        <i class="tio-chevron-left fs-24"></i>
                    </button>
                </div>
                <div class="button-next top-18 align-items-center">
                    <button type="button"
                        class="btn btn-click-next ml-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                        <i class="tio-chevron-right fs-24"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

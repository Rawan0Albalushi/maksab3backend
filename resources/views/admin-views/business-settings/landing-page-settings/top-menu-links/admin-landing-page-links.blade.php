<div class="d-flex flex-wrap justify-content-between align-items-center tabs-slide-wrap position-relative mb-20 __gap-12px">
    <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
        <ul class="nav nav-tabs tabs-inner border-0 nav--tabs nav--pills">
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/admin-landing-page-settings/setup') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.admin-landing-page-settings', 'setup') }}">{{translate('messages.Setup')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/admin-landing-page-settings/fixed-data') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.admin-landing-page-settings', 'fixed-data') }}">{{translate('messages.fixed Data')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/admin-landing-page-settings/promotional-section*') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.admin-landing-page-settings', 'promotional-section') }}">{{translate('messages.Promotional section')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/admin-landing-page-settings/feature-list*') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.admin-landing-page-settings', 'feature-list') }}">{{translate('messages.Feature List')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/admin-landing-page-settings/earn-money') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.admin-landing-page-settings', 'earn-money') }}">{{translate('messages.Earn money')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/admin-landing-page-settings/why-choose-us*') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.admin-landing-page-settings', 'why-choose-us') }}">{{translate('messages.Why choose us')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/admin-landing-page-settings/available-zone*') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.admin-landing-page-settings', 'available-zone') }}">{{translate('messages.Available zone')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/admin-landing-page-settings/download-apps') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.admin-landing-page-settings', 'download-apps') }}">{{translate('Download apps')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/admin-landing-page-settings/testimonials*') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.admin-landing-page-settings', 'testimonials') }}">{{translate('messages.testimonials')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/admin-landing-page-settings/contact-us') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.admin-landing-page-settings', 'contact-us') }}">{{translate('messages.Contact us page')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/admin-landing-page-settings/background-color') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.admin-landing-page-settings', 'background-color') }}">{{translate('messages.Background colors')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/admin-landing-page-settings/meta-data') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.admin-landing-page-settings', 'meta-data') }}">{{translate('Meta data')}}</a>
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

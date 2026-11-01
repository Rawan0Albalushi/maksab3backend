<div class="d-flex flex-wrap justify-content-between align-items-center tabs-slide-wrap mb-20 __gap-12px">
    <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
        <ul class="nav nav-tabs tabs-inner border-0 nav--tabs nav--pills">
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/header') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'header') }}">{{translate('Hero Section')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/trust-section') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'trust-section') }}">{{translate('Trust Section')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/available-zone') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'available-zone') }}">{{translate('messages.Available zone')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/promotion-banner') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'promotion-banner') }}">{{translate('Promotional banners')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/download-user-app') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'download-user-app') }}">{{translate('User App Download')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/popular-clients') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'popular-clients') }}">{{translate('Popular Clients')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/download-seller-app') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'download-seller-app') }}">{{translate('Download the Seller App')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/download-deliveryman-app') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'download-deliveryman-app') }}">{{translate('Download the Deliveryman App')}}</a>
            </li>
            @if(addon_published_status('RideShare'))
                <li class="nav-item tabs-slide_items">
                    <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/download-rider-app') ? 'active' : '' }}"
                    href="{{ route('admin.business-settings.react-landing-page-settings', 'download-rider-app') }}">{{translate('Rider App Download')}}</a>
                </li>
            @endif
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/banner-section') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'banner-section') }}">{{translate('Banner Section')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/testimonials*') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'testimonials') }}">{{translate('messages.testimonials')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/gallery') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'gallery') }}">{{translate('gallery')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/highlight-section') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'highlight-section') }}">{{translate('Highlight Section')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/faq') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'faq') }}">{{translate('FAQ')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/footer') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'footer') }}">{{translate('Footer')}}</a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/react-landing-page-settings/meta-data') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.react-landing-page-settings', 'meta-data') }}">{{translate('Meta data')}}</a>
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

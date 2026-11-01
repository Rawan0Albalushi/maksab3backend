<div class="d-flex flex-wrap justify-content-between align-items-center mb-20 __gap-12px">
    <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
        <ul class="nav nav-tabs border-0 nav--tabs nav--pills">
            <li class="nav-item">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/flutter-landing-page-settings/fixed-data') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.flutter-landing-page-settings', 'fixed-data') }}">{{translate('messages.fixed Data')}}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/flutter-landing-page-settings/special-criteria*') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.flutter-landing-page-settings', 'special-criteria') }}">{{translate('messages.Special Criteria')}}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/flutter-landing-page-settings/available-zone*') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.flutter-landing-page-settings', 'available-zone') }}">{{translate('messages.Available zone')}}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/flutter-landing-page-settings/join-as') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.flutter-landing-page-settings', 'join-as') }}">{{translate('messages.Join as')}}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Request::is('admin/business-settings/pages/flutter-landing-page-settings/download-apps') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.flutter-landing-page-settings', 'download-apps') }}">{{translate('Download apps')}}</a>
            </li>
        </ul>
    </div>
</div>

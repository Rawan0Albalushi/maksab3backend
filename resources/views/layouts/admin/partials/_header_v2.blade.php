@php
    use App\CentralLogics\Helpers;

    $current_module_id   = Config::get('module.current_module_id');
    $current_module_type = Config::get('module.current_module_type');
    $current_module_name = Config::get('module.current_module_name');
    $current_module_icon = Config::get('module.current_module_icon');
    $admin_user          = auth('admin')->user();
    $local               = session()->has('local') ? session('local') : null;
    $lang                = Helpers::get_business_settings('system_language');

    $modules = app(\App\Services\System\ModuleService::class)->switcherModules($admin_user?->zone_id);

    // Helpers::admin_workspace_for_path() is the single source of truth for
    // which tab is active — the sidebar shell and the breadcrumb read the same
    // rules, so all three stay in step.
    $workspace         = Helpers::admin_workspace_for_path();
    $is_settings_path  = $workspace === 'settings';
    $is_users_path     = $workspace === 'users';
    $is_dispatch_path  = $workspace === 'dispatch';
    $is_reports_path   = $workspace === 'reports';
    $is_finance_path   = $workspace === 'finance';
    $is_module_path    = $workspace === 'module';

    $unread_messages = \App\Models\Conversation::whereUserType('admin')->whereHas('last_message', function ($q) {
        $q->whereColumn('conversations.sender_id', 'messages.sender_id');
    })->where('unread_message_count', '>', 0)->count();

    $safety_count = 0; $latest_safety = null;
    $ride_share_module_id = Helpers::ride_share_module_id() ?? 0;
    if (addon_published_status('RideShare')) {
        $safety_q = \Modules\RideShare\Entities\TripManagement\RideSafetyAlert::where('status', 'pending');
        $safety_count = $safety_q->count();
        $latest_safety = $safety_count > 0 ? $safety_q->latest()->first() : null;
    }

    $url_users    = Helpers::users_workspace_landing_url();
    $url_finance  = Helpers::finance_workspace_landing_url();
    $url_reports  = Helpers::reports_workspace_landing_url();
    $url_dispatch = route('admin.dispatch.dashboard');
    $url_settings = Helpers::settings_workspace_landing_url();
    $url_module   = route('admin.dashboard') . '?module_id=' . $current_module_id;
@endphp

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

<script>
(function () {
    try {
        var saved = localStorage.getItem('v2_mode_v1');
        var isPos = /^\/?admin\/pos(\/|$)/.test(window.location.pathname);
        var mode = isPos ? 'compact' : (saved === 'compact' ? 'compact' : 'pinned');
        document.body.classList.remove('v2-mode-pinned', 'v2-mode-compact');
        document.body.classList.add('v2-mode-' + mode);
    } catch (e) {}
})();
</script>

@include('layouts.admin.partials._v2_tour')

<header class="v2-topbar" role="banner">
    <a class="v2-brand" href="{{ route('admin.dashboard') }}">
        <img class="v2-brand-mark" src="{{ \App\CentralLogics\Helpers::logoFullUrl() }}" alt="Logo" onerror="this.src='{{ asset('public/assets/admin/img/160x160/img2.jpg') }}'">
    </a>

    <button type="button" class="v2-mode-btn v2-mobile-toggle" id="v2-mobile-toggle" aria-label="{{ translate('Toggle navigation drawer') }}">
        <i data-lucide="menu"></i>
    </button>
    @if($layout_features['compact_mode_toggle'] ?? true)
    <button type="button" class="v2-mode-btn v2-desktop-only" id="v2-mode-btn"
            title="{{ translate('Collapse sidebar') }}" aria-label="{{ translate('Collapse sidebar') }}"
            aria-controls="v2-panel" aria-pressed="false">
        @include('layouts.admin.partials._v2_mode_icon')
    </button>
    @endif

    <nav class="v2-topnav" id="v2-topnav" aria-label="{{ translate('Workspaces') }}">
        @if(Helpers::admin_can_access_workspace('module'))
        <a class="v2-ws-tab v2-ws-tab--module {{ $is_module_path ? 'is-active' : '' }}" href="{{ $url_module }}">
            <i data-lucide="package" class="v2-ws-ico"></i>
            <span>{{ translate('Module') }}</span>
            @if($is_module_path && $current_module_name)
                <span class="v2-module-pill">
                    <i data-lucide="layout-grid"></i>{{ $current_module_name }}
                </span>
            @endif
        </a>
        @endif
        @if(Helpers::admin_can_access_workspace('users'))
        <a class="v2-ws-tab {{ $is_users_path ? 'is-active' : '' }}" href="{{ $url_users }}">
            <i data-lucide="users" class="v2-ws-ico"></i>
            <span>{{ translate('Users') }}</span>
        </a>
        @endif
        @if(Helpers::admin_can_access_workspace('finance'))
        <a class="v2-ws-tab {{ $is_finance_path ? 'is-active' : '' }}" href="{{ $url_finance }}">
            <i data-lucide="wallet" class="v2-ws-ico"></i>
            <span>{{ translate('Finance') }}</span>
        </a>
        @endif
        @if(Helpers::admin_can_access_workspace('reports'))
        <a class="v2-ws-tab {{ $is_reports_path ? 'is-active' : '' }}" href="{{ $url_reports }}">
            <i data-lucide="bar-chart-3" class="v2-ws-ico"></i>
            <span>{{ translate('Reports') }}</span>
        </a>
        @endif
        @if(Helpers::admin_can_access_workspace('dispatch'))
        <a class="v2-ws-tab {{ $is_dispatch_path ? 'is-active' : '' }}" href="{{ $url_dispatch }}">
            <i data-lucide="route" class="v2-ws-ico"></i>
            <span>{{ translate('dispatch') }}</span>
        </a>
        @endif
        @if(Helpers::admin_can_access_workspace('settings'))
        <a class="v2-ws-tab {{ $is_settings_path ? 'is-active' : '' }}" href="{{ $url_settings }}">
            <i data-lucide="settings-2" class="v2-ws-ico"></i>
            <span>{{ translate('Settings') }}</span>
        </a>
        @endif
    </nav>

    <div class="v2-topbar-spacer"></div>

    <button type="button" class="v2-topbar-search" data-toggle="modal" data-target="#staticBackdrop" aria-label="{{ translate('Search') }}">
        <i data-lucide="search"></i>
        <span class="v2-search-placeholder">{{ translate('Search by keyword') }}</span>
        <kbd>Ctrl+K</kbd>
    </button>

    <div class="v2-topbar-actions">
        <button type="button" class="v2-icon-btn v2-mobile-only-search" data-toggle="modal" data-target="#staticBackdrop" aria-label="{{ translate('Search') }}">
            <i data-lucide="search"></i>
        </button>

        @if($lang)
            <div class="v2-lang-wrap">
                <button type="button" class="v2-icon-btn" id="v2-lang-btn" aria-haspopup="menu" aria-expanded="false" aria-label="{{ translate('Language') }}">
                    <i data-lucide="globe"></i>
                </button>
                <div class="v2-lang-pop" id="v2-lang-pop" role="menu">
                    <div class="v2-lang-pop-head">{{ translate('Language') }}</div>
                    @foreach($lang as $data)
                        @if(($data['status'] ?? 0) == 1)
                            <a class="v2-lang-item {{ ($data['code'] === $local) || (!$local && ($data['default'] ?? false) === true) ? 'is-active' : '' }}" href="{{ route('admin.lang', [$data['code']]) }}">
                                <span class="v2-lang-code">{{ strtoupper($data['code']) }}</span>
                                <span class="v2-lang-name">{{ ucfirst($data['name'] ?? $data['code']) }}</span>
                                <i data-lucide="check" class="v2-lang-check"></i>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if(\App\CentralLogics\Helpers::module_permission_check('customer_management'))
        <a href="{{ route('admin.message.list') }}" class="v2-icon-btn" aria-label="{{ translate('messages.message') }}">
            <i data-lucide="message-circle"></i>
            @if($unread_messages > 0)<span class="v2-badge">{{ $unread_messages }}</span>@endif
        </a>
        @endif

        @if(addon_published_status('RideShare') && \App\CentralLogics\Helpers::module_permission_check('heat_map'))
            <a id="v2-safety-link" href="{{ route('admin.ride-share.safety-alerts', ['module_id' => $ride_share_module_id]) }}" class="v2-icon-btn @if($latest_safety) safety-alert-header-icon @endif" @if($latest_safety) data-user-id="{{ $latest_safety->sent_by }}" @endif aria-label="{{ translate('Safety alerts') }}">
                <i data-lucide="shield-alert"></i>
                @if($safety_count > 0)<span class="v2-badge" id="v2-safety-badge">{{ $safety_count }}</span>@endif
            </a>
        @endif

        @if($layout_features['fullscreen'] ?? true)
        <button type="button" class="v2-icon-btn v2-desktop-only" id="v2-fs-btn" aria-label="{{ translate('Toggle fullscreen') }}" title="{{ translate('Toggle fullscreen') }} (F11)" data-exit-title="{{ translate('Exit fullscreen') }}">
            <i data-lucide="maximize-2" id="v2-fs-icon"></i>
        </button>
        @endif
    </div>

    <button type="button" class="v2-module-switcher" id="v2-mod-switch" aria-haspopup="menu" aria-expanded="false">
        <span class="v2-mod-icon">
            @if($current_module_icon)
                <img src="{{ $current_module_icon }}" alt="" onerror="this.src='{{ asset('public/assets/admin/img/new-img/module-icon.svg') }}'">
            @else
                <i data-lucide="shopping-cart"></i>
            @endif
        </span>
        <span class="v2-mod-name">{{ $current_module_name ?? translate('Modules') }}</span>
        <i data-lucide="chevron-down" class="v2-chev"></i>
    </button>
    <div class="v2-modpop" id="v2-modpop" role="menu">
        <h4>{{ translate('Modules section') }}</h4>
        <p>{{ translate('Select module & monitor your business module wise') }}</p>
        @if($modules->count() > 0)
            <div class="v2-mod-grid">
                @foreach($modules as $module)
                    {{-- Rental was the only addon type this guarded, so a Service or RideShare
                         module kept its tile on an install without that addon -- and with no addon
                         there is no icon either, so it drew as an empty square that switched the
                         panel into a module with no screens behind it. module_type_addon_active()
                         answers for all three. --}}
                    @if(module_type_addon_active($module->module_type))
                        <a href="javascript:"
                           class="v2-mod-tile set-module {{ $current_module_id == $module->id ? 'is-active' : '' }}"
                           data-module-id="{{ $module->id }}"
                           data-url="{{ $module->module_type === 'rental' && addon_published_status('Rental') ? route('admin.rental.dashboard') : route('admin.dashboard') }}"
                           data-filter="module_id">
                            @if(getEnvMode() == 'demo' && in_array($module->module_type, ['rental', 'ride-share']))
                                <span class="v2-mod-addon-tag">{{ translate('Addon') }}</span>
                            @endif
                            <div class="v2-mod-tile-ico">
                                <img src="{{ $module->icon_full_url }}" alt="" onerror="this.src='{{ asset('public/assets/admin/img/new-img/module/e-shop.svg') }}'">
                            </div>
                            <div>{{ $module->module_name }}</div>
                        </a>
                    @endif
                @endforeach
                @if(Helpers::module_permission_check('module'))
                    <a href="{{ route('admin.business-settings.module.create') }}" class="v2-mod-tile" title="{{ translate('Add new module') }}">
                        <div class="v2-mod-tile-ico"><i data-lucide="plus"></i></div>
                        <div>{{ translate('Add') }}</div>
                    </a>
                @endif
            </div>
        @else
            <div class="v2-mod-empty">
                <p>{{ translate('Please, enable or create module first') }}</p>
                <a href="{{ route('admin.business-settings.module.index') }}" class="v2-mod-empty-btn">{{ translate('Module setup') }}</a>
            </div>
        @endif
    </div>
</header>

<div class="v2-mobile-backdrop" id="v2-mobile-backdrop" aria-hidden="true"></div>

@push('script_2')
<script>
(function () {
    'use strict';

    function closePops() {
        document.querySelectorAll('.v2-modpop, .v2-lang-pop').forEach(function (p) { p.classList.remove('is-open'); });
        var ms = document.getElementById('v2-mod-switch');
        var lb = document.getElementById('v2-lang-btn');
        if (ms) ms.setAttribute('aria-expanded', 'false');
        if (lb) lb.setAttribute('aria-expanded', 'false');
    }

    function v2CloseDrawerIfOpen() {
        if (document.body.classList.contains('v2-drawer-open')) {
            document.body.classList.remove('v2-drawer-open');
        }
    }

    var modBtn = document.getElementById('v2-mod-switch');
    var modPop = document.getElementById('v2-modpop');
    if (modBtn && modPop) {
        modBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            v2CloseDrawerIfOpen();
            var isOpen = !modPop.classList.contains('is-open');
            closePops();
            if (isOpen) modPop.classList.add('is-open');
            modBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    var langBtn = document.getElementById('v2-lang-btn');
    var langPop = document.getElementById('v2-lang-pop');
    if (langBtn && langPop) {
        langBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            v2CloseDrawerIfOpen();
            var isOpen = !langPop.classList.contains('is-open');
            closePops();
            if (isOpen) langPop.classList.add('is-open');
            langBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('#v2-modpop, #v2-mod-switch, #v2-lang-pop, #v2-lang-btn')) return;
        closePops();
    });

    var MODE_KEY = 'v2_mode_v1';
    var MODE_ORDER = ['pinned', 'compact'];
    /* The button is labelled with what the next click does, not with the mode
       it is already in. The glyph animates between the two states on its own
       (.v2-mode-glyph in admin-v2.css), so nothing here touches the icon. */
    var MODE_LABEL = {
        pinned: '{{ translate('Collapse sidebar') }}',
        compact: '{{ translate('Expand sidebar') }}'
    };
    var modeBtn = document.getElementById('v2-mode-btn');
    var modeAnimTimer = null;

    /* The content edge is only allowed to animate for the length of a mode
       switch — see the .v2-mode-anim block in admin-v2.css for why it is not
       simply left on, and why the saved mode therefore lands instantly on
       page load instead of sliding in. */
    function armModeAnimation() {
        document.body.classList.add('v2-mode-anim');
        if (modeAnimTimer) clearTimeout(modeAnimTimer);
        modeAnimTimer = setTimeout(function () {
            document.body.classList.remove('v2-mode-anim');
            modeAnimTimer = null;
            /* Charts and datatables size themselves off the content width and
               only ever re-measure on resize, so tell them the edge settled. */
            var ev;
            try { ev = new Event('resize'); }
            catch (e) { ev = document.createEvent('Event'); ev.initEvent('resize', true, true); }
            window.dispatchEvent(ev);
        }, 380);
    }

    function applyMode(mode, persist) {
        if (MODE_ORDER.indexOf(mode) === -1) mode = 'pinned';
        document.body.classList.remove('v2-mode-pinned', 'v2-mode-compact');
        document.body.classList.add('v2-mode-' + mode);
        if (modeBtn) {
            modeBtn.setAttribute('title', MODE_LABEL[mode]);
            modeBtn.setAttribute('aria-label', MODE_LABEL[mode]);
            modeBtn.setAttribute('aria-pressed', mode === 'compact' ? 'true' : 'false');
        }
        if (persist !== false) {
            try { localStorage.setItem(MODE_KEY, mode); } catch (e) {}
        }
    }

    var isPosPage = /^\/?admin\/pos(\/|$)/.test(window.location.pathname);
    if (isPosPage) {
        applyMode('compact', false);
    } else {
        var savedMode;
        try { savedMode = localStorage.getItem(MODE_KEY); } catch (e) { savedMode = null; }
        applyMode(savedMode || 'pinned', false);
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest || !e.target.closest('#v2-mode-btn')) return;
        var current = document.body.classList.contains('v2-mode-compact') ? 'compact' : 'pinned';
        var next = MODE_ORDER[(MODE_ORDER.indexOf(current) + 1) % MODE_ORDER.length];
        armModeAnimation();
        applyMode(next, !isPosPage);
    });

    var mobileToggle = document.getElementById('v2-mobile-toggle');
    var backdrop = document.getElementById('v2-mobile-backdrop');
    function setDrawer(open) {
        document.body.classList.toggle('v2-drawer-open', !!open);
    }
    function isDrawerOpen() {
        return document.body.classList.contains('v2-drawer-open');
    }
    if (mobileToggle) mobileToggle.addEventListener('click', function () {
        setDrawer(!isDrawerOpen());
    });
    if (backdrop) backdrop.addEventListener('click', function () { setDrawer(false); });

    var DRAWER_CLOSE_TRIGGERS = [
        '.v2-topbar-search',
        '.v2-mobile-only-search',
        '#v2-mod-switch',
        '#v2-lang-btn',
        '.v2-topbar-actions a.v2-icon-btn'
    ].join(',');
    document.addEventListener('click', function (e) {
        if (!isDrawerOpen()) return;
        if (e.target.closest(DRAWER_CLOSE_TRIGGERS)) {
            setDrawer(false);
        }
    });

    document.addEventListener('click', function (e) {
        if (!isDrawerOpen()) return;
        var navLink = e.target.closest('#v2-panel a[href]');
        if (navLink) setDrawer(false);
    });

    if (window.jQuery) {
        window.jQuery(document).on('show.bs.modal show.bs.offcanvas', function () {
            if (isDrawerOpen()) setDrawer(false);
        });
    } else {
        document.addEventListener('show.bs.modal', function () { if (isDrawerOpen()) setDrawer(false); }, true);
        document.addEventListener('show.bs.offcanvas', function () { if (isDrawerOpen()) setDrawer(false); }, true);
    }

    var modpopForDelegate = document.getElementById('v2-modpop');
    if (modpopForDelegate) {
        modpopForDelegate.addEventListener('click', function (e) {
            var tile = e.target.closest('a.set-module[data-url][data-module-id]');
            if (!tile) return;
            e.preventDefault();
            setDrawer(false);
            try {
                var url = new URL(tile.getAttribute('data-url'), window.location.href);
                var filterBy = tile.getAttribute('data-filter') || 'module_id';
                url.searchParams.set(filterBy, tile.getAttribute('data-module-id'));
                window.location.href = url.toString();
            } catch (err) {}
        });
    }


    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
})();
</script>
@endpush

@php
    use App\CentralLogics\Helpers;
    $store_data        = Helpers::get_store_data();
    $logged_in_user    = Helpers::get_loggedin_user();
    $local             = session()->has('vendor_local') ? session('vendor_local') : null;
    $lang              = $system_languages;
    $unread_messages   = $unread_message_count;
@endphp

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

<script>
/* Restore the saved mode before the chrome paints. Without this the panel
   renders pinned and then jumps to compact once the deferred script below
   runs — the admin topbar has done this since compact mode landed. */
(function () {
    try {
        var saved = localStorage.getItem('v2_view_mode');
        var isPos = /\/vendor-panel\/pos(\/|$)/.test(window.location.pathname);
        document.body.classList.toggle('v2-mode-compact', isPos || saved === 'compact');
    } catch (e) {}
})();
</script>

<header class="v2-topbar" role="banner">
    <a class="v2-brand" href="{{ route('vendor.dashboard') }}" title="{{ $store_data->name ?? 'Store' }}">
        <img class="v2-brand-mark" src="{{ $store_data?->logo_full_url ?: asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="{{ $store_data->name ?? 'Store' }}" onerror="this.src='{{ asset('public/assets/admin/img/160x160/img2.jpg') }}'">
        <span class="v2-brand-name v2-desktop-only">{{ $store_data->name ?? '' }}</span>
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

    @if(Helpers::check_website_builder_status() && Helpers::employee_module_permission_check('custom_website'))
    <a href="{{ route('vendor.builder.index', ['page' => 'global-settings']) }}"
       class="website-builder-btn v2-builder-btn" id="vendor-dashboard-builder-button">
        <span class="website-builder-icon">
            <img src="{{ asset('public/assets/admin/img/builder.svg') }}" alt="">
        </span>
        <span class="v2-desktop-only">{{ translate('Build your custom website') }}</span>
        @if(getEnvMode() == 'demo')
            <span class="v2-builder-addon-tag v2-desktop-only">{{ translate('Addon') }}</span>
        @endif
    </a>
    @endif

    <div class="v2-topbar-spacer"></div>

    <button type="button" class="v2-topbar-search" data-toggle="modal" data-target="#staticBackdrop" aria-label="{{ translate('Search or') }}">
        <i data-lucide="search"></i>
        <span class="v2-search-placeholder">{{ translate('Search by keyword') }}</span>
        <kbd>Ctrl+K</kbd>
    </button>

    <div class="v2-topbar-actions">
        <button type="button" class="v2-icon-btn v2-mobile-only-search" data-toggle="modal" data-target="#staticBackdrop" aria-label="{{ translate('Search or') }}">
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
                            <a class="v2-lang-item {{ ($data['code'] === $local) || (!$local && ($data['default'] ?? false) === true) ? 'is-active' : '' }}" href="{{ route('vendor.lang', [$data['code']]) }}">
                                <span class="v2-lang-code">{{ strtoupper($data['code']) }}</span>
                                <span class="v2-lang-name">{{ ucfirst($data['name'] ?? $data['code']) }}</span>
                                <i data-lucide="check" class="v2-lang-check"></i>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if(Helpers::employee_module_permission_check('chat'))
        <a href="{{ route('vendor.message.list') }}" class="v2-icon-btn" aria-label="{{ translate('messages.message') }}">
            <i data-lucide="message-circle"></i>
            @if($unread_messages > 0)<span class="v2-badge">{{ $unread_messages }}</span>@endif
        </a>
        @endif

        @if($layout_features['fullscreen'] ?? true)
        <button type="button" class="v2-icon-btn v2-desktop-only" id="v2-fs-btn" aria-label="{{ translate('Toggle fullscreen') }}" title="{{ translate('Toggle fullscreen') }} (F11)" data-exit-title="{{ translate('Exit fullscreen') }}">
            <i data-lucide="maximize-2" id="v2-fs-icon"></i>
        </button>
        @endif
    </div>
</header>

<div class="v2-mobile-backdrop" id="v2-mobile-backdrop" aria-hidden="true"></div>

@push('script_2')
<script>
(function () {
    'use strict';

    function closePops() {
        document.querySelectorAll('.v2-lang-pop').forEach(function (p) { p.classList.remove('is-open'); });
        var lb = document.getElementById('v2-lang-btn');
        if (lb) lb.setAttribute('aria-expanded', 'false');
    }

    var langBtn = document.getElementById('v2-lang-btn');
    var langPop = document.getElementById('v2-lang-pop');
    if (langBtn && langPop) {
        langBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = !langPop.classList.contains('is-open');
            closePops();
            if (open) { langPop.classList.add('is-open'); langBtn.setAttribute('aria-expanded', 'true'); }
        });
    }
    document.addEventListener('click', function (e) {
        if (e.target.closest('#v2-lang-pop, #v2-lang-btn')) return;
        closePops();
    });

    var modeBtn = document.getElementById('v2-mode-btn');
    var savedMode = localStorage.getItem('v2_view_mode') || 'pinned';
    var isPosPage = /\/vendor-panel\/pos(\/|$)/.test(window.location.pathname);
    var modeAnimTimer = null;
    /* The button is labelled with what the next click does. The glyph animates
       between its two states on its own — see .v2-mode-glyph in admin-v2.css —
       so nothing here rewrites the icon node. */
    var MODE_LABEL = {
        pinned: '{{ translate('Collapse sidebar') }}',
        compact: '{{ translate('Expand sidebar') }}'
    };

    /* The content edge is only allowed to animate for the length of a mode
       switch — see the .v2-mode-anim block in admin-v2.css. Arming it here and
       nowhere else is also what keeps the saved mode from sliding in on every
       page load. */
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

    function applyMode(m) {
        document.body.classList.toggle('v2-mode-compact', m === 'compact');
        if (modeBtn) {
            modeBtn.setAttribute('title', MODE_LABEL[m === 'compact' ? 'compact' : 'pinned']);
            modeBtn.setAttribute('aria-label', MODE_LABEL[m === 'compact' ? 'compact' : 'pinned']);
            modeBtn.setAttribute('aria-pressed', m === 'compact' ? 'true' : 'false');
        }
    }
    applyMode(isPosPage ? 'compact' : savedMode);
    if (modeBtn) modeBtn.addEventListener('click', function () {
        var next = document.body.classList.contains('v2-mode-compact') ? 'pinned' : 'compact';
        if (!isPosPage) localStorage.setItem('v2_view_mode', next);
        armModeAnimation();
        applyMode(next);
    });

    var mobileToggle = document.getElementById('v2-mobile-toggle');
    var backdrop = document.getElementById('v2-mobile-backdrop');
    function setDrawer(open) { document.body.classList.toggle('v2-drawer-open', !!open); }
    if (mobileToggle) mobileToggle.addEventListener('click', function () {
        setDrawer(!document.body.classList.contains('v2-drawer-open'));
    });
    if (backdrop) backdrop.addEventListener('click', function () { setDrawer(false); });

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
})();
</script>
@endpush

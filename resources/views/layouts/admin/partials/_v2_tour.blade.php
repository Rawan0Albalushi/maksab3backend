@php
    $tour_features   = config('layout.features', []);
    $tour_pin        = $tour_features['pin'] ?? true;
    $tour_compact    = $tour_features['compact_mode_toggle'] ?? true;
    $tour_fullscreen = $tour_features['fullscreen'] ?? true;
    $tour_rideshare  = addon_published_status('RideShare');
@endphp

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css">
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js"></script>

<script>
(function () {
    'use strict';

    var STORAGE_KEY = 'v2_tour_seen_v1';
    var MOBILE_MQ = '(max-width: 720px)';

    // The instance currently driving, so the injected "Skip tour"
    // button has something to destroy.
    var activeDriver = null;

    function el(selector) {
        return document.querySelector(selector);
    }
    function isMobile() {
        return window.matchMedia(MOBILE_MQ).matches;
    }
    function setDrawerOpen(open) {
        document.body.classList.toggle('v2-drawer-open', !!open);
    }
    function isDrawerOpen() {
        return document.body.classList.contains('v2-drawer-open');
    }

    // Step hooks that open the mobile drawer before highlighting an element
    // inside the rail/panel and leave it open until a non-drawer step is
    // reached. The driver.js `onHighlightStarted` / `onDeselected` hooks fire
    // once per step transition, giving us a clean place to manage the state.
    function drawerStepHooks() {
        return {
            onHighlightStarted: function () {
                if (isMobile() && !isDrawerOpen()) setDrawerOpen(true);
            }
        };
    }
    function nonDrawerStepHooks() {
        return {
            onHighlightStarted: function () {
                if (isMobile() && isDrawerOpen()) setDrawerOpen(false);
            }
        };
    }

    // Build the list of steps for the current viewport. Skips steps whose
    // target element isn't present, or that don't make sense on this viewport.
    function defineSteps() {
        var mobile = isMobile();
        var steps = [];

        // 1. Welcome
        steps.push({
            element: '.v2-brand',
            popover: {
                icon: 'sparkles',
                title: '{{ translate('Welcome to the new admin') }}',
                description: '{{ translate('We have redesigned the layout. Here is a quick tour of what changed — it takes under a minute.') }}',
                side: 'bottom', align: 'start'
            },
            ...nonDrawerStepHooks()
        });

        // 2. Workspace tabs (on mobile these live in a horizontal strip
        //    below the topbar; on desktop they're inline in the topbar)
        steps.push({
            element: '.v2-topnav',
            popover: {
                icon: 'layout-grid',
                title: '{{ translate('Workspaces') }}',
                description: '{{ translate('Switch between module, users, finance, reports, dispatch and settings from here.') }}',
                side: 'bottom', align: mobile ? 'start' : 'center'
            },
            ...nonDrawerStepHooks()
        });

        // 3. Active module pill
        if (el('.v2-ws-tab--module')) {
            steps.push({
                element: '.v2-ws-tab--module',
                popover: {
                    icon: 'package',
                    title: '{{ translate('Active module') }}',
                    description: '{{ translate('The module tab shows what you are currently working in.') }}',
                    side: 'bottom', align: 'start'
                },
                ...nonDrawerStepHooks()
            });
        }

        // 4. Module switcher
        if (el('#v2-mod-switch')) {
            steps.push({
                element: '#v2-mod-switch',
                popover: {
                    icon: 'arrow-left-right',
                    title: '{{ translate('Module switcher') }}',
                    description: '{{ translate('Switch between grocery, food, pharmacy, parcel and other business modules from here.') }}',
                    side: 'bottom', align: 'end'
                },
                ...nonDrawerStepHooks()
            });
        }

        // 5. (mobile only) Hamburger button — how to reach the sidebar
        if (mobile && el('#v2-mobile-toggle')) {
            steps.push({
                element: '#v2-mobile-toggle',
                popover: {
                    icon: 'menu',
                    title: '{{ translate('Open the sidebar') }}',
                    description: '{{ translate('Tap the menu icon any time to slide the sidebar in or out.') }}',
                    side: 'bottom', align: 'start'
                },
                ...nonDrawerStepHooks()
            });
        }

        // 6. Rail (drawer opens automatically on mobile)
        if (el('#v2-rail')) {
            steps.push({
                element: '#v2-rail',
                popover: {
                    icon: 'panel-left',
                    title: '{{ translate('The rail') }}',
                    description: '{{ translate('Each workspace has sections. Click an icon to switch panels.') }}',
                    side: mobile ? 'bottom' : 'right',
                    align: 'start'
                },
                ...drawerStepHooks()
            });
        }

        // 7. Panel
        if (el('#v2-panel')) {
            steps.push({
                element: '#v2-panel',
                popover: {
                    icon: 'list',
                    title: '{{ translate('The panel') }}',
                    description: '{{ translate('Navigation items live here. The active item stays highlighted. Click a chevron to collapse a group.') }}',
                    side: mobile ? 'bottom' : 'right',
                    align: 'start'
                },
                ...drawerStepHooks()
            });
        }

        // 8. Pinning (config-gated)
        @if($tour_pin)
        if (true) {
            var pinBtn = document.querySelector('.v2-panel-content:not([hidden]) .v2-pin')
                      || document.querySelector('.v2-pin');
            if (pinBtn) {
                steps.push({
                    element: pinBtn,
                    popover: {
                        icon: 'pin',
                        title: '{{ translate('Pinning') }}',
                        description: '{{ translate('Tap the pin on any item to add a shortcut at the top of this panel.') }}',
                        side: mobile ? 'bottom' : 'right',
                        align: 'start'
                    },
                    ...drawerStepHooks()
                });
            }
        }
        @endif

        // 9. Search — desktop uses the topbar search bar; mobile uses the
        //    smaller search icon next to language.
        var searchBar = mobile
            ? el('.v2-mobile-only-search')
            : el('.v2-topbar-search');
        if (searchBar) {
            steps.push({
                element: searchBar,
                popover: {
                    icon: 'search',
                    title: '{{ translate('Search') }}',
                    description: mobile
                        ? '{{ translate('Tap the search icon to jump to any admin page by keyword.') }}'
                        : '{{ translate('Press Ctrl+K (or ⌘+K) to jump to any admin page by keyword.') }}',
                    side: 'bottom', align: 'center'
                },
                ...nonDrawerStepHooks()
            });
        }

        // 10. Language
        if (el('#v2-lang-btn')) {
            steps.push({
                element: '#v2-lang-btn',
                popover: {
                    icon: 'languages',
                    title: '{{ translate('Language') }}',
                    description: '{{ translate('Switch the admin UI language.') }}',
                    side: 'bottom', align: 'end'
                },
                ...nonDrawerStepHooks()
            });
        }

        // 11. Notifications (message icon)
        var msgBell = document.querySelector('a.v2-icon-btn[href*="message"]')
                   || document.querySelector('.v2-topbar-actions .v2-icon-btn');
        if (msgBell) {
            steps.push({
                element: msgBell,
                popover: {
                    icon: 'bell',
                    title: '{{ translate('Notifications') }}',
                    description: '{{ translate('Messages and safety alerts show up here.') }}',
                    side: 'bottom', align: 'end'
                },
                ...nonDrawerStepHooks()
            });
        }

        // 12. Safety alerts (only when RideShare addon is published)
        @if($tour_rideshare)
        var safetyLink = el('#v2-safety-link');
        if (safetyLink) {
            steps.push({
                element: safetyLink,
                popover: {
                    icon: 'shield-alert',
                    title: '{{ translate('Safety alerts') }}',
                    description: '{{ translate('Live ride-share safety alerts surface here. The badge shows how many are pending.') }}',
                    side: 'bottom', align: 'end'
                },
                ...nonDrawerStepHooks()
            });
        }
        @endif

        // 13. (desktop only) Compact mode toggle — hidden on mobile
        @if($tour_compact)
        if (!mobile) {
            var modeBtn = el('#v2-mode-btn');
            if (modeBtn) {
                steps.push({
                    element: modeBtn,
                    popover: {
                        icon: 'minimize-2',
                        title: '{{ translate('Compact mode') }}',
                        description: '{{ translate('Toggle compact mode to hide the panel and hover-to-peek when you need more screen space.') }}',
                        side: 'bottom', align: 'start'
                    },
                    ...nonDrawerStepHooks()
                });
            }
        }
        @endif

        @if($tour_fullscreen)
        if (!mobile) {
            var fsBtn = el('#v2-fs-btn');
            if (fsBtn) {
                steps.push({
                    element: fsBtn,
                    popover: {
                        icon: 'maximize',
                        title: '{{ translate('Fullscreen') }}',
                        description: '{{ translate('Enter fullscreen for an immersive view. Click this button to toggle.') }} {{ translate('Keyboard shortcut') }}: F11',
                        side: 'bottom', align: 'end'
                    },
                    ...nonDrawerStepHooks()
                });
            }
        }
        @endif

        // 13. Profile avatar (lives at the bottom of the rail — drawer opens
        //     automatically on mobile so it's reachable)
        var profileBtn = el('#v2-rail-profile');
        if (profileBtn) {
            steps.push({
                element: profileBtn,
                popover: {
                    icon: 'user',
                    title: '{{ translate('Profile') }}',
                    description: '{{ translate('Open your profile, settings or log out from the avatar at the bottom of the rail.') }}',
                    side: mobile ? 'top' : 'right',
                    align: mobile ? 'start' : 'end'
                },
                ...drawerStepHooks()
            });
        }

        return steps;
    }

    var SKIP_TEXT     = @json(translate('Skip tour'));
    var CLOSE_TEXT    = @json(translate('Close'));
    var STEP_TEXT     = @json(translate('Step'));

    // Lucide glyphs, inlined. Calling lucide.createIcons() here would
    // re-scan and re-create every icon in the page once per step, since
    // the replaced <svg> keeps its data-lucide attribute.
    var ICONS = {
        'sparkles': '<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/><path d="M20 2v4"/><path d="M22 4h-4"/><circle cx="4" cy="20" r="2"/>',
        'layout-grid': '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
        'package': '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><polyline points="3.29 7 12 12 20.71 7"/><path d="m7.5 4.27 9 5.15"/>',
        'arrow-left-right': '<path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/>',
        'menu': '<path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/>',
        'panel-left': '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/>',
        'list': '<path d="M3 5h.01"/><path d="M3 12h.01"/><path d="M3 19h.01"/><path d="M8 5h13"/><path d="M8 12h13"/><path d="M8 19h13"/>',
        'pin': '<path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/>',
        'search': '<path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/>',
        'languages': '<path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/>',
        'bell': '<path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>',
        'shield-alert': '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
        'minimize-2': '<path d="m14 10 7-7"/><path d="M20 10h-6V4"/><path d="m3 21 7-7"/><path d="M4 14h6v6"/>',
        'maximize': '<path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/>',
        'user': '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'chevron-right': '<path d="m9 18 6-6-6-6"/>',
        'chevron-left': '<path d="m15 18-6-6 6-6"/>',
        'check': '<path d="M20 6 9 17l-5-5"/>',
        'x': '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'compass': '<circle cx="12" cy="12" r="10"/><path d="m16.24 7.76-1.804 5.411a2 2 0 0 1-1.265 1.265L7.76 16.24l1.804-5.411a2 2 0 0 1 1.265-1.265z"/>'
    };

    // `.v2-tour-dir` marks the glyphs RTL should mirror — the chevrons,
    // never the check or the close ×.
    function iconMarkup(name, directional) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"'
            + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"'
            + (directional ? ' class="v2-tour-dir"' : '') + '>'
            + (ICONS[name] || ICONS.compass) + '</svg>';
    }
    function iconNode(name, directional) {
        var holder = document.createElement('div');
        holder.innerHTML = iconMarkup(name, directional);
        return holder.firstChild;
    }

    function stepLabel(current, total) {
        return STEP_TEXT + ' ' + current + '/' + total;
    }

    // Chrome reports :focus-visible for the programmatic focus below even
    // when the step was advanced with the mouse, so the focus ring would sit
    // on Next for the whole tour. Track the real input modality instead and
    // let `body.v2-tour-kb` gate the ring.
    function onTourKeydown(e) {
        if (e.key === 'Tab' || e.key === 'ArrowLeft' || e.key === 'ArrowRight'
            || e.key === 'Enter' || e.key === ' ' || e.key === 'Escape') {
            document.body.classList.add('v2-tour-kb');
        }
    }
    function onTourPointer() {
        document.body.classList.remove('v2-tour-kb');
    }
    function watchInputModality(on) {
        var fn = on ? 'addEventListener' : 'removeEventListener';
        document[fn]('keydown', onTourKeydown, true);
        document[fn]('pointerdown', onTourPointer, true);
        if (!on) document.body.classList.remove('v2-tour-kb');
    }

    // Rebuild a footer button as label + glyph. The class driver.js
    // dispatches clicks on stays on the button itself; the children are
    // made click-through in CSS, since driver reads `event.target`.
    function decorateButton(btn, name, directional, glyphFirst) {
        var label = document.createElement('span');
        label.textContent = (btn.textContent || '').trim();
        btn.textContent = '';
        var glyph = iconNode(name, directional);
        if (glyphFirst) {
            btn.appendChild(glyph);
            btn.appendChild(label);
        } else {
            btn.appendChild(label);
            btn.appendChild(glyph);
        }
    }

    function enhancePopover(popover, opts) {
        var state = (opts && opts.state) || {};
        var steps = ((opts && opts.config) || {}).steps || [];
        var total = steps.length || 1;
        var index = state.activeIndex || 0;
        var meta  = (steps[index] && steps[index].popover) || {};
        var isFirst = index === 0;
        var isLast  = index >= total - 1;

        var head = document.createElement('div');
        head.className = 'v2-tour-head';

        var badge = document.createElement('span');
        badge.className = 'v2-tour-badge';
        badge.appendChild(iconNode(meta.icon || 'compass'));

        var eyebrow = document.createElement('span');
        eyebrow.className = 'v2-tour-eyebrow';
        eyebrow.textContent = stepLabel(index + 1, total);

        var heading = document.createElement('div');
        heading.className = 'v2-tour-heading';
        heading.appendChild(eyebrow);
        heading.appendChild(popover.title);

        head.appendChild(badge);
        head.appendChild(heading);

        var track = document.createElement('div');
        track.className = 'v2-tour-track';
        var fill = document.createElement('span');
        fill.className = 'v2-tour-track__fill';
        fill.style.width = '0%';
        track.appendChild(fill);

        var body = document.createElement('div');
        body.className = 'v2-tour-body';
        body.appendChild(popover.description);

        popover.wrapper.insertBefore(head, popover.footer);
        popover.wrapper.insertBefore(track, popover.footer);
        popover.wrapper.insertBefore(body, popover.footer);

        // Grow the fill on the frame after layout, so the bar animates
        // to its new length on every step rather than snapping to it.
        window.requestAnimationFrame(function () {
            fill.style.width = Math.round(((index + 1) / total) * 100) + '%';
        });

        if (isFirst) {
            // driver disables Back on step one; an inert control reads
            // as a dead end, so drop it entirely.
            popover.previousButton.style.display = 'none';
        } else {
            decorateButton(popover.previousButton, 'chevron-left', true, true);
        }
        decorateButton(popover.nextButton, isLast ? 'check' : 'chevron-right', !isLast, false);

        if (!isLast) {
            var skip = document.createElement('button');
            skip.type = 'button';
            skip.className = 'v2-tour-skip';
            skip.textContent = SKIP_TEXT;
            skip.addEventListener('click', function (e) {
                e.preventDefault();
                if (activeDriver) activeDriver.destroy();
            });
            popover.footer.insertBefore(skip, popover.footer.firstChild);
        }

        // driver writes `display: block` inline on the close button, which
        // would beat any stylesheet — re-write it so the glyph centres.
        if (popover.closeButton.style.display !== 'none') {
            popover.closeButton.style.display = 'grid';
        }
        popover.closeButton.textContent = '';
        popover.closeButton.appendChild(iconNode('x'));
        popover.closeButton.setAttribute('aria-label', CLOSE_TEXT);

        // driver focuses the close button once this hook returns. Move it
        // to the primary action so a keyboard user lands on Next, not on
        // the one control that ends the tour.
        window.requestAnimationFrame(function () {
            try { popover.nextButton.focus({ preventScroll: true }); } catch (e) {}
        });
    }

    function markSeen() {
        try { localStorage.setItem(STORAGE_KEY, '1'); } catch (e) {}
        // Always tidy up the drawer after the tour ends on mobile so we don't
        // leave it open in the user's face.
        if (isMobile()) setDrawerOpen(false);
        watchInputModality(false);
        activeDriver = null;
    }

    function buildDriver() {
        if (typeof window.driver === 'undefined' || !window.driver.js) {
            return null;
        }
        var mobile = isMobile();
        return window.driver.js.driver({
            // The step counter is rendered in the popover header instead.
            showProgress: false,
            allowClose: true,
            stagePadding: mobile ? 6 : 8,
            stageRadius: 12,
            popoverOffset: 12,
            overlayColor: '#0a1f1c',
            overlayOpacity: 0.62,
            smoothScroll: true,
            popoverClass: 'v2-tour-popover',
            nextBtnText: '{{ translate('Next') }}',
            prevBtnText: '{{ translate('Back') }}',
            doneBtnText: '{{ translate('Done') }}',
            onPopoverRender: enhancePopover,
            steps: defineSteps(),
            onDestroyed: markSeen
        });
    }

    window.startV2Tour = function (opts) {
        opts = opts || {};
        var inst = buildDriver();
        if (!inst) return;
        if (opts.restart) {
            try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
        }
        activeDriver = inst;
        watchInputModality(true);
        inst.drive();
    };

    // First-visit auto-start. Only on v2 chrome, only if not seen.
    document.addEventListener('DOMContentLoaded', function () {
        if (!document.body.classList.contains('v2-chrome')) return;

        // "Replay tour" entry — wired from the legacy floating tour panel
        // (.restart-Tour link) and from the v2 profile popover (.v2-replay-tour).
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('.v2-replay-tour');
            if (!trigger) return;
            e.preventDefault();
            // Close the profile popover so the tour can target rail elements.
            var pop = document.getElementById('v2-profile-pop');
            if (pop) pop.classList.remove('is-open');
            window.startV2Tour({ restart: true });
        });

        var seen = false;
        try { seen = localStorage.getItem(STORAGE_KEY) === '1'; } catch (e) {}
        if (seen) return;
        // Wait a tick so Lucide icons + rail panels finish rendering.
        setTimeout(function () { window.startV2Tour(); }, 600);
    });
})();
</script>

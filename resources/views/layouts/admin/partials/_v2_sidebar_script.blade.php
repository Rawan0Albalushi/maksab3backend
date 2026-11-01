@push('script_2')
<script>
(function () {
    'use strict';

    var shell = document.getElementById('v2-shell');
    if (!shell) return;
    var workspace = shell.dataset.workspace || 'module';
    var rail = document.getElementById('v2-rail');
    var panel = document.getElementById('v2-panel');
    if (!rail || !panel) return;

    var PIN_KEY = 'v2_pins_v1';
    var COLLAPSED_KEY = 'v2_collapsed_v1';
    var PARENTS_KEY = 'v2_parents_v1';

    function load(k) { try { return JSON.parse(localStorage.getItem(k) || '{}'); } catch (e) { return {}; } }
    function save(k, v) { localStorage.setItem(k, JSON.stringify(v)); }

    var pins = load(PIN_KEY);
    var collapsed = load(COLLAPSED_KEY);
    var parentsState = load(PARENTS_KEY);

    function pinKeyFor(panelId) { return workspace + '::' + panelId; }

    /* ---- Sliding rail marker ----
       A single pill that travels to the active icon. Without it every icon
       cross-faded its own background against every other, which is what made
       hover switching read as a blink. Built here rather than in the blade so
       all seven rails (six admin workspaces + vendor) get it from one place. */
    var railBtns = rail.querySelector('.v2-rail-btns');
    var marker = null;
    if (railBtns) {
        marker = document.createElement('span');
        marker.className = 'v2-rail-marker';
        marker.setAttribute('aria-hidden', 'true');
        var blob = document.createElement('span');
        blob.className = 'v2-rail-marker-blob';
        marker.appendChild(blob);
        railBtns.insertBefore(marker, railBtns.firstChild);
        rail.classList.add('has-rail-marker');
    }

    function moveMarker(btn, instant) {
        if (!marker) return;
        if (!btn) { marker.classList.remove('is-ready'); return; }
        // The rail is display:none in a couple of mobile states, where every
        // offset reads 0. Measuring then would park the pill at the top, so
        // hold it hidden and let the observer below place it once laid out.
        if (!btn.offsetHeight) { marker.classList.remove('is-ready'); return; }
        // offsetTop is layout-relative to .v2-rail-btns, so a scrolled rail
        // (Settings has 12 icons) still lands the pill on the right button.
        if (instant) marker.classList.add('is-instant');
        marker.style.height = btn.offsetHeight + 'px';
        marker.style.transform = 'translateY(' + btn.offsetTop + 'px)';
        marker.classList.add('is-ready');
        if (instant) {
            void marker.offsetWidth;
            marker.classList.remove('is-instant');
            return;
        }
        // restart the squash/stretch even when it is already mid-flight
        marker.classList.remove('is-travelling');
        void marker.offsetWidth;
        marker.classList.add('is-travelling');
    }

    function activateRailSection(btn) {
        if (!btn) return;
        var current = rail.querySelector('.v2-rail-btn[data-section].is-active');
        // re-running this on the section already shown would replay the panel
        // animation from scratch — that was the flicker on repeated hover
        if (current === btn) return;
        var sect = btn.dataset.section;
        rail.querySelectorAll('.v2-rail-btn[data-section]').forEach(function (b) {
            b.classList.toggle('is-active', b === btn);
        });
        panel.querySelectorAll('.v2-panel-content').forEach(function (p) {
            if (p.dataset.panel === sect) p.removeAttribute('hidden');
            else p.setAttribute('hidden', '');
        });
        // a section left scrolled down would otherwise hand the next one a
        // mid-scroll starting position, which reads as a jump
        panel.scrollTop = 0;
        moveMarker(btn, false);
    }

    function syncMarker() {
        moveMarker(rail.querySelector('.v2-rail-btn[data-section].is-active'), true);
    }
    syncMarker();
    window.addEventListener('resize', syncMarker);
    // catches the rail going from display:none to laid out (mobile drawer
    // opening, modal closing) and any reflow of the icon stack
    if (window.ResizeObserver && railBtns) {
        new ResizeObserver(syncMarker).observe(railBtns);
    }

    rail.addEventListener('click', function (e) {
        var btn = e.target.closest('.v2-rail-btn[data-section]');
        if (!btn) return;
        activateRailSection(btn);
    });

    var hoverSwitchTimer = null;
    var pendingBtn = null;
    function clearHoverSwitch() {
        if (hoverSwitchTimer) { clearTimeout(hoverSwitchTimer); hoverSwitchTimer = null; }
        pendingBtn = null;
    }
    rail.addEventListener('mouseover', function (e) {
        var btn = e.target.closest('.v2-rail-btn[data-section]');
        if (!btn) return;
        if (btn.classList.contains('is-active')) { clearHoverSwitch(); return; }
        // mouseover bubbles from the inner <svg> too, so drifting across one
        // icon used to keep restarting the timer and stall the switch
        if (btn === pendingBtn) return;
        clearHoverSwitch();
        pendingBtn = btn;
        hoverSwitchTimer = setTimeout(function () {
            hoverSwitchTimer = null;
            pendingBtn = null;
            activateRailSection(btn);
        }, 70);
    });
    rail.addEventListener('mouseleave', clearHoverSwitch);

    var anchoredSection = shell.dataset.activeSection
        || (function () {
            var b = rail.querySelector('.v2-rail-btn[data-section].is-active');
            return b ? b.dataset.section : null;
        })();

    shell.addEventListener('mouseleave', function () {
        clearHoverSwitch();
        if (!anchoredSection) return;
        var btn = rail.querySelector('.v2-rail-btn[data-section="' + anchoredSection + '"]');
        if (btn && !btn.classList.contains('is-active')) {
            activateRailSection(btn);
            // activateRailSection resets scrollTop, so bring the page's own
            // nav item back into view when we settle on the anchored section
            scrollActiveIntoView();
        }
    });

    /* ---- Collapse / expand ----
       Groups hide with `display`, parents with the `hidden` attribute, and
       both used to land in one frame: twelve links appearing at once, and the
       panel below them jumping to its new position. slideSection measures the
       box, pins it to that height and lets CSS carry it to the other end
       (.is-sliding in admin-v2.css), then drops the inline height again — a
       height left pinned would clip a count badge that changes later. */
    var prefersReducedMotion = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function setSectionHidden(el, hidden, useAttr) {
        if (useAttr) {
            if (hidden) el.setAttribute('hidden', '');
            else el.removeAttribute('hidden');
        } else {
            el.style.display = hidden ? 'none' : '';
        }
    }

    function slideSection(el, open, useAttr) {
        if (!el) return;
        // read the live height first: mid-flight it is the animated one, and
        // landing the previous run would otherwise snap it before we look
        var start = el.offsetHeight;
        if (el.v2SlideEnd) el.v2SlideEnd();
        if (prefersReducedMotion) { setSectionHidden(el, !open, useAttr); return; }

        setSectionHidden(el, false, useAttr);
        el.style.height = '';
        var target = open ? el.offsetHeight : 0;
        if (start === target) { setSectionHidden(el, !open, useAttr); return; }

        el.style.height = start + 'px';
        el.style.opacity = open ? '0' : '1';
        el.classList.add('is-sliding');
        void el.offsetHeight;

        var timer = null;
        function finish() {
            el.removeEventListener('transitionend', onEnd);
            if (timer) { clearTimeout(timer); timer = null; }
            el.v2SlideEnd = null;
            el.classList.remove('is-sliding');
            el.style.height = '';
            el.style.opacity = '';
            if (!open) setSectionHidden(el, true, useAttr);
        }
        function onEnd(e) {
            if (e.target === el && e.propertyName === 'height') finish();
        }
        // a transitionend never arrives if the tab is hidden mid-flight, and
        // the list would stay pinned at whatever height it stopped at
        timer = setTimeout(finish, 600);
        el.v2SlideEnd = finish;
        el.addEventListener('transitionend', onEnd);

        el.style.height = target + 'px';
        el.style.opacity = open ? '1' : '0';
    }

    panel.addEventListener('click', function (e) {
        var hdr = e.target.closest('[data-group-toggle]');
        if (hdr) {
            var key = hdr.dataset.groupToggle;
            var group = hdr.parentElement;
            var items = group.querySelector('.v2-group-items');
            var isCollapsed = group.classList.toggle('is-collapsed');
            slideSection(items, !isCollapsed, false);
            collapsed[key] = isCollapsed;
            save(COLLAPSED_KEY, collapsed);
            return;
        }
        var par = e.target.closest('[data-parent-toggle]');
        if (par) {
            var pkey = par.dataset.parentToggle;
            var children = par.nextElementSibling;
            var isOpen = par.classList.toggle('is-open');
            slideSection(children, isOpen, true);
            parentsState[pkey] = isOpen;
            save(PARENTS_KEY, parentsState);
            return;
        }
        var pin = e.target.closest('.v2-pin');
        if (pin) {
            e.preventDefault();
            e.stopPropagation();
            var visiblePanel = panel.querySelector('.v2-panel-content:not([hidden])');
            var panelKey = pinKeyFor(visiblePanel ? visiblePanel.dataset.panel : 'dashboard');
            var id = pin.dataset.pin;
            var list = pins[panelKey] || [];
            var idx = list.indexOf(id);
            if (idx === -1) list.push(id); else list.splice(idx, 1);
            pins[panelKey] = list;
            save(PIN_KEY, pins);
            renderPinned(panelKey);
            panel.querySelectorAll('.v2-pin[data-pin="' + id + '"]').forEach(function (b) {
                b.classList.toggle('is-pinned', list.indexOf(id) !== -1);
            });
        }
    });

    panel.querySelectorAll('[data-group-toggle]').forEach(function (hdr) {
        if (collapsed[hdr.dataset.groupToggle]) {
            var group = hdr.parentElement;
            group.classList.add('is-collapsed');
            var items = group.querySelector('.v2-group-items');
            if (items) items.style.display = 'none';
        }
    });
    panel.querySelectorAll('[data-parent-toggle]').forEach(function (par) {
        if (parentsState[par.dataset.parentToggle] && !par.classList.contains('is-open')) {
            par.classList.add('is-open');
            var children = par.nextElementSibling;
            if (children) children.removeAttribute('hidden');
        }
    });

    function renderPinned(panelKey) {
        var sect = panelKey.split('::')[1];
        var p = panel.querySelector('.v2-panel-content[data-panel="' + sect + '"]');
        if (!p) return;
        var card = p.querySelector('.v2-pinned-card');
        if (!card) return;
        var list = pins[panelKey] || [];
        var inner = card.querySelector('.v2-pinned-list');
        if (!inner) return;
        if (list.length === 0) {
            inner.innerHTML = '<div class="v2-pinned-empty">' + (card.dataset.emptyText || 'Hover any item and tap the pin to add a shortcut here.') + '</div>';
            return;
        }
        var html = '';
        list.forEach(function (id) {
            var src = p.querySelector('.v2-nav-item[data-id="' + id + '"]');
            if (src) html += '<div class="v2-pinned-item">' + src.innerHTML + '</div>';
        });
        inner.innerHTML = html || '<div class="v2-pinned-empty">' + (card.dataset.emptyText || '') + '</div>';
        inner.querySelectorAll('.v2-pinned-item').forEach(function (it, i) {
            var src = p.querySelector('.v2-nav-item[data-id="' + list[i] + '"]');
            if (src && src.href) it.addEventListener('click', function () { location.href = src.href; });
        });
    }

    panel.querySelectorAll('.v2-panel-content').forEach(function (p) {
        var key = pinKeyFor(p.dataset.panel);
        var list = pins[key] || [];
        list.forEach(function (id) {
            var btn = p.querySelector('.v2-pin[data-pin="' + id + '"]');
            if (btn) btn.classList.add('is-pinned');
        });
        renderPinned(key);
    });

    var railTooltip = document.querySelector('.v2-rail-tooltip');
    if (!railTooltip) {
        railTooltip = document.createElement('div');
        railTooltip.className = 'v2-rail-tooltip';
        document.body.appendChild(railTooltip);
    }
    function showRailTooltip(btn) {
        var label = btn.getAttribute('data-label') || btn.getAttribute('aria-label');
        if (!label) return;
        railTooltip.textContent = label;
        var rect = btn.getBoundingClientRect();
        var isRtl = document.documentElement.getAttribute('dir') === 'rtl';
        if (isRtl) {
            railTooltip.style.left = '';
            railTooltip.style.right = (window.innerWidth - rect.left + 12) + 'px';
        } else {
            railTooltip.style.right = '';
            railTooltip.style.left = (rect.right + 12) + 'px';
        }
        railTooltip.style.top  = (rect.top + rect.height / 2) + 'px';
        railTooltip.classList.add('is-visible');
    }
    function hideRailTooltip() { railTooltip.classList.remove('is-visible'); }
    rail.querySelectorAll('.v2-rail-btn[data-label]').forEach(function (btn) {
        btn.addEventListener('mouseenter', function () { showRailTooltip(btn); });
        btn.addEventListener('mouseleave', hideRailTooltip);
        btn.addEventListener('click', hideRailTooltip);
        btn.addEventListener('focus', function () { showRailTooltip(btn); });
        btn.addEventListener('blur', hideRailTooltip);
    });

    function scrollActiveIntoView() {
        var visiblePanel = panel.querySelector('.v2-panel-content:not([hidden])');
        if (!visiblePanel) return;
        var active = visiblePanel.querySelector('.v2-nav-item.is-active');
        if (!active) return;
        var panelRect = panel.getBoundingClientRect();
        var activeRect = active.getBoundingClientRect();
        if (activeRect.top < panelRect.top + 8 || activeRect.bottom > panelRect.bottom - 8) {
            var delta = activeRect.top - panelRect.top - (panel.clientHeight / 2) + (active.clientHeight / 2);
            panel.scrollTop = panel.scrollTop + delta;
        }
    }
    scrollActiveIntoView();
    rail.addEventListener('click', function () { setTimeout(scrollActiveIntoView, 0); });

    var profileBtn = document.getElementById('v2-rail-profile');
    var profilePop = document.getElementById('v2-profile-pop');
    if (profileBtn && profilePop) {
        profileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = profilePop.classList.toggle('is-open');
            profileBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#v2-profile-pop, #v2-rail-profile')) {
                profilePop.classList.remove('is-open');
                profileBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
})();
</script>
@endpush

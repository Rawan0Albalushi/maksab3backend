'use strict';

/* ==========================================================================
   Horizontal tab strips — one behaviour for every `ul.nav-tabs` in the panel.

   The strips are already a scrolling row of pills (`admin-tabs.css`), and the
   longer ones — React landing page settings has 16 tabs, the email templates
   a dozen — are wider than the page. What was missing was everything around
   the scroll:

     - The current tab was never brought into view. Landing on the last tab
       ("Meta Data") left the strip parked at 0 with the active pill off the
       right edge, so every switch lost the admin's place.
     - The arrows stepped by one pill, so crossing a 16-tab strip took ten
       clicks.
     - `document.querySelector('.tabs-inner')` bound ONE container and the
       FIRST `.btn-click-prev` / `.btn-click-next` on the page. A page with
       two strips got a second one that did nothing, and on the product /
       delivery-man forms the tab script could grab the image gallery's
       arrows instead (that gallery's wrapper is `tabs-inner` too).

   This file replaces that inline script in both app layouts.

   Two kinds of scroller, because that inline script served both:

     - A TAB STRIP (`ul.nav-tabs`) gets the whole treatment, including the
       restyled arrows the `.tabs-scroller-host` rules in admin-tabs.css draw.
     - A SLIDER — the `div.tabs-inner` rows: the order-view prescription
       thumbnails, the hand-rolled image galleries on the product, delivery-man
       and rider forms — gets the behaviour only. Its arrows keep style.css's
       own look and placement (the prescription ones are 102px translucent
       bars, not circles), so their visibility is still toggled with an inline
       `display` exactly as before.

   Sliders that already own a scoped handler opt out with
   `data-tab-scroller="off"` — `_multiple-image-uploader.blade.php` is the one.

   Arrows are only wired where the markup already ships an `.arrow-area`;
   nothing is injected, so no screen grows controls it did not have.

   Never `scrollIntoView()`. That walks every scrollable ancestor, so
   revealing a pill would also scroll the page — the header jumps away under
   the admin for a 40px sideways correction. Everything here writes
   `strip.scrollLeft` directly, which cannot move anything but the strip.

   RTL: rects are physical and `scrollLeft` still grows leftward under
   `dir="rtl"` (it just runs -max..0), so the maths below is direction-blind.
   Only the range clamp and the arrow/keyboard direction flip.
   ========================================================================== */

(function () {
    var STRIP_SELECTOR = 'ul.nav-tabs:not(.navbar-nav):not(.nav-tabs--plain)';
    var ACTIVE_SELECTOR = '.nav-link.active, .nav-item.show > .nav-link, .nav-link[aria-selected="true"]';
    var EDGE = 2;        /* px of slack when deciding "is it at the end" */
    var DRAG_SLOP = 4;   /* px of travel before a press counts as a drag, not a click */
    var GUTTER = 12;     /* px kept between a revealed pill and the strip edge */

    var strips = [];

    function isRtl(el) {
        return window.getComputedStyle(el).direction === 'rtl';
    }

    function maxScroll(strip) {
        return Math.max(0, strip.scrollWidth - strip.clientWidth);
    }

    /* scrollLeft runs 0..max under LTR and -max..0 under RTL. */
    function clamp(strip, value) {
        var max = maxScroll(strip);
        return isRtl(strip) ? Math.min(0, Math.max(-max, value)) : Math.max(0, Math.min(max, value));
    }

    function scrollTo(strip, value, smooth) {
        var target = clamp(strip, value);
        var max = maxScroll(strip);
        var rtl = isRtl(strip);
        /* Land exactly on an end when we come within a gutter of it, so the
           arrow for that direction actually disappears instead of hanging
           around to scroll the last few pixels. */
        if (Math.abs(target) <= GUTTER) target = 0;
        else if (Math.abs(target) >= max - GUTTER) target = rtl ? -max : max;
        if (smooth && typeof strip.scrollTo === 'function') {
            strip.scrollTo({ left: target, behavior: 'smooth' });
        } else {
            strip.scrollLeft = target;
        }
    }

    function items(strip) {
        return Array.prototype.slice.call(strip.children);
    }

    function links(strip) {
        return Array.prototype.slice.call(strip.querySelectorAll('.nav-link:not(.disabled)'));
    }

    /* --------------------------------------------------------------------
       State — drives the arrows and the edge shadows from CSS classes
       rather than inline `display`, so both can transition.
       -------------------------------------------------------------------- */

    function update(entry) {
        var strip = entry.strip;
        var max = maxScroll(strip);
        var offset = Math.abs(strip.scrollLeft);
        var scrollable = max > EDGE;
        var atStart = !scrollable || offset <= EDGE;
        var atEnd = !scrollable || offset >= max - EDGE;

        strip.classList.toggle('can-scroll', scrollable);

        if (entry.kind === 'tabs') {
            entry.host.classList.toggle('is-scrollable', scrollable);
            entry.host.classList.toggle('at-start', atStart);
            entry.host.classList.toggle('at-end', atEnd);
        } else if (entry.prevWrap && entry.nextWrap) {
            /* A slider's arrows are styled by style.css against the wrapper
               they were authored in, so they keep the inline `display` the
               replaced script used rather than moving onto state classes. */
            entry.prevWrap.style.display = atStart ? 'none' : 'flex';
            entry.nextWrap.style.display = atEnd ? 'none' : 'flex';
        }

        if (entry.prevBtn) entry.prevBtn.disabled = atStart;
        if (entry.nextBtn) entry.nextBtn.disabled = atEnd;
    }

    /* --------------------------------------------------------------------
       Reveal — put the active pill on screen without moving the page
       -------------------------------------------------------------------- */

    function reveal(entry, smooth) {
        var strip = entry.strip;
        if (maxScroll(strip) <= EDGE) return;

        var active = strip.querySelector(ACTIVE_SELECTOR);
        if (!active) return;

        var item = active.closest('li') || active;
        var stripBox = strip.getBoundingClientRect();
        var itemBox = item.getBoundingClientRect();

        var overStart = stripBox.left + GUTTER - itemBox.left;
        var overEnd = itemBox.right - (stripBox.right - GUTTER);
        if (overStart <= 0 && overEnd <= 0) return;   /* already comfortably in view */

        /* Centre it when there is room — an active tab hard against the edge
           reads as "the end of the strip" and hides that more follow. */
        var centred = (itemBox.left - stripBox.left) - (stripBox.width - itemBox.width) / 2;
        scrollTo(strip, strip.scrollLeft + centred, smooth);
    }

    /* --------------------------------------------------------------------
       Arrows — step a screenful, landing on a pill boundary
       -------------------------------------------------------------------- */

    function step(entry, forward) {
        var strip = entry.strip;
        var box = strip.getBoundingClientRect();
        var list = items(strip);
        var i;
        var rect;

        if (forward) {
            /* First pill clipped by (or past) the trailing edge becomes the
               new leading pill — no half-cut label at either end. */
            for (i = 0; i < list.length; i++) {
                rect = list[i].getBoundingClientRect();
                if (rect.right > box.right - EDGE) {
                    scrollTo(strip, strip.scrollLeft + (rect.left - box.left) - GUTTER, true);
                    return;
                }
            }
        } else {
            for (i = list.length - 1; i >= 0; i--) {
                rect = list[i].getBoundingClientRect();
                if (rect.left < box.left + EDGE) {
                    scrollTo(strip, strip.scrollLeft + (rect.right - box.right) + GUTTER, true);
                    return;
                }
            }
        }

        /* No pill straddles the edge (a single very wide one, say) — fall
           back to a plain screenful. */
        var page = Math.max(strip.clientWidth * 0.8, 120);
        scrollTo(strip, strip.scrollLeft + (forward ? page : -page), true);
    }

    /* --------------------------------------------------------------------
       Drag to scroll
       -------------------------------------------------------------------- */

    function bindDrag(entry) {
        var strip = entry.strip;
        var startX = 0;
        var startScroll = 0;
        var pointerId = null;
        var dragged = false;

        strip.addEventListener('pointerdown', function (event) {
            /* Touch and pen already scroll the strip natively; handling them
               here too would move it twice per swipe. */
            if (event.pointerType && event.pointerType !== 'mouse') return;
            if (event.button !== 0 || maxScroll(strip) <= EDGE) return;
            if (event.target.closest('input, select, textarea, button')) return;

            pointerId = event.pointerId;
            startX = event.clientX;
            startScroll = strip.scrollLeft;
            dragged = false;
        });

        strip.addEventListener('pointermove', function (event) {
            if (pointerId === null || event.pointerId !== pointerId) return;

            var travel = event.clientX - startX;
            if (!dragged) {
                if (Math.abs(travel) < DRAG_SLOP) return;
                dragged = true;
                entry.touched = true;
                strip.classList.add('is-dragging');
                /* Capture only once it is a real drag, so a plain click on a
                   pill still reaches the link. */
                if (strip.setPointerCapture) strip.setPointerCapture(pointerId);
            }
            strip.scrollLeft = clamp(strip, startScroll - travel);
        });

        function end(event) {
            if (pointerId === null || (event && event.pointerId !== pointerId)) return;
            if (strip.releasePointerCapture && strip.hasPointerCapture && strip.hasPointerCapture(pointerId)) {
                strip.releasePointerCapture(pointerId);
            }
            pointerId = null;
            strip.classList.remove('is-dragging');
        }

        strip.addEventListener('pointerup', end);
        strip.addEventListener('pointercancel', end);

        /* The pills are links, and a mouse press that moves starts a native
           link drag — Chrome then fires `pointercancel` and the strip stops
           following the cursor after the first few pixels. Dragging a tab out
           to a bookmark bar is worth less here than dragging the strip. */
        strip.addEventListener('dragstart', function (event) {
            if (pointerId !== null) event.preventDefault();
        });

        /* Swallow the click that closes a drag — capture phase, ahead of the
           link and of page-transition.js's progress bar. */
        strip.addEventListener('click', function (event) {
            if (!dragged) return;
            dragged = false;
            event.preventDefault();
            event.stopPropagation();
        }, true);
    }

    /* --------------------------------------------------------------------
       Keyboard — move focus along the strip, the way a tab list should
       -------------------------------------------------------------------- */

    function bindKeys(entry) {
        entry.strip.addEventListener('keydown', function (event) {
            var forward;
            if (event.key === 'ArrowRight') forward = !isRtl(entry.strip);
            else if (event.key === 'ArrowLeft') forward = isRtl(entry.strip);
            else if (event.key !== 'Home' && event.key !== 'End') return;

            var list = links(entry.strip);
            if (list.length < 2) return;
            entry.touched = true;

            var at = list.indexOf(document.activeElement);
            var next;
            if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = list.length - 1;
            else if (at === -1) return;
            else next = at + (forward ? 1 : -1);

            if (next < 0 || next >= list.length) return;
            event.preventDefault();
            list[next].focus({ preventScroll: true });

            var box = entry.strip.getBoundingClientRect();
            var rect = list[next].getBoundingClientRect();
            if (rect.left < box.left + GUTTER) {
                scrollTo(entry.strip, entry.strip.scrollLeft + (rect.left - box.left) - GUTTER, true);
            } else if (rect.right > box.right - GUTTER) {
                scrollTo(entry.strip, entry.strip.scrollLeft + (rect.right - box.right) + GUTTER, true);
            }
        });
    }

    /* --------------------------------------------------------------------
       Wiring
       -------------------------------------------------------------------- */

    function setUp(strip, kind) {
        if (strip.dataset.tabScroller) return null;
        strip.dataset.tabScroller = '1';

        var host = strip.parentElement;
        if (!host) return null;

        var entry = { strip: strip, host: host, kind: kind };

        var wrap = strip.closest('.tabs-slide-wrap');
        var area = wrap ? wrap.querySelector(':scope > .arrow-area') : null;
        /* One `.arrow-area` per wrapper: if a wrapper ever holds two
           scrollers, the first claims the arrows rather than both wiring the
           same pair. */
        if (area && area.dataset.tabScrollerArrows) area = null;
        if (area && !area.contains(strip)) {
            area.dataset.tabScrollerArrows = '1';
            entry.prevWrap = area.querySelector('.button-prev');
            entry.nextWrap = area.querySelector('.button-next');
            entry.prevBtn = area.querySelector('.btn-click-prev');
            entry.nextBtn = area.querySelector('.btn-click-next');

            /* Tab strips only: `.arrow-area` usually sits a level above the
               strip, on a `flex-wrap` row that is two lines tall when the tabs
               overflow — which put a `top: 50%` arrow between the lines. Re-home
               it on the strip's own parent so it hugs the strip. A slider's
               arrows are positioned against their authored wrapper and stay
               where they are. */
            if (kind === 'tabs' && area.parentElement !== host) host.appendChild(area);
        }

        strip.classList.add('tabs-scroller');
        if (kind === 'tabs') host.classList.add('tabs-scroller-host');

        if (entry.prevBtn) {
            entry.prevBtn.addEventListener('click', function () {
                entry.touched = true;
                step(entry, isRtl(strip));
            });
        }
        if (entry.nextBtn) {
            entry.nextBtn.addEventListener('click', function () {
                entry.touched = true;
                step(entry, !isRtl(strip));
            });
        }

        strip.addEventListener('scroll', function () { update(entry); }, { passive: true });
        bindDrag(entry);
        bindKeys(entry);

        if (window.ResizeObserver) {
            var ro = new ResizeObserver(function () {
                update(entry);
                if (!entry.touched) reveal(entry, false);
            });
            ro.observe(strip);
            /* The strip's own box never changes when the icon set and web
               fonts land late — only its content does, and `scrollWidth` is
               not observable. A pill is: watching the last one catches the
               reflow that would otherwise leave the revealed tab a few px
               short of the end, with a "next" arrow that scrolls nothing. */
            if (strip.lastElementChild) ro.observe(strip.lastElementChild);
        }
        if (window.MutationObserver) {
            /* Catches pills added late (language tabs, ajax counts) and the
               in-page tab skins that just move `.active` around. It re-reveals
               only when the active pill actually changed — the filter is
               `class` on the whole subtree, so anything that touches a class
               here (a hover skin, the drag flag) would otherwise yank a strip
               the admin is mid-drag on back to the current tab. */
            entry.active = strip.querySelector(ACTIVE_SELECTOR);
            new MutationObserver(function () {
                update(entry);
                var active = strip.querySelector(ACTIVE_SELECTOR);
                if (active === entry.active) return;
                entry.active = active;
                reveal(entry, true);
            }).observe(strip, { childList: true, subtree: true, attributeFilter: ['class'] });
        }

        update(entry);
        reveal(entry, false);
        strips.push(entry);
        return entry;
    }

    function scan() {
        document.querySelectorAll(STRIP_SELECTOR).forEach(function (strip) {
            setUp(strip, 'tabs');
        });

        document.querySelectorAll('.tabs-slide-wrap').forEach(function (wrap) {
            if (wrap.closest('[data-tab-scroller="off"]')) return;
            var slider = wrap.querySelector('.tabs-inner');
            if (!slider || slider.matches(STRIP_SELECTOR)) return;
            setUp(slider, 'slider');
        });
    }

    function refresh(rereveal) {
        scan();
        strips.forEach(function (entry) {
            update(entry);
            /* Only while the admin has not taken the strip over themselves —
               snapping someone's manual scroll back to the active tab on a
               window resize would be worse than leaving it where they put it. */
            if (rereveal && !entry.touched) reveal(entry, false);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }

    /* Web fonts and the tio icon set land after DOMContentLoaded and change
       every pill's width, which walks the revealed tab back off the edge — so
       these re-reveal rather than only re-measuring. */
    window.addEventListener('load', function () { refresh(true); });

    var resizeFrame = null;
    window.addEventListener('resize', function () {
        if (resizeFrame) return;
        resizeFrame = requestAnimationFrame(function () {
            resizeFrame = null;
            refresh(true);
        });
    });
    if (document.fonts && document.fonts.ready) {
        /* A frame later: `fonts.ready` resolves before the reflow it causes
           has been laid out, so measuring on the microtask reads stale pill
           widths. */
        document.fonts.ready.then(function () {
            requestAnimationFrame(function () { refresh(true); });
        });
    }

    /* Strips inside a modal or a collapsed panel measure 0 until shown. */
    if (window.jQuery) {
        window.jQuery(document).on('shown.bs.modal shown.bs.collapse shown.bs.tab', function () {
            refresh(true);
        });
    }

    window.TabScroller = {
        refresh: function () { refresh(false); },
        reveal: function () {
            strips.forEach(function (entry) { reveal(entry, true); });
        }
    };
})();

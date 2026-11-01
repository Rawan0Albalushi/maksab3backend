/* breadcrumb.js — behaviour for the section crumbs rendered by
 * resources/views/partials/_breadcrumb.blade.php.
 *
 * A section crumb names a v2 sidebar panel rather than a URL, so clicking it
 * reopens that panel. Under v1 chrome there is no rail to open, so the button
 * is downgraded to plain text instead of being left as a control that does
 * nothing. */
(function () {
    'use strict';

    function railButton(section) {
        return document.querySelector('.v2-rail-btn[data-section="' + section + '"]');
    }

    /* The trail scrolls rather than truncating, and on a narrow screen the crumb
     * that matters most - the page you are on - is the one off the end. */
    function revealCurrent() {
        var scroller = document.querySelector('.bcx__scroll');

        if (!scroller || scroller.scrollWidth <= scroller.clientWidth) {
            return;
        }

        var overflow = scroller.scrollWidth - scroller.clientWidth;

        // In RTL the scroll origin is the right edge, and browsers disagree on
        // whether that is 0 or a negative offset; both directions are covered
        // by trying the sign that actually moves it.
        scroller.scrollLeft = document.documentElement.dir === 'rtl' ? -overflow : overflow;

        if (scroller.scrollLeft === 0 && overflow > 0) {
            scroller.scrollLeft = overflow;
        }
    }

    function init() {
        revealCurrent();
        window.addEventListener('resize', revealCurrent);

        var steps = document.querySelectorAll('.bcx [data-bc-panel]');

        if (!steps.length) {
            return;
        }

        Array.prototype.forEach.call(steps, function (step) {
            var target = railButton(step.getAttribute('data-bc-panel'));

            if (!target) {
                var span = document.createElement('span');
                // Not step.className: that is .bcx__step, which carries the
                // pointer cursor and hover tint. Copying it onto the span
                // leaves the crumb looking like the control it just stopped
                // being. .bcx__text is the plain-grouping style.
                span.className = 'bcx__text';
                span.textContent = step.textContent.trim();
                step.parentNode.replaceChild(span, step);
                return;
            }

            step.addEventListener('click', function () {
                target.click();
                // The panel is off-canvas on small screens until the drawer is
                // opened, so ask for it the same way the burger does.
                var toggle = document.getElementById('v2-mobile-toggle');
                if (toggle && getComputedStyle(toggle).display !== 'none'
                    && !document.body.classList.contains('v2-drawer-open')) {
                    toggle.click();
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

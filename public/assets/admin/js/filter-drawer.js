"use strict";

/**
 * Filter Drawer — drives the shared right-hand slide-in filter panel.
 *
 * It takes over every `#datatableFilterSidebar` / `.filter-drawer` on the page:
 * backdrop, slide transition, Esc + backdrop-click + close-button dismissal,
 * body scroll lock, focus handling, and a live "N active" count in the header.
 *
 * Pages only need the markup they already have. A trigger is any element with
 * `.filter-button-show` (or `[data-fd-open]`); it can name its panel with
 * `data-fd-target="#selector"`, otherwise the first panel on the page is used.
 */
(function ($) {
    if (!$) {
        return;
    }

    var DRAWER_PARTS = ['#datatableFilterSidebar', '.filter-drawer'];
    var DRAWER_SEL = DRAWER_PARTS.join(', ');
    var OPEN_SEL = '.filter-button-show, [data-fd-open], #filter-button-on';
    var CLOSE_SEL = '.fd-close, .filter-button-hide, [data-fd-close], #filter-button-off';
    var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    var $backdrop = null;
    var $active = null;
    var $opener = null;

    /** Expand a descendant selector across both drawer roots. */
    function within(suffix) {
        return DRAWER_PARTS.map(function (root) {
            return root + ' ' + suffix;
        }).join(', ');
    }

    function backdrop() {
        if (!$backdrop || !$backdrop.length) {
            $backdrop = $('.filter-drawer-backdrop').first();

            if (!$backdrop.length) {
                $backdrop = $('<div class="filter-drawer-backdrop" aria-hidden="true"></div>').appendTo('body');
            }
        }

        return $backdrop;
    }

    function drawerFor($trigger) {
        var target = $trigger.data('fd-target');

        return target ? $(target).first() : $(DRAWER_SEL).first();
    }

    function open($drawer, $trigger) {
        if (!$drawer || !$drawer.length || $drawer.hasClass('is-open')) {
            return;
        }

        $opener = $trigger && $trigger.length ? $trigger : null;
        $active = $drawer;

        backdrop().addClass('is-open');
        $drawer.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('fd-open');

        if ($opener) {
            $opener.attr('aria-expanded', 'true');
        }

        // Let the panel finish sliding before pulling focus, otherwise the
        // browser scrolls the still-offscreen panel into view.
        window.setTimeout(function () {
            var $first = $drawer.find(FOCUSABLE).filter(':visible').first();

            if ($first.length) {
                $first.trigger('focus');
            } else {
                $drawer.attr('tabindex', '-1').trigger('focus');
            }
        }, 340);

        refreshCount($drawer);
    }

    function close($drawer) {
        $drawer = $drawer && $drawer.length ? $drawer : $active;

        if (!$drawer || !$drawer.length) {
            return;
        }

        $drawer.removeClass('is-open').attr('aria-hidden', 'true');
        backdrop().removeClass('is-open');
        $('body').removeClass('fd-open');

        if ($opener) {
            $opener.attr('aria-expanded', 'false').trigger('focus');
            $opener = null;
        }

        $active = null;
    }

    /**
     * Count the filter groups the form currently has a value for. A group is one
     * named control (a multi-select, a checkbox set, a radio set, a date input) —
     * so ticking three statuses still reads as one active filter, which matches
     * how the badge on the trigger button is counted server-side.
     */
    function activeCount($drawer) {
        var $form = $drawer.find('form').first();

        if (!$form.length) {
            return 0;
        }

        var seen = {};
        var count = 0;

        $form.find('select, input').each(function () {
            var field = this;
            var name = field.name;

            if (!name || field.type === 'hidden' || field.disabled || name === '_token' || seen[name]) {
                return;
            }

            var filled;

            if (field.type === 'checkbox' || field.type === 'radio') {
                filled = $form.find('[name="' + name.replace(/"/g, '\\"') + '"]').filter(':checked').length > 0;
            } else if (field.tagName === 'SELECT') {
                var values = $(field).val();
                values = Array.isArray(values) ? values : (values === null ? [] : [values]);
                filled = values.filter(function (value) {
                    return value !== '' && value !== 'all';
                }).length > 0;
            } else {
                filled = $.trim(field.value || '') !== '';
            }

            if (filled) {
                seen[name] = true;
                count += 1;
            }
        });

        return count;
    }

    function refreshCount($drawer) {
        if (!$drawer || !$drawer.length) {
            return;
        }

        var $chip = $drawer.find('[data-fd-count]');

        if (!$chip.length) {
            return;
        }

        var count = activeCount($drawer);
        var label = $chip.data('fd-count-label') || 'Active';

        $chip.text(String(label) + ': ' + count).toggleClass('is-visible', count > 0);
    }

    function trapFocus(event, $drawer) {
        var $items = $drawer.find(FOCUSABLE).filter(':visible');

        if (!$items.length) {
            return;
        }

        var first = $items.get(0);
        var last = $items.get($items.length - 1);

        if (event.shiftKey && event.target === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && event.target === last) {
            event.preventDefault();
            first.focus();
        }
    }

    $(function () {
        var $drawers = $(DRAWER_SEL);

        if (!$drawers.length) {
            return;
        }

        $drawers.each(function () {
            var $drawer = $(this);

            // The panel is `position: fixed`; reparenting it to <body> keeps a
            // transformed ancestor from turning it into an absolutely positioned
            // box inside the page content.
            if (!$drawer.parent().is('body')) {
                $drawer.appendTo('body');
            }

            // The legacy handlers animated it with jQuery show()/hide(), which
            // leaves an inline `display` behind that fights the CSS transition.
            $drawer.css('display', '')
                .removeClass('initial-hidden')
                .attr('aria-hidden', 'true')
                .attr('role', 'dialog')
                .attr('aria-modal', 'true');

            refreshCount($drawer);
        });

        backdrop();

        $(document).on('click', OPEN_SEL, function (event) {
            var $trigger = $(this);
            var $drawer = drawerFor($trigger);

            if (!$drawer.length) {
                return;
            }

            event.preventDefault();
            open($drawer, $trigger);
        });

        $(document).on('click', CLOSE_SEL, function (event) {
            var $drawer = $(this).closest(DRAWER_SEL);

            if (!$drawer.length && !$active) {
                return;
            }

            event.preventDefault();
            close($drawer.length ? $drawer : $active);
        });

        $(document).on('click', '.filter-drawer-backdrop', function () {
            close();
        });

        $(document).on('keydown', function (event) {
            if (!$active) {
                return;
            }

            if (event.key === 'Escape' || event.keyCode === 27) {
                close();
            } else if (event.key === 'Tab' || event.keyCode === 9) {
                trapFocus(event, $active);
            }
        });

        // Bootstrap modals sit in the same stacking range; never show both.
        $(document).on('show.bs.modal', function () {
            close();
        });

        // `select2:select` / `select2:unselect` because select2 does not always
        // fire a native change on the original element; both bubble to document.
        $(document).on(
            'change keyup select2:select select2:unselect',
            within('form :input'),
            function () {
                refreshCount($(this).closest(DRAWER_SEL));
            }
        );

        $(document).on('reset', within('form'), function () {
            var $drawer = $(this).closest(DRAWER_SEL);

            window.setTimeout(function () {
                refreshCount($drawer);
            }, 0);
        });
    });

    window.FilterDrawer = {
        open: function (selector) {
            open(selector ? $(selector).first() : $(DRAWER_SEL).first(), null);
        },
        close: function () {
            close();
        },
        refresh: function (selector) {
            $(selector || DRAWER_SEL).each(function () {
                refreshCount($(this));
            });
        }
    };
})(window.jQuery);

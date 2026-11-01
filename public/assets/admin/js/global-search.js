/**
 * Global search palette — shared by the admin and vendor panels.
 *
 * Owns everything that is identical between the two panels: result markup,
 * empty/loading states and command-palette keyboard behaviour. The panel
 * layouts keep the AJAX wiring (routes and translations live there) and hand
 * the payload to `GlobalSearch.results()` / `GlobalSearch.recent()`.
 *
 * Markup: resources/views/layouts/{admin,vendor}/partials/_header.blade.php
 * Styles: public/assets/admin/css/global-search.css
 */
(function (window, document) {
    'use strict';

    var ICONS = {
        page: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>',
        record: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.66 3.13 3 7 3s7-1.34 7-3V6"/><path d="M5 12v6c0 1.66 3.13 3 7 3s7-1.34 7-3v-6"/></svg>',
        recent: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        arrow: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13"/><path d="m12 5 7 7-7 7"/></svg>',
        empty: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/><path d="M8.5 11h5"/></svg>',
        idle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>'
    };

    var labels = {
        pages: 'Pages',
        records: 'Records',
        recent: 'Recent searches',
        noResultTitle: 'No result found',
        noResultText: 'Try another keyword, or search by ID, name, phone or email.',
        idleTitle: 'Search anything',
        idleText: 'Jump to any page, or look up an order, store, customer and more.',
        errorTitle: 'Something went wrong',
        errorText: 'We could not load the results. Please try again.'
    };

    var images = { noResult: null };

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /** Escape first, then wrap keyword hits — never the other way round. */
    function highlight(value, keyword) {
        var safe = escapeHtml(value);

        if (!keyword) {
            return safe;
        }

        var pattern = escapeHtml(keyword).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

        if (!pattern) {
            return safe;
        }

        return safe.replace(new RegExp('(' + pattern + ')', 'gi'), '<mark>$1</mark>');
    }

    function icon(name) {
        return ICONS[name] || ICONS.page;
    }

    function row(item) {
        var kind = item.kind || 'page';

        return '<a href="' + escapeHtml(item.href) + '" class="gsearch-item search-list-item" role="option" tabindex="-1"' +
            ' data-route-name="' + escapeHtml(item.routeName) + '"' +
            ' data-route-uri="' + escapeHtml(item.uri) + '"' +
            ' data-route-full-url="' + escapeHtml(item.fullRoute) + '">' +
            '<span class="gsearch-item-ico">' + icon(kind) + '</span>' +
            '<span class="gsearch-item-main">' +
            '<span class="gsearch-item-title">' + item.titleHtml + '</span>' +
            '<span class="gsearch-item-path">' + item.pathHtml + '</span>' +
            '</span>' +
            '<span class="gsearch-item-go">' + icon('arrow') + '</span>' +
            '</a>';
    }

    function group(title, rowsHtml, count) {
        return '<div class="gsearch-group" role="group" aria-label="' + escapeHtml(title) + '">' +
            '<div class="gsearch-group-head">' + escapeHtml(title) +
            '<span class="gsearch-count">' + count + '</span></div>' +
            '<div class="gsearch-list">' + rowsHtml + '</div>' +
            '</div>';
    }

    function state(variant, iconName, title, text) {
        var art = variant === 'empty' && images.noResult
            ? '<img src="' + escapeHtml(images.noResult) + '" alt="">'
            : icon(iconName);

        return '<div class="gsearch-state gsearch-state--' + variant + '">' +
            '<span class="gsearch-state-ico">' + art + '</span>' +
            '<span class="gsearch-state-title">' + escapeHtml(title) + '</span>' +
            '<span class="gsearch-state-text">' + escapeHtml(text) + '</span>' +
            '</div>';
    }

    var GlobalSearch = {
        escape: escapeHtml,

        /** Called from the panel layout with its translated strings. */
        configure: function (options) {
            options = options || {};
            Object.assign(labels, options.labels || {});
            Object.assign(images, options.images || {});
        },

        skeleton: function (count) {
            var html = '';

            for (var i = 0; i < (count || 5); i++) {
                html += '<div class="gsearch-skel">' +
                    '<span class="gsearch-skel-ico"></span>' +
                    '<span class="gsearch-skel-body">' +
                    '<span class="gsearch-skel-line" style="width:' + (46 + (i % 3) * 14) + '%"></span>' +
                    '<span class="gsearch-skel-line" style="width:' + (26 + (i % 4) * 9) + '%"></span>' +
                    '</span></div>';
            }

            return html;
        },

        idle: function () {
            return state('idle', 'idle', labels.idleTitle, labels.idleText);
        },

        noResult: function (note) {
            return (note ? '<span class="gsearch-note">' + escapeHtml(note) + '</span>' : '') +
                state('empty', 'empty', labels.noResultTitle, labels.noResultText);
        },

        error: function () {
            return state('error', 'error', labels.errorTitle, labels.errorText);
        },

        /**
         * @param {Array}  items   Search payload rows.
         * @param {string} keyword Raw keyword, highlighted inside each row.
         * @param {Object} extra   {note, footnote} — optional contextual hints.
         */
        results: function (items, keyword, extra) {
            extra = extra || {};

            var pages = [];
            var records = [];

            items.forEach(function (item) {
                var isRecord = item.data_from === 'database';
                var separator = String(item.fullRoute).indexOf('?') === -1 ? '?' : '&';

                var html = row({
                    kind: isRecord ? 'record' : 'page',
                    href: item.fullRoute + separator + 'keyword=' + encodeURIComponent(keyword),
                    routeName: item.routeName,
                    uri: item.URI,
                    fullRoute: item.fullRoute,
                    titleHtml: highlight(item.routeName, keyword),
                    pathHtml: highlight(item.URI, keyword)
                });

                (isRecord ? records : pages).push(html);
            });

            var html = extra.note ? '<span class="gsearch-note">' + escapeHtml(extra.note) + '</span>' : '';

            if (pages.length) {
                html += group(labels.pages, pages.join(''), pages.length);
            }

            if (records.length) {
                html += group(labels.records, records.join(''), records.length);
            }

            if (extra.footnote) {
                html += '<span class="gsearch-note">' + escapeHtml(extra.footnote) + '</span>';
            }

            return html;
        },

        recent: function (items) {
            var rows = items.map(function (item) {
                return row({
                    kind: 'recent',
                    href: item.route_full_url,
                    routeName: item.route_name,
                    uri: item.route_uri,
                    fullRoute: item.route_full_url,
                    titleHtml: escapeHtml(item.route_name),
                    pathHtml: escapeHtml(item.route_uri)
                });
            }).join('');

            return group(labels.recent, rows, items.length);
        },

        /** Toggles the indeterminate progress bar in the search bar. */
        loading: function (isLoading) {
            var shell = document.querySelector('#staticBackdrop .gsearch');

            if (shell) {
                shell.classList.toggle('is-loading', !!isLoading);
            }
        }
    };

    /* ----------------------------------------------------------------------
       Keyboard navigation
       ---------------------------------------------------------------------- */

    function items() {
        var results = document.getElementById('searchResults');

        return results ? Array.prototype.slice.call(results.querySelectorAll('.gsearch-item')) : [];
    }

    function activeIndex(list) {
        for (var i = 0; i < list.length; i++) {
            if (list[i].classList.contains('is-active')) {
                return i;
            }
        }

        return -1;
    }

    function activate(element) {
        var input = document.getElementById('searchInput');

        items().forEach(function (item) {
            item.classList.remove('is-active');
            item.removeAttribute('aria-selected');
        });

        if (!element) {
            if (input) {
                input.removeAttribute('aria-activedescendant');
            }

            return;
        }

        element.classList.add('is-active');
        element.setAttribute('aria-selected', 'true');
        element.scrollIntoView({ block: 'nearest' });

        if (input) {
            element.id = element.id || 'gsearch-opt-' + items().indexOf(element);
            input.setAttribute('aria-activedescendant', element.id);
        }
    }

    function move(step) {
        var list = items();

        if (!list.length) {
            return;
        }

        var next = activeIndex(list) + step;

        if (next < 0) {
            next = list.length - 1;
        } else if (next >= list.length) {
            next = 0;
        }

        activate(list[next]);
    }

    function isMac() {
        var platform = (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || '';

        return /mac|iphone|ipad|ipod/i.test(platform);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('staticBackdrop');
        var input = document.getElementById('searchInput');
        var clear = document.getElementById('gsearchClear');
        var results = document.getElementById('searchResults');

        if (!modal || !input) {
            return;
        }

        // Mac users press ⌘K, so label the trigger buttons accordingly.
        if (isMac()) {
            document.querySelectorAll('.ctrlplusk, .v2-topbar-search kbd').forEach(function (node) {
                node.textContent = '⌘K';
            });
        }

        function syncClear() {
            if (clear) {
                clear.hidden = input.value.length === 0;
            }
        }

        input.addEventListener('input', syncClear);

        if (clear) {
            clear.addEventListener('click', function () {
                input.value = '';
                syncClear();
                input.focus();
                // Let the panel layout refresh the panel back to recent searches.
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }

        if (results) {
            // First row is pre-selected so Enter always has a sensible target.
            if (window.MutationObserver) {
                new MutationObserver(function () {
                    var list = items();

                    if (list.length && activeIndex(list) === -1) {
                        activate(list[0]);
                    }
                }).observe(results, { childList: true, subtree: true });
            }

            results.addEventListener('mousemove', function (event) {
                var item = event.target.closest ? event.target.closest('.gsearch-item') : null;

                if (item && !item.classList.contains('is-active')) {
                    activate(item);
                }
            });
        }

        modal.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                move(1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                move(-1);
            } else if (event.key === 'Enter') {
                var list = items();
                var current = list[activeIndex(list)];

                if (current) {
                    event.preventDefault();
                    current.click();
                }
            }
        });

        $(modal).on('show.bs.modal', function () {
            document.body.classList.add('gsearch-open');
            syncClear();
        });

        $(modal).on('shown.bs.modal', function () {
            input.focus();
            input.select();
        });

        $(modal).on('hidden.bs.modal', function () {
            document.body.classList.remove('gsearch-open');
            GlobalSearch.loading(false);
            input.value = '';
            syncClear();
        });
    });

    window.GlobalSearch = GlobalSearch;
})(window, document);

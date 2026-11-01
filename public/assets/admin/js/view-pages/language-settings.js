/**
 * Language setup (admin/business-settings/language).
 *
 * Status toggling, the delete confirmation, the demo-build guard, and the
 * client-side search/status filter over the language cards.
 */
"use strict";

(function () {
    var grid = document.getElementById('lang-set-grid');
    var emptyState = document.getElementById('lang-set-empty');
    var searchInput = document.getElementById('lang-set-search');
    var filterButtons = Array.prototype.slice.call(document.querySelectorAll('.lang-set-filter'));
    var cards = grid ? Array.prototype.slice.call(grid.querySelectorAll('.lang-set-item')) : [];
    var activeFilter = 'all';

    /* ------------------------------------------------------------ filtering */

    function applyFilters() {
        if (!grid) {
            return;
        }

        var term = (searchInput ? searchInput.value : '').trim().toLowerCase();
        var visible = 0;

        cards.forEach(function (card) {
            var matchesStatus = activeFilter === 'all' || card.dataset.status === activeFilter;
            var matchesTerm = term === '' || (card.dataset.search || '').indexOf(term) !== -1;
            var show = matchesStatus && matchesTerm;

            card.hidden = !show;
            if (show) {
                visible++;
            }
        });

        if (emptyState) {
            emptyState.classList.toggle('is-visible', visible === 0);
        }
        grid.style.display = visible === 0 ? 'none' : '';
    }

    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            activeFilter = button.dataset.filter || 'all';

            filterButtons.forEach(function (other) {
                var isActive = other === button;
                other.classList.toggle('is-active', isActive);
                other.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            applyFilters();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
        // Enter would submit the surrounding form on some themes; the filter is
        // live, so there is nothing to submit.
        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    }
})();

$(document).ready(function () {
    var strings = {};

    try {
        strings = JSON.parse($('#lang-set-strings').text()) || {};
    } catch (e) {
        strings = {};
    }

    // Select2 on #country-code is initialised globally by app-blade/admin.js —
    // re-initialising it here would stack a second container on the field.

    $('.delete').on('click', function (e) {
        e.preventDefault();
        $('#delete-link').attr('href', $(this).attr('id'));
        $('#delete-modal').modal('show');
    });

    /* The default language's toggle is disabled, so it fires no click of its
       own — bind the wrapping label instead, otherwise the control just looks
       broken. */
    $('.lang-set-toggle.is-locked').on('click', function (e) {
        e.preventDefault();
        toastr.warning(strings.default_locked);
    });

    $('.call-demo-lang').on('click', function (e) {
        e.preventDefault();

        var key = $(this).data('key');
        var mode = $(this).data('env-mode');

        if ((key === 0 || key === 1) && mode === 'demo') {
            toastr.info(strings.demo_blocked, {
                CloseButton: true,
                ProgressBar: true
            });
        }
    });

    $('.status-update').on('click', function () {
        var $input = $(this);

        $.get({
            url: $input.data('url'),
            data: {
                code: $input.data('id')
            },
            success: function (response) {
                // update_status() answers {error: 403} instead of an HTTP error
                // when the code turns out to be the default one.
                if (response && response.error) {
                    $input.prop('checked', !$input.prop('checked'));
                    toastr.warning(strings.default_locked);
                    return;
                }

                toastr.success(strings.status_updated);
                setTimeout(function () {
                    window.location.href = strings.index_url;
                }, 1200);
            },
            error: function () {
                $input.prop('checked', !$input.prop('checked'));
                toastr.error(strings.request_failed);
            }
        });
    });
});

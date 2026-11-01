"use strict";

/* --------------------------------------------------------------------------
   Tax report query card (admin, Rental and Service all render the same card)

   Two things every copy of the card needs:

   1. The fixed-choice selects go through select2 like the rest of the panel's
      dropdowns, with the search box off — two options never need one.

   2. An AJAX select2 positions its dropdown before the first response arrives,
      and that early measurement leaves it pinned to the top of the page: the
      list appears up there, then drops under the field once the results land.
      The wait is as long as the request, so it reads as the dropdown jumping
      up and then back down. Re-running select2's own reposition after the
      dropdown is open puts it under the field on the first frame instead.

   The dropdown deliberately stays attached to <body>. `.txr-query` is
   `overflow: hidden`, so a dropdown parented inside the card is cut off at the
   card's edge — with eight tax rates listed, most of them.
   -------------------------------------------------------------------------- */

window.TaxReportFilters = (function ($) {
    // select2 binds its reposition handler under a `.select2.<id>` namespace,
    // so triggering the shared part of that namespace reaches it and nothing
    // else bound to window resize on the page.
    function anchorDropdown($select) {
        $select.on('select2:open', function () {
            $(window).trigger('resize.select2');
        });
    }

    // The card's own scripts run after the panel-wide `.js-select2-custom`
    // pass, so anything re-initialised here is torn down first. The check is
    // on the class select2 puts on the original <select>, not on
    // `.data('select2')`: select2 keeps its instance in its own store, so the
    // jQuery entry can be missing on a select that is already set up — leaving
    // the first widget in place and the second one, the one carrying this
    // card's AJAX config, detached and never used.
    function init($select, options) {
        if (!$select || !$select.length) {
            return;
        }

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }

        $.HSCore.components.HSSelect2.init($select, options || {});
        anchorDropdown($select);
    }

    return {
        anchorDropdown: anchorDropdown,

        // Date range type / how to calculate tax: a handful of fixed options.
        initFixedSelect: function ($select) {
            init($select, { minimumResultsForSearch: Infinity });
        },

        // The tax rate pickers, which search the tax list over AJAX.
        initTaxSelect: function ($select, options) {
            init($select, options);
        },
    };
})(jQuery);

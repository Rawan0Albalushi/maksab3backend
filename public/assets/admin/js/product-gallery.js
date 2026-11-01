/* ==========================================================================
   Product Gallery — behaviour for the detail drawer.

   Loaded from admin-views/product/product_gallery.blade.php and its vendor
   twin. Everything is delegated from `document`, because the drawer body is
   fetched over AJAX and re-injected on every open — the layout's own
   `see-more_pragraph` and `.tabs-slide` scripts bind once on DOM ready and so
   never see that markup at all, which is why the drawer carries its own here.
   ========================================================================== */

(function ($) {
    'use strict';

    var $lastTrigger = null;

    // Opening is also handled by admin.js / offcanvas.js, but both bind
    // directly on DOM ready. Doing it here as well keeps the panel working if a
    // card is ever re-rendered, and the class changes are idempotent.
    $(document).on('click', '.pgal .data-info-show', function (e) {
        e.preventDefault();
        $lastTrigger = $(this);

        $($(this).data('target')).addClass('open');
        $('#offcanvasOverlay').addClass('show');
        $('body').addClass('modal-open');
    });

    function closeDrawer() {
        var wasOpen = $('.custom-offcanvas.open').length > 0;

        $('.custom-offcanvas').removeClass('open');
        $('#offcanvasOverlay').removeClass('show');
        $('body').removeClass('modal-open');

        if (wasOpen && $lastTrigger && $lastTrigger.is(':visible')) {
            $lastTrigger.trigger('focus');
        }
        $lastTrigger = null;
    }

    $(document).on('click', '.pg-drawer__close, .offcanvas-close, #offcanvasOverlay', function (e) {
        e.preventDefault();
        closeDrawer();
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('.custom-offcanvas.open').length) {
            closeDrawer();
        }
    });

    // Thumbnail strip -> main shot.
    $(document).on('click', '.pg-thumb', function () {
        var src = $(this).data('src');
        if (!src) {
            return;
        }

        $(this).addClass('is-active').siblings('.pg-thumb').removeClass('is-active');
        $(this).closest('.pg-hero').find('.pg-hero__img').attr('src', src);
    });

    // Description clamp. The toggle is only rendered when the text is long
    // enough to be clamped, so no measuring is needed here.
    $(document).on('click', '.pg-desc__toggle', function () {
        var $desc = $(this).closest('.pg-desc');
        var open = $desc.toggleClass('is-open').hasClass('is-open');

        $(this).text($(this).data(open ? 'less' : 'more'));
    });

    // Move focus into the panel once its content has landed.
    $(document).on('pgal:drawer-loaded', function () {
        $('.custom-offcanvas.open').find('.pg-drawer__close').trigger('focus');
    });
})(jQuery);

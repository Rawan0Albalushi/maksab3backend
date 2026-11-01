<script>
    "use strict";

    $(function () {
        $('.bundle-items-popover').each(function () {
            const source = $(this).data('target');

            $(this).popover({
                html: true,
                trigger: 'hover focus',
                placement: 'right',
                container: 'body',
                template: '<div class="popover bundle-items-popover-body" role="tooltip">'
                    + '<div class="arrow"></div><div class="popover-body"></div></div>',
                content: function () {
                    return $(source).html();
                },
            });
        });
    });

    $(document).on('click', '.bundle-detail', function () {
        const target = $('#offcanvas__bundle_detail');

        $('#bundle-detail-body').html(
            '<div class="d-flex align-items-center justify-content-center h-100">' +
            '<div class="spinner-border text--primary" role="status"></div></div>'
        );
        target.addClass('open');
        $('#offcanvasOverlay').addClass('show');
        $('body').addClass('modal-open');

        $.get($(this).data('url'), function (html) {
            $('#bundle-detail-body').html(html);
        }).fail(function () {
            toastr.error('{{ translate('Something went wrong') }}');
            target.removeClass('open');
            $('#offcanvasOverlay').removeClass('show');
            $('body').removeClass('modal-open');
        });
    });

    $(document).on('click', '.offcanvas-close, #offcanvasOverlay', function () {
        $('.custom-offcanvas').removeClass('open');
        $('#offcanvasOverlay').removeClass('show');
        $('body').removeClass('modal-open');
    });

</script>

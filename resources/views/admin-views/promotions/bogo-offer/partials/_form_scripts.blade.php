<script>
    "use strict";

    // $formUrl / $redirectUrl / $successMessage are supplied by the including view.
    $(document).on('input', '[data-counter]', function () {
        $('#' + $(this).data('counter')).text($(this).val().length);
    });

    // A reset restores the fields but not the counts under them - those only follow input -
    // so they are recomputed once the browser has put the original values back.
    $('#bogo-offer-form').on('reset', function () {
        const $form = $(this);

        setTimeout(function () {
            $form.find('[data-counter]').each(function () {
                $('#' + $(this).data('counter')).text($(this).val().length);
            });
        }, 0);
    });

    // Keep the two datetime fields consistent with each other.
    $('#start_date').on('change', function () {
        $('#end_date').attr('min', $(this).val());
    });
    $('#end_date').on('change', function () {
        $('#start_date').attr('max', $(this).val());
    });

    $('#bogo-offer-form').on('submit', function (e) {
        e.preventDefault();

        const $button = $(this).find('button[type="submit"]');
        $button.prop('disabled', true);

        let formData = new FormData(this);
        $.ajaxSetup({headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}});

        $.post({
            url: formUrl,
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            success: function (data) {
                if (data.errors) {
                    $button.prop('disabled', false);
                    data.errors.forEach(err => toastr.error(err.message, {CloseButton: true, ProgressBar: true}));
                } else {
                    toastr.success(successMessage, {CloseButton: true, ProgressBar: true});
                    setTimeout(() => location.href = redirectUrl, 2000);
                }
            },
            error: function (xhr) {
                $button.prop('disabled', false);
                const errors = xhr.responseJSON?.errors || [];
                errors.length
                    ? errors.forEach(err => toastr.error(err.message))
                    : toastr.error('{{ translate('messages.Something went wrong') }}');
            }
        });
    });
</script>

<script>
    "use strict";

    // Deletion uses a page local confirm rather than the shared .form-alert
    // SweetAlert, matching Area Setup.
    let pendingDeleteFormId = null;

    $(document).on('click', '.weight-delete-btn', function () {
        pendingDeleteFormId = $(this).data('id');
        $('#weight-delete-modal').modal('show');
    });

    $(document).on('click', '#weight-delete-confirm', function () {
        if (pendingDeleteFormId) {
            $('#' + pendingDeleteFormId).submit();
        }
    });

    // offcanvas.js only slides the panel open; the form itself is pulled in
    // here so add and edit can share one panel.
    $(document).on('click', '.offcanvas-trigger', function () {
        let action = $(this).data('action');
        let url = action === 'edit' ? $(this).data('url') : '{{ route('admin.business-settings.zone.weight.create') }}';
        fetch_form(url);
    });

    $(document).on('click', '.offcanvas-close, #offcanvasOverlay', function () {
        $('.custom-offcanvas').removeClass('open');
        $('#offcanvasOverlay').removeClass('show');
    });

    function fetch_form(url) {
        $.ajax({
            url: url,
            type: 'get',
            beforeSend: function () {
                $('#data-view').empty();
                $('#loading').show();
            },
            success: function (data) {
                $('#data-view').append(data.view);
            },
            complete: function () {
                $('#loading').hide();
            }
        });
    }

    // The language tabs live inside the injected markup, so they need a
    // delegated handler instead of the page level one.
    $(document).on('click', '#data-view .lang_link', function (e) {
        e.preventDefault();
        let lang = this.id.replace('-link', '');
        $('#data-view .lang_link').removeClass('active');
        $(this).addClass('active');
        $('#data-view .lang_form').addClass('d-none');
        $('#data-view').find('#' + lang + '-form').removeClass('d-none');
    });

    $(document).on('submit', '#weight-offcanvas-form', function (e) {
        e.preventDefault();
        let form = $(this);
        let submitBtn = form.find('button[type="submit"]');
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $.post({
            url: form.attr('action'),
            data: new FormData(this),
            cache: false,
            contentType: false,
            processData: false,
            beforeSend: function () {
                submitBtn.prop('disabled', true);
            },
            success: function (data) {
                if (data.errors) {
                    submitBtn.prop('disabled', false);
                    for (let i = 0; i < data.errors.length; i++) {
                        toastr.error(data.errors[i].message, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    }
                } else {
                    toastr.success(data.success, {
                        CloseButton: true,
                        ProgressBar: true
                    });
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                }
            },
            error: function () {
                submitBtn.prop('disabled', false);
            }
        });
    });
</script>

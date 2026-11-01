<script>
    "use strict";

    let bundleConfirmAction = null;

    function openBundleConfirm(trigger, action) {
        bundleConfirmAction = action;

        $('#bundle-confirm-title').text(trigger.data('title'));
        $('#bundle-confirm-body').text(trigger.data('message'));
        $('#bundleConfirmModal').modal('show');
    }

    $('#bundle-confirm-ok').on('click', function () {
        $('#bundleConfirmModal').modal('hide');

        if (bundleConfirmAction) {
            bundleConfirmAction();
        }
    });

    $('#bundleConfirmModal').on('hidden.bs.modal', function () {
        bundleConfirmAction = null;
        $('.bundle-status-toggle').each(function () {
            this.checked = $(this).data('checked');
        });
    });

    $(document).on('click', '.bundle-edit-trigger', function () {
        const url = $(this).data('url');

        openBundleConfirm($(this), function () {
            window.location.href = url;
        });
    });

    $(document).on('click', '.bundle-delete-trigger', function () {
        const form = $('#' + $(this).data('form'));

        openBundleConfirm($(this), function () {
            form.trigger('submit');
        });
    });

    $(document).on('click', '.bundle-status-toggle', function (event) {
        event.preventDefault();

        const form = $('#' + $(this).data('form'));

        openBundleConfirm($(this), function () {
            form.trigger('submit');
        });
    });
</script>

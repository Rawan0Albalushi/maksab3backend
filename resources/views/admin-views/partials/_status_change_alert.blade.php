{{-- Shared confirmation for status toggles. Mirrors the handler used by shift/list and
     vehicle/view: the toggle carries data-url and data-message, the click is cancelled so
     the switch does not flip until the change is actually confirmed. --}}
<script>
    "use strict";

    $(document).on('click', '.status_change_alert', function (event) {
        event.preventDefault();

        const url = $(this).data('url');
        const message = $(this).data('message');

        Swal.fire({
            title: '{{ translate('Are you sure?') }}',
            text: message,
            type: 'warning',
            showCancelButton: true,
            cancelButtonColor: 'default',
            confirmButtonColor: '#FC6A57',
            cancelButtonText: '{{ translate('No') }}',
            confirmButtonText: '{{ translate('Yes') }}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                location.href = url;
            }
        });
    });
</script>

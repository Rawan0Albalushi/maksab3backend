{{-- Strings for `public/assets/admin/js/status-toggle.js`, which is a static
     asset and so cannot call translate() itself. Included from the admin and
     vendor layouts, immediately before that script. --}}

<script>
    window.statusToggleLang = {
        confirm_title: @json(translate('messages.Are you sure?')),
        yes: @json(translate('messages.Yes')),
        no: @json(translate('messages.No')),
        on: @json(translate('messages.Active')),
        off: @json(translate('messages.Inactive')),
        done: @json(translate('Updated successfully')),
        failed: @json(translate('messages.Status update failed'))
    };
</script>

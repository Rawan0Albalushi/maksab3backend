{{-- Strings for `public/assets/admin/js/priority-select.js`, which is a static
     asset and so cannot call translate() itself. Included from the admin and
     vendor layouts, immediately before that script.

     Keep every value comma-free: @json takes its argument by splitting the
     directive's parentheses on commas, so a comma inside the string silently
     drops the escaping flags. --}}

<script>
    window.prioritySelectLang = {
        done: @json(translate('Updated successfully')),
        failed: @json(translate('messages.Priority update failed'))
    };
</script>

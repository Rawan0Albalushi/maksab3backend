<script>
    "use strict";

    /* Reveal / hide a secret input (API keys, client secrets, SMTP passwords). */
    $(document).on('click', '.tps-toggle-secret', function () {
        const $button = $(this);
        const $input = $($button.data('target'));

        if (!$input.length) {
            return;
        }

        const hidden = $input.attr('type') === 'password';
        $input.attr('type', hidden ? 'text' : 'password');
        $button.find('i').attr('class', hidden ? 'tio-invisible' : 'tio-visible');
        $button.attr('aria-label', hidden
            ? '{{ translate('Hide value') }}'
            : '{{ translate('Show value') }}');
    });

    /* Copy the value of an input or a plain text node to the clipboard. */
    $(document).on('click', '.tps-copy', function () {
        const $source = $($(this).data('target'));

        if (!$source.length) {
            return;
        }

        const value = $source.is('input, textarea') ? $source.val() : $source.text().trim();

        if (!value) {
            return;
        }

        const $temp = $('<textarea>').css({position: 'fixed', opacity: 0}).val(value).appendTo('body');
        $temp.select();
        document.execCommand('copy');
        $temp.remove();

        toastr.success("{{ translate('Copied to clipboard') }}");
    });
</script>

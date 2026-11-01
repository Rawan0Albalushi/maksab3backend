{{-- Shown when the server rejects a save with code "conflict", i.e. the schedule collides
     with an existing happy hour in the same module. A toast is too easy to miss for
     something that needs the schedule reworked before the form can be submitted again.

     Bundled SweetAlert is 7.33.1: customClass is a plain string there and buttons are
     classed with confirmButtonClass. The {popup, confirmButton} object form is v9+. --}}
<style>
    .swal2-popup.hh-overlap-popup {
        border-radius: 16px;
        padding: 32px 40px 40px;
        max-width: 560px;
    }
    .hh-overlap-popup .swal2-close {
        width: 38px;
        height: 38px;
        margin: 6px 6px 0 0;
        font-size: 24px;
        line-height: 38px;
        border-radius: 50%;
        background: var(--section-bg2);
        color: var(--title-clr);
        opacity: .75;
    }
    .hh-overlap-popup .swal2-content {
        padding: 0;
    }
    .hh-overlap-popup .swal2-actions {
        margin: 32px 0 0;
    }
    .hh-overlap__icon {
        margin-bottom: 28px;
    }
    .hh-overlap__title {
        font-size: 26px;
        font-weight: 700;
        color: var(--title-clr);
        margin-bottom: 14px;
    }
    .hh-overlap__text {
        font-size: 17px;
        line-height: 1.5;
        opacity: .7;
        margin-bottom: 0;
    }
    .hh-overlap__detail {
        display: block;
        margin-top: 12px;
        font-size: 14px;
        opacity: .6;
    }
    .hh-overlap-confirm {
        min-width: 260px;
        height: 58px;
        font-size: 17px;
        border-radius: 8px;
    }
</style>

<script>
    "use strict";

    function showOverlapAlert(detail) {
        // detail is the server's precise line ("Time conflict on 2029-02-01 in this module"),
        // kept subordinate to the designed copy so a long custom schedule still says which
        // date to go and fix.
        const extra = detail
            ? `<span class="hh-overlap__detail">${$('<div>').text(detail).html()}</span>`
            : '';

        Swal.fire({
            html: `
                <div class="hh-overlap__icon">
                    <svg width="112" height="100" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 3.5c-.6 0-1.15.32-1.45.84L2.7 18.16A1.67 1.67 0 0 0 4.15 20.7h15.7a1.67 1.67 0 0 0 1.45-2.54L13.45 4.34A1.67 1.67 0 0 0 12 3.5Z" fill="#DC4B44"/>
                        <path d="M12 8.75v5.1" stroke="#fff" stroke-width="2.1" stroke-linecap="round"/>
                        <circle cx="12" cy="17.35" r="1.2" fill="#fff"/>
                    </svg>
                </div>
                <h4 class="hh-overlap__title">{{ translate('Happy hour schedule overlap') }}</h4>
                <p class="hh-overlap__text">
                    {{ translate('This happy hour overlaps with another please change the schedule to fix it') }}
                    ${extra}
                </p>`,
            showCloseButton: true,
            buttonsStyling: false,
            customClass: 'hh-overlap-popup',
            confirmButtonClass: 'btn btn--primary hh-overlap-confirm',
            confirmButtonText: '{{ translate('messages.Okay') }}',
        });
    }

    // Returns true when the response was an overlap, so callers can skip their own toast.
    function handleScheduleConflict(errors) {
        const conflict = (errors || []).find(e => e.code === 'conflict');

        if (conflict) {
            showOverlapAlert(conflict.message);

            return true;
        }

        return false;
    }
</script>

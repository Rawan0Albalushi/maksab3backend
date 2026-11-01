<style>
    .logout-swal-backdrop.swal2-container,
    .logout-swal-backdrop.swal2-container.swal2-shown {
        background-color: rgba(21, 28, 40, .5);
        -webkit-backdrop-filter: blur(3px);
        backdrop-filter: blur(3px);
    }

    .swal2-popup.logout-swal {
        width: 100% !important;
        max-width: 400px !important;
        padding: 36px 32px 28px !important;
        border-radius: 20px !important;
        background: #fff;
        box-shadow: 0 20px 48px -12px rgba(17, 24, 39, .25), 0 0 0 1px rgba(17, 24, 39, .04);
        font-family: "Inter", sans-serif;
    }

    .logout-swal .swal2-title {
        display: none !important;
    }

    .logout-swal .swal2-content {
        padding: 0 !important;
        margin: 0;
    }

    .logout-swal .swal2-close {
        top: 14px !important;
        inset-inline-end: 14px !important;
        inset-inline-start: auto !important;
        width: 30px !important;
        height: 30px !important;
        border-radius: 50% !important;
        background: transparent !important;
        color: #9aa4b2 !important;
        font-family: inherit !important;
        font-size: 20px !important;
        font-weight: 400 !important;
        line-height: 1 !important;
        transition: background-color .15s ease, color .15s ease;
    }

    .logout-swal .swal2-close:hover {
        background: #f1f4f7 !important;
        color: var(--title-clr, #334257) !important;
    }

    .logout-swal__icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 64px;
        height: 64px;
        margin: 0 auto 22px;
        border-radius: 50%;
        background: #fff1f3;
        color: #e11d48;
        box-shadow: 0 0 0 8px #fff7f8;
    }

    .logout-swal__icon svg {
        width: 28px;
        height: 28px;
    }

    [dir="rtl"] .logout-swal__icon svg {
        transform: scaleX(-1);
    }

    .logout-swal__title {
        margin: 0 0 8px;
        color: var(--title-clr, #334257);
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.35;
        letter-spacing: -.01em;
        text-wrap: balance;
    }

    .logout-swal__text {
        max-width: 306px;
        margin: 0 auto;
        color: #6b7280;
        font-size: .875rem;
        font-weight: 400;
        line-height: 1.6;
        text-wrap: balance;
    }

    .logout-swal .swal2-actions {
        display: flex;
        flex-wrap: nowrap;
        gap: 12px;
        width: 100%;
        margin: 28px 0 0 !important;
    }

    /* Flex, not inline text: SweetAlert's loading spinner is an inline-block ::after that
       otherwise sits on the text baseline and shoves the label upward. */
    .logout-swal .swal2-actions button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 1 1 0;
        min-width: 0;
        height: 46px;
        line-height: 1;
        margin: 0 !important;
        padding: 0 16px;
        border: 1px solid transparent;
        border-radius: 10px;
        font-family: inherit;
        font-size: .9375rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
    }

    .logout-swal .swal2-actions button:focus {
        outline: none;
    }

    .logout-swal .swal2-actions button[disabled] {
        opacity: .65;
        cursor: default;
    }

    .logout-swal .swal2-actions button.swal2-cancel {
        border-color: #e3e8ef;
        background: #fff;
        color: var(--title-clr, #334257);
    }

    .logout-swal .swal2-actions button.swal2-cancel:hover {
        border-color: #d5dce6;
        background: #f5f7fa;
    }

    .logout-swal .swal2-actions button.swal2-cancel:focus {
        box-shadow: 0 0 0 3px rgba(51, 66, 87, .14);
    }

    .logout-swal .swal2-actions button.swal2-confirm {
        background: #e11d48;
        color: #fff;
        box-shadow: 0 6px 14px -6px rgba(225, 29, 72, .6);
    }

    .logout-swal .swal2-actions button.swal2-confirm:hover {
        background: #be123c;
    }

    .logout-swal .swal2-actions button.swal2-confirm:focus {
        box-shadow: 0 0 0 3px rgba(225, 29, 72, .28);
    }

    .swal2-popup.logout-swal .swal2-actions.swal2-loading .swal2-confirm:not(.swal2-styled)::after {
        flex: 0 0 auto;
        box-sizing: border-box;
        width: 14px;
        height: 14px;
        margin-left: 0;
        margin-inline-start: 8px;
        border-width: 2px;
        border-color: rgba(255, 255, 255, .55);
        border-inline-end-color: transparent;
        box-shadow: none;
    }

    .swal2-popup.logout-swal.swal2-show {
        animation: logout-swal-in .26s cubic-bezier(.22, 1, .36, 1);
    }

    .swal2-popup.logout-swal.swal2-hide {
        animation: logout-swal-out .16s ease forwards;
    }

    @keyframes logout-swal-in {
        from {
            opacity: 0;
            transform: translateY(10px) scale(.97);
        }
        to {
            opacity: 1;
            transform: none;
        }
    }

    @keyframes logout-swal-out {
        to {
            opacity: 0;
            transform: translateY(4px) scale(.98);
        }
    }

    @media screen and (max-width: 575px) {
        .swal2-popup.logout-swal {
            padding: 30px 22px 24px !important;
            border-radius: 16px !important;
        }
        .logout-swal .swal2-actions {
            flex-direction: column-reverse;
            gap: 10px;
        }
        .logout-swal .swal2-actions button {
            flex: none;
            width: 100%;
        }
    }
</style>

<script>
    "use strict";

    $(document).on('click', '.log-out', function (event) {
        event.preventDefault();

        Swal.fire({
            html: `
                <div class="logout-swal__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </div>
                <h2 class="logout-swal__title">{{ translate('Do you want to sign out?') }}</h2>
                <p class="logout-swal__text">{{ translate('You will be returned to the login screen. Any unsaved changes will be lost.') }}</p>`,
            customClass: 'logout-swal',
            customContainerClass: 'logout-swal-backdrop',
            buttonsStyling: false,
            showCancelButton: true,
            showCloseButton: true,
            reverseButtons: true,
            focusCancel: true,
            confirmButtonText: '{{ translate('Sign out') }}',
            cancelButtonText: '{{ translate('Cancel') }}',
            closeButtonAriaLabel: '{{ translate('Close') }}',
            showLoaderOnConfirm: true,
            allowOutsideClick: function () {
                return !Swal.isLoading();
            },
            preConfirm: function () {
                return new Promise(function () {
                    window.location.href = '{{ route('logout') }}';
                });
            }
        });
    });
</script>

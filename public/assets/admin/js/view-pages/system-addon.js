"use strict";

/* --------------------------------------------------------------------------
   System add-ons (admin/business-settings/system-addon)
   Routes and translated strings are read from #addonPage data attributes so
   this file stays free of Blade.

   Turning an add-on on or off never reloads the page: the card, its confirm
   modal, the counters and the Builder notice are all updated from the state
   the server reports back. Menus live in the layout, so a one-off "refresh to
   see the new menus" notice is shown instead of forcing a reload each time.
   -------------------------------------------------------------------------- */

$(document).ready(function () {
    const $page = $('#addonPage');

    if (!$page.length) {
        return;
    }

    const config = {
        uploadUrl: $page.data('upload-url'),
        publishUrl: $page.data('publish-url'),
        deleteUrl: $page.data('delete-url'),
        activatedMessage: $page.data('activated-message'),
        deactivatedMessage: $page.data('deactivated-message'),
        uploadErrorMessage: $page.data('upload-error-message'),
        invalidFileMessage: $page.data('invalid-file-message'),
        errorMessage: $page.data('error-message'),
    };

    const $dropzone = $('#addonDropzone');
    const $fileInput = $('#inputFile');
    const $fileCard = $('#progress-bar');
    const $uploadButton = $('#upload_theme');
    const $progressBar = $('#uploadProgress');
    const $progressLabel = $('#progress-label');

    const toastOptions = {
        CloseButton: true,
        ProgressBar: true
    };

    function csrfHeaders() {
        return {
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        };
    }

    function formatSize(bytes) {
        if (!bytes) {
            return '0 KB';
        }
        const units = ['B', 'KB', 'MB', 'GB'];
        let index = 0;
        let size = bytes;
        while (size >= 1024 && index < units.length - 1) {
            size /= 1024;
            index++;
        }
        return (index === 0 ? size : size.toFixed(1)) + ' ' + units[index];
    }

    function resetProgress() {
        $progressBar.css('width', '0%');
        $progressLabel.text('0%');
    }

    function clearSelectedFile() {
        $fileInput.val('');
        $fileCard.addClass('d--none');
        $dropzone.removeClass('is-dragover');
        $uploadButton.prop('disabled', true);
        resetProgress();
    }

    // ----- file selection ---------------------------------------------------
    $fileInput.on('change', function () {
        if (this.files && this.files[0]) {
            $('#name_of_file').text(this.files[0].name).attr('title', this.files[0].name);
            $('#size_of_file').text(formatSize(this.files[0].size));
            resetProgress();
            $fileCard.removeClass('d--none');
            $uploadButton.prop('disabled', false);
        } else {
            clearSelectedFile();
        }
    });

    $('#remove_file').on('click', clearSelectedFile);

    // ----- drag & drop ------------------------------------------------------
    // The drop is handled here rather than left to the file input underneath:
    // a drop that lands a pixel outside the input (or carries a folder) would
    // otherwise fall through to the browser, which opens or downloads it and
    // navigates away from the page.
    function acceptsDroppedFiles(event) {
        const transfer = event.originalEvent && event.originalEvent.dataTransfer;
        return !!transfer && $.inArray('Files', Array.prototype.slice.call(transfer.types || [])) !== -1;
    }

    // A dropped directory arrives as a typeless entry, so the extension is the
    // only thing that separates a real package from a folder.
    function isZipFile(file) {
        return /\.zip$/i.test(file.name);
    }

    function assignFile(file) {
        if (typeof DataTransfer !== 'function') {
            return false;
        }

        try {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            $fileInput[0].files = transfer.files;
        } catch (e) {
            return false;
        }

        $fileInput.trigger('change');
        return true;
    }

    $dropzone.on('dragenter dragover', function (e) {
        if (!acceptsDroppedFiles(e)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        e.originalEvent.dataTransfer.dropEffect = 'copy';
        $(this).addClass('is-dragover');
    });

    $dropzone.on('dragleave dragend', function () {
        $(this).removeClass('is-dragover');
    });

    $dropzone.on('drop', function (e) {
        if (!acceptsDroppedFiles(e)) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('is-dragover');

        const files = e.originalEvent.dataTransfer.files;

        if (!files || files.length !== 1 || !isZipFile(files[0]) || !assignFile(files[0])) {
            toastr.error(config.invalidFileMessage, toastOptions);
        }
    });

    // Anywhere else on the page a file drop is swallowed, so a near miss never
    // makes the browser leave the page to open the package.
    $(document).on('dragover drop', function (e) {
        if (acceptsDroppedFiles(e)) {
            e.preventDefault();
        }
    });

    // ----- upload -----------------------------------------------------------
    $('.zip-upload').on('click', function () {
        if (!$fileInput[0].files.length) {
            return;
        }

        $.ajaxSetup(csrfHeaders());

        $.ajax({
            type: 'POST',
            url: config.uploadUrl,
            data: new FormData(document.getElementById('theme_form')),
            processData: false,
            contentType: false,
            xhr: function () {
                let xhr = new window.XMLHttpRequest();

                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        let percentage = Math.round((e.loaded * 100) / e.total);
                        $progressBar.css('width', percentage + '%');
                        $progressLabel.text(percentage + '%');
                    }
                }, false);

                return xhr;
            },
            beforeSend: function () {
                $uploadButton.prop('disabled', true);
            },
            success: function (response) {
                if (response.status === 'error') {
                    resetProgress();
                    toastr.error(response.message, toastOptions);
                } else if (response.status === 'success') {
                    toastr.success(response.message, toastOptions);
                    // A new add-on means a new card, which only the server can
                    // render — this is the one flow that still reloads.
                    location.reload();
                }
            },
            error: function () {
                resetProgress();
                toastr.error(config.uploadErrorMessage, toastOptions);
            },
            complete: function () {
                $uploadButton.prop('disabled', false);
            },
        });
    });

    /* ----------------------------------------------------------------------
       In-place card state
       ---------------------------------------------------------------------- */

    function cardByPath(path) {
        return $page.find('[data-addon-card]').filter(function () {
            return $(this).data('path') === path;
        }).first();
    }

    function refreshCounts() {
        const $cards = $page.find('[data-addon-card]');
        const active = $cards.filter(function () {
            return $(this).find('[data-addon-switch]').attr('aria-checked') === 'true';
        }).length;

        $page.find('[data-addon-count="active"]').text(active);
        $page.find('[data-addon-count="inactive"]').text($cards.length - active);
    }

    function showRefreshNotice() {
        $page.find('[data-addon-refresh-notice]').removeClass('d-none');
    }

    // Swaps every part of a card that names its state, plus the copy inside its
    // confirm modal, so re-opening the modal never offers the old direction.
    function applyState($card, isPublished) {
        const key = $card.data('key');
        const $switch = $card.find('[data-addon-switch]');
        const $badge = $card.find('[data-addon-badge]');
        // The confirm modals are rendered outside .addon-page so a card's hover
        // transform can never become their containing block — hence the
        // document-wide lookup rather than $page.find().
        const $modal = $('[data-addon-modal]').filter(function () {
            return $(this).data('key') === key;
        }).first();

        $switch.attr('aria-checked', isPublished ? 'true' : 'false')
            .attr('title', $switch.data(isPublished ? 'title-on' : 'title-off'))
            .find('[data-addon-switch-text]')
            .text($switch.data(isPublished ? 'text-on' : 'text-off'));

        $badge.toggleClass('addon-badge--active', !!isPublished)
            .toggleClass('addon-badge--inactive', !isPublished)
            .find('[data-addon-badge-text]')
            .text($badge.data(isPublished ? 'text-on' : 'text-off'));

        // Deleting an add-on that is running would pull files out from under it,
        // so the button only exists while it is off.
        $card.find('[data-addon-delete-btn]').toggleClass('d-none', !!isPublished);

        if ($modal.length) {
            $modal.find('[data-addon-modal-title]').text($modal.data(isPublished ? 'title-on' : 'title-off'));
            $modal.find('[data-addon-modal-text]').text($modal.data(isPublished ? 'text-on' : 'text-off'));
            $modal.find('[data-addon-modal-confirm]').text($modal.data(isPublished ? 'confirm-on' : 'confirm-off'));
        }

        // Reaching "on" at all means the purchase code checked out, so the
        // licence chip can only ever move from "required" to "licensed".
        if (isPublished) {
            $card.find('[data-addon-licensed]').removeClass('d-none');
            $card.find('[data-addon-unlicensed]').addClass('d-none');
        }

        refreshCounts();
        showRefreshNotice();
    }

    function toggleBuilderAlert($card, isPublished) {
        if (($card.data('path') || '').split('/').pop() !== 'Builder') {
            return;
        }
        $page.find('[data-builder-alert]').toggleClass('d-none', !isPublished);
    }

    function announce($card, isPublished) {
        const template = isPublished ? config.activatedMessage : config.deactivatedMessage;
        const label = $card.data('label') || '';
        toastr.success(String(template || '') + ': ' + label, toastOptions);
    }

    function setBusy($card, busy) {
        $card.toggleClass('is-busy', busy);
        $card.find('[data-addon-switch]').prop('disabled', busy);
    }

    // Bootstrap 4 leaves a stuck backdrop and a scroll-locked body when a modal
    // is shown while another is still hiding, so hand over on hidden.bs.modal.
    function handOffModal($from, $to) {
        if (!$from.hasClass('show')) {
            $to.modal('show');
            return;
        }
        $from.one('hidden.bs.modal', function () {
            $to.modal('show');
        }).modal('hide');
    }

    $page.find('[data-addon-refresh-notice] [data-addon-refresh-now]').on('click', function () {
        location.reload();
    });

    // ----- activate / deactivate -------------------------------------------
    $(document).on('click', '.publish-addon', function () {
        const path = $(this).data('path');
        const $card = cardByPath(path);

        $.ajaxSetup(csrfHeaders());

        $.post({
            url: config.publishUrl,
            data: {
                'path': path
            },
            beforeSend: function () {
                setBusy($card, true);
            },
            success: function (data) {
                if (data.flag === 'inactive') {
                    // Never licensed on this domain — collect the purchase code
                    // first; the modal posts back here and reports the new state.
                    $('#activateData').empty().html(data.view);
                    $('#activatedThemeModal').modal('show');
                } else if (data.flag === 'requirements_missing') {
                    // Builder pre-flight blocked activation —
                    // the server returned the rendered modal body
                    $('#builderRequirementsData').empty().html(data.view);
                    $('#builderRequirementsModal').modal('show');
                } else if (data.status === 'demo') {
                    toastr.info(data.message, toastOptions);
                } else if (data.status === 'error') {
                    toastr.error(data.message, toastOptions);
                } else if (data.errors) {
                    for (let i = 0; i < data.errors.length; i++) {
                        toastr.error(data.errors[i].message, toastOptions);
                    }
                } else if (data.status === 'success') {
                    const isPublished = Number(data.is_published) === 1;
                    applyState($card, isPublished);
                    toggleBuilderAlert($card, isPublished);
                    announce($card, isPublished);
                }
            },
            error: function () {
                toastr.error(config.errorMessage, toastOptions);
            },
            complete: function () {
                setBusy($card, false);
            },
        });
    });

    // ----- license & activate (modal form) ----------------------------------
    $(document).on('submit', '#addon_activation_form', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $submit = $form.find('button[type="submit"]');
        const path = $form.find('input[name="path"]').val();
        const $card = cardByPath(path);

        $.ajax({
            type: 'POST',
            url: $form.attr('action'),
            data: $form.serialize(),
            beforeSend: function () {
                $submit.prop('disabled', true);
            },
            success: function (data) {
                if (data.status === 'redirect') {
                    // The purchase code did not check out — the activation server
                    // takes it from here.
                    window.location.href = data.url;
                    return;
                }

                if (data.flag === 'requirements_missing') {
                    $('#builderRequirementsData').empty().html(data.view);
                    handOffModal($('#activatedThemeModal'), $('#builderRequirementsModal'));
                    return;
                }

                if (data.status === 'demo') {
                    toastr.info(data.message, toastOptions);
                    return;
                }

                if (data.status === 'error') {
                    toastr.error(data.message, toastOptions);
                    return;
                }

                if (data.status === 'success') {
                    $('#activatedThemeModal').modal('hide');
                    applyState($card, true);
                    toggleBuilderAlert($card, true);
                    announce($card, true);
                }
            },
            error: function () {
                toastr.error(config.errorMessage, toastOptions);
            },
            complete: function () {
                $submit.prop('disabled', false);
            },
        });
    });

    // ----- delete -----------------------------------------------------------
    $(document).on('click', '.theme-delete', function () {
        const path = $(this).data('path');
        const $card = cardByPath(path);

        $.ajaxSetup(csrfHeaders());

        $.post({
            url: config.deleteUrl,
            data: {
                path
            },
            beforeSend: function () {
                setBusy($card, true);
            },
            success: function (data) {
                if (data.status === 'success') {
                    toastr.success(data.message, toastOptions);

                    const key = $card.data('key');
                    $('#shiftThemeModal_' + key + ', #deleteThemeModal_' + key).remove();
                    $card.closest('.col-12').remove();

                    // Only the server can render the empty state, so removing the
                    // last card is the one delete that still reloads.
                    if (!$page.find('[data-addon-card]').length) {
                        location.reload();
                        return;
                    }

                    refreshCounts();
                    showRefreshNotice();
                } else if (data.status === 'error') {
                    toastr.error(data.message, toastOptions);
                }
            },
            error: function () {
                toastr.error(config.errorMessage, toastOptions);
            },
            complete: function () {
                setBusy($card, false);
            },
        });
    });

    // activation() flashed pre-flight issues — open the modal once on load.
    if ($page.data('show-builder-requirements')) {
        $('#builderRequirementsModal').modal('show');
    }
});

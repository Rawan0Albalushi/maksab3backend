"use strict";

/*
 * Media gallery — admin/business-settings/file-manager
 *
 * The grid used to carry one Bootstrap modal per file; everything here drives a
 * single preview shell from the data attributes on the tile that was clicked.
 */
(function ($) {
    var strings = $.extend(
        {
            confirmTitle: "Are you sure?",
            confirmText: "This file will be removed from storage.",
            yes: "Yes",
            no: "No",
            copied: "File path copied successfully!",
        },
        window.fmgStrings || {}
    );

    var VIEW_KEY = "fmg-view";

    /* ------------------------------------------------------------------
       Grid / list switch — remembered per browser, so an admin who works in
       list mode is not put back in the grid on every folder they open.
       ------------------------------------------------------------------ */

    function applyView(view) {
        $(".fmg").toggleClass("is-list", view === "list");
        $("[data-fmg-view]").each(function () {
            $(this).toggleClass("is-active", $(this).data("fmg-view") === view);
        });
    }

    function storedView() {
        try {
            return localStorage.getItem(VIEW_KEY) || "grid";
        } catch (e) {
            return "grid";
        }
    }

    $(document).on("click", "[data-fmg-view]", function () {
        var view = $(this).data("fmg-view");
        applyView(view);
        try {
            localStorage.setItem(VIEW_KEY, view);
        } catch (e) {
            /* private browsing — the choice just does not survive the reload */
        }
    });

    /* ------------------------------------------------------------------
       Preview
       ------------------------------------------------------------------ */

    $(document).on("click", ".fmg-open", function () {
        var $tile = $(this).closest(".fmg-asset");

        if (!$tile.length) {
            return;
        }

        $("#fmg-preview-name").text($tile.attr("data-name"));
        $("#fmg-preview-path").text($tile.attr("data-file-path"));
        $("#fmg-preview-copy").attr("data-file-path", $tile.attr("data-file-path"));
        $("#fmg-preview-download").attr("href", $tile.attr("data-download"));
        $("#fmg-preview-delete")
            .attr("data-delete", $tile.attr("data-delete"))
            .attr("data-name", $tile.attr("data-name"));

        $("#fmg-preview-dims").text("");
        $("#fmg-preview-image")
            .attr("src", $tile.attr("data-url"))
            .attr("alt", $tile.attr("data-name"));

        $("#fmg-preview").modal("show");
    });

    /* Dimensions are free once the browser has the image, and they are the one
       thing an admin cannot read off the tile. `load` does not bubble, so this
       is bound to the element rather than delegated off the document. */
    $("#fmg-preview-image").on("load", function () {
        if (this.naturalWidth) {
            $("#fmg-preview-dims").text(this.naturalWidth + " \u00d7 " + this.naturalHeight);
        }
    });

    $("#fmg-preview").on("hidden.bs.modal", function () {
        $("#fmg-preview-image").attr("src", "");
    });

    /* ------------------------------------------------------------------
       Copy path
       ------------------------------------------------------------------ */

    $(document).on("click", ".copy-test", function () {
        copy_test($(this).attr("data-file-path"));
    });

    function copy_test(copyText) {
        if (!copyText) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(copyText);
        } else {
            var $scratch = $("<textarea>").val(copyText).appendTo("body").select();
            document.execCommand("copy");
            $scratch.remove();
        }

        toastr.success(strings.copied, {
            CloseButton: true,
            ProgressBar: true,
        });
    }

    /* ------------------------------------------------------------------
       Delete — one shared form, so the grid does not ship a form per tile
       ------------------------------------------------------------------ */

    $(document).on("click", ".fmg-delete", function () {
        var url = $(this).attr("data-delete");
        var name = $(this).attr("data-name");

        if (!url) {
            return;
        }

        Swal.fire({
            title: strings.confirmTitle,
            text: name ? name + " — " + strings.confirmText : strings.confirmText,
            type: "warning",
            showCancelButton: true,
            cancelButtonColor: "default",
            confirmButtonColor: "#FC6A57",
            cancelButtonText: strings.no,
            confirmButtonText: strings.yes,
            reverseButtons: true,
        }).then(function (result) {
            if (result.value) {
                $("#fmg-delete-form").attr("action", url).trigger("submit");
            }
        });
    });

    /* ------------------------------------------------------------------
       Upload staging
       ------------------------------------------------------------------ */

    var $imageInput = $("#customFileUpload");
    var $zipInput = $("#customZipFileUpload");
    var $staged = $("#files");
    var $submit = $("#fmg-upload-submit");

    function readableSize(bytes) {
        var units = ["B", "KB", "MB", "GB"];
        var unit = 0;

        while (bytes >= 1024 && unit < units.length - 1) {
            bytes /= 1024;
            unit++;
        }

        return (unit > 1 ? bytes.toFixed(1) : Math.round(bytes)) + " " + units[unit];
    }

    function refreshSubmitState() {
        var hasImages = $imageInput.length && $imageInput[0].files.length > 0;
        var hasZip = $zipInput.length && $zipInput[0].files.length > 0;
        $submit.prop("disabled", !hasImages && !hasZip);
    }

    function renderStaged() {
        if (!$imageInput.length) {
            return;
        }

        var files = $imageInput[0].files;
        $staged.empty();

        Array.prototype.forEach.call(files, function (file, index) {
            var $item = $(
                '<div class="fmg-staged__item">' +
                    '<img alt="">' +
                    '<span class="fmg-staged__name"></span>' +
                    '<button type="button" class="fmg-staged__drop" data-index="' +
                    index +
                    '" aria-label="remove"><i class="tio-clear"></i></button>' +
                    "</div>"
            );

            $item.find(".fmg-staged__name").text(file.name + " \u00b7 " + readableSize(file.size));
            $item.find("img").attr("alt", file.name);

            var reader = new FileReader();
            reader.onload = function (e) {
                $item.find("img").attr("src", e.target.result);
            };
            reader.readAsDataURL(file);

            $staged.append($item);
        });

        refreshSubmitState();
    }

    $imageInput.on("change", renderStaged);

    /* A FileList is read-only, so dropping one file means rebuilding the list
       out of the ones that are staying. */
    $(document).on("click", ".fmg-staged__drop", function () {
        var drop = parseInt($(this).attr("data-index"), 10);
        var transfer = new DataTransfer();

        Array.prototype.forEach.call($imageInput[0].files, function (file, index) {
            if (index !== drop) {
                transfer.items.add(file);
            }
        });

        $imageInput[0].files = transfer.files;
        renderStaged();
    });

    $zipInput.on("change", function () {
        var file = this.files[0];
        var $label = $("#zipFileLabel");
        $label.text(file ? file.name + " \u00b7 " + readableSize(file.size) : $label.attr("data-empty"));
        refreshSubmitState();
    });

    /* The file inputs cover their drop zones, so the browser handles the drop
       itself — these only paint the hover state. */
    $(".fmg-drop").each(function () {
        var $zone = $(this);

        $zone.on("dragenter dragover", function (e) {
            e.preventDefault();
            $zone.addClass("is-dragging");
        });

        $zone.on("dragleave drop", function () {
            $zone.removeClass("is-dragging");
        });
    });

    $("#fmg-upload-reset").on("click", function () {
        $staged.empty();
        setTimeout(refreshSubmitState, 0);
    });

    $("#fmg-upload-form").on("submit", function () {
        $submit.prop("disabled", true).find("i").attr("class", "tio-refresh");
    });

    $(function () {
        applyView(storedView());
        refreshSubmitState();
    });
})(jQuery);

"use strict";

(function () {
    var config = window.notificationFormConfig || {};
    var lang = config.lang || {};

    function paintFact(key, value) {
        $('[data-preview-fact="' + key + '"]').text(value || lang.notSet);
    }

    function paintCounter($field) {
        var $counter = $field.closest(".tps-field, .form-group").find(".text-counting");
        var max = $field.attr("maxlength");

        if ($counter.length && max) {
            $counter.text($field.val().length + "/" + max);
        }
    }

    function selectedTarget() {
        return $('#notification input[name="tergat"]:checked');
    }

    function paintTargetFact() {
        var $target = selectedTarget();

        paintFact("target", $target.length ? $.trim($target.data("label")) : "");
    }

    function paintZoneFact() {
        var $zone = $("#zone");

        paintFact("zone", $.trim($zone.find("option:selected").first().text()));
    }

    function paintMessage() {
        var title = $.trim($("#notification_title").val());
        var description = $.trim($("#description").val());

        $('[data-preview="title"]').text(title || lang.previewTitle);
        $('[data-preview="description"]').text(description || lang.previewBody);
    }

    function paintImage(source) {
        var $push = $("#notification-preview");

        if (source) {
            $push.addClass("has-image").find('[data-preview="image"]').attr("src", source);
            return;
        }

        $push.removeClass("has-image").find('[data-preview="image"]').attr("src", "");
    }

    function historyUrl(target) {
        var url = new URL(window.location.href);
        var search = $.trim($("#history_search").val());

        url.searchParams.delete("page");

        if (target && target !== "all") {
            url.searchParams.set("target", target);
        } else {
            url.searchParams.delete("target");
        }

        if (search) {
            url.searchParams.set("search", search);
        } else {
            url.searchParams.delete("search");
        }

        return url.toString();
    }

    $(function () {
        paintTargetFact();
        paintZoneFact();
        paintMessage();
    });

    $(document).on("change", '#notification input[name="tergat"]', paintTargetFact);

    $(document).on("change", "#zone", paintZoneFact);

    $(document).on("input", "#notification_title, #description", paintMessage);

    $(document).on("input", "#notification .tps-field .form-control", function () {
        paintCounter($(this));
    });

    $(document).on("input", "#notification-update-offcanvas .form-control", function () {
        paintCounter($(this));
    });

    $(document).on("change", "#image-input", function (event) {
        var file = event.target.files[0];

        if (!file) {
            paintImage("");
            return;
        }

        var reader = new FileReader();

        reader.onload = function (e) {
            paintImage(e.target.result);
        };

        reader.readAsDataURL(file);
    });

    $(document).on("click", "#image-input-wrap .remove_btn", function () {
        paintImage("");
    });

    $(document).on("reset", "#notification", function () {
        window.setTimeout(function () {
            $("#zone").val("all").trigger("change");
            paintTargetFact();
            paintMessage();
            paintImage("");
            $("#notification .tps-field .form-control").each(function () {
                paintCounter($(this));
            });
        }, 0);
    });

    $(document).on("change", "#filter_form", function () {
        window.location.href = historyUrl($(this).val());
    });

    $(document).on("submit", "#history_form", function (event) {
        event.preventDefault();
        window.location.href = historyUrl($("#filter_form").val());
    });

    $(document).on("submit", "#notification", function (event) {
        event.preventDefault();

        var form = this;
        var $form = $(form);
        var $button = $form.find('button[type="submit"]');

        Swal.fire({
            title: lang.confirmTitle,
            text: lang.confirmText + ": " + $.trim(selectedTarget().data("label") || ""),
            imageUrl: config.confirmImage,
            imageWidth: 80,
            imageHeight: 80,
            imageAlt: lang.confirmTitle,
            showCancelButton: true,
            showCloseButton: true,
            closeButtonHtml: "&times;",
            cancelButtonColor: "default",
            confirmButtonColor: "primary",
            cancelButtonText: lang.cancel,
            confirmButtonText: lang.send,
            reverseButtons: true
        }).then(function (result) {
            if (!result.value) {
                return;
            }

            $button.prop("disabled", true);

            $.ajaxSetup({
                headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") }
            });

            $.post({
                url: config.submitUrl,
                data: new FormData(form),
                cache: false,
                contentType: false,
                processData: false,
                beforeSend: function () {
                    $("#loading").show();
                },
                success: function (data) {
                    if (data.errors) {
                        $button.prop("disabled", false);

                        for (var i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, { CloseButton: true, ProgressBar: true });
                        }

                        return;
                    }

                    toastr.success(config.successMessage, { CloseButton: true, ProgressBar: true });

                    setTimeout(function () {
                        window.location.href = config.historyUrl;
                    }, 2000);
                },
                error: function () {
                    $button.prop("disabled", false);
                },
                complete: function () {
                    $("#loading").hide();
                }
            });
        });
    });

    $(document).on("show.bs.modal", "#notification-view-modal", function (event) {
        var $button = $(event.relatedTarget);
        var $modal = $(this);
        var image = $button.data("image");

        $modal.find('[data-view="title"]').text($button.data("title"));
        $modal.find('[data-view="description"]').text($button.data("description"));
        $modal.find('[data-view="zone"]').text($button.data("zone"));
        $modal.find('[data-view="target"]').text($button.data("audience"));
        $modal.find('[data-view="sent"]').text($button.data("sent"));
        $modal.find(".ntf-push").toggleClass("has-image", Boolean(image))
            .find('[data-view="image"]').attr("src", image || "");
    });

    var editing = {};

    function paintOffcanvas() {
        var $container = $("#image-input-u").closest(".upload-file_custom");
        var $overlay = $container.find(".overlay");

        $("#notification_title_u").val(editing.title).trigger("input");
        $("#description_u").val(editing.description).trigger("input");
        $("#zone_u").val(editing.zone ? editing.zone : "all").trigger("change");
        $("#tergat_u").val(editing.target);

        if (editing.image) {
            $container.find(".upload-file-img").attr("src", editing.image).show();
            $container.find(".upload-file-textbox").hide();
            $container.addClass("input-disabled");
            $overlay.addClass("show");
            $container.find(".remove_btn").css("opacity", 1);
            return;
        }

        $container.find(".upload-file-img").hide().attr("src", "");
        $container.find(".upload-file-textbox").show();
        $container.removeClass("input-disabled");
        $overlay.removeClass("show");
        $container.find(".remove_btn").css("opacity", 0);
    }

    $(document).on("click", ".edit-btn", function () {
        var $button = $(this);

        editing = {
            id: $button.data("id"),
            title: $button.data("title"),
            description: $button.data("description"),
            image: $button.data("raw-image"),
            zone: $button.data("zone-id"),
            target: $button.data("tergat")
        };

        paintOffcanvas();

        $("#update-notification-form").attr("action", config.updateUrl.replace("__id__", editing.id));
    });

    $(document).on("click", "#update_reset_btn", paintOffcanvas);
})();

"use strict";

(function () {
    var config = window.bannerFormConfig || {};
    var lang = config.lang || {};

    function zoneValue() {
        var value = $("#zone").val();

        return value ? value : "";
    }

    function selectedType() {
        return $('input[name="banner_type"]:checked').val();
    }

    function optionText($select) {
        var value = $select.val();

        if (!value || value === "0") {
            return "";
        }

        return $.trim($select.find("option:selected").first().text());
    }

    function paintFact(key, value) {
        $('[data-preview-fact="' + key + '"]').text(value || lang.notSet);
    }

    function paintLinkFact() {
        var type = selectedType();

        if (type === "store_wise") {
            paintFact("link", optionText($("#store_id")));
            return;
        }

        if (type === "item_wise") {
            paintFact("link", optionText($("#choice_item")));
            return;
        }

        paintFact("link", $.trim($("#default_link").val() || ""));
    }

    function paintTarget() {
        var type = selectedType();

        $("#store_wise").toggle(type === "store_wise");
        $("#item_wise").toggle(type === "item_wise");
        $("#default").toggle(type === "default");

        paintLinkFact();
    }

    function loadItems(selectedId) {
        if (!config.itemSourceUrl) {
            return;
        }

        var url = config.itemSourceUrl + "?module_id=" + config.moduleId;
        var zone = zoneValue();

        if (zone) {
            url += "&zone_id=" + zone;
        }

        if (selectedId) {
            url += "&data[]=" + selectedId;
        }

        $.get({
            url: url,
            dataType: "json",
            success: function (data) {
                $("#choice_item").empty().append(data.options).trigger("change");
                paintLinkFact();
            }
        });
    }

    function initSelect2() {
        $(".js-select2-custom").each(function () {
            if ($.HSCore && $.HSCore.components && $.HSCore.components.HSSelect2) {
                $.HSCore.components.HSSelect2.init($(this));
            }
        });

        $(".js-data-example-ajax").select2({
            placeholder: config.ownerPlaceholder,
            ajax: {
                url: config.storeSourceUrl,
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term,
                        page: params.page,
                        zone_ids: [zoneValue()],
                        module_id: config.moduleId,
                        include_addon_providers: 1
                    };
                },
                processResults: function (data) {
                    return { results: data };
                }
            }
        });
    }

    $(function () {
        initSelect2();
        paintTarget();
        loadItems(config.selectedItemId);

        $("#zone").on("change", function () {
            $("#store_id").val(null).trigger("change");
            paintFact("zone", optionText($(this)));
            loadItems();
            paintLinkFact();
        });

        $('input[name="banner_type"]').on("change", paintTarget);

        $("#store_id, #choice_item").on("change", paintLinkFact);

        $("#default_link").on("input", paintLinkFact);

        $("[data-banner-title]").on("input", function () {
            paintFact("title", $.trim($(this).val()));
        });
    });

    $(document).on("change", "#banner-image", function (event) {
        var file = event.target.files[0];

        if (!file) {
            return;
        }

        var reader = new FileReader();

        reader.onload = function (e) {
            $("#banner-preview").addClass("has-image").find("img").attr("src", e.target.result);
        };

        reader.readAsDataURL(file);
    });

    $(document).on("reset", "#banner_form", function () {
        if (config.isEdit) {
            window.location.reload();
            return;
        }

        window.setTimeout(function () {
            $("#store_id, #choice_item").val(null).trigger("change");
            $("#zone").trigger("change");
            $("#banner-preview").removeClass("has-image").find("img").removeAttr("src");
            paintTarget();
            paintFact("title", "");
        }, 0);
    });

    $(document).on("submit", "#banner_form", function (event) {
        event.preventDefault();

        var $form = $(this);

        if (!$form.valid()) {
            return false;
        }

        var type = selectedType();

        if (type === "store_wise" && !$("#store_id").val()) {
            toastr.error(lang.selectStore, { CloseButton: true, ProgressBar: true });
            return false;
        }

        if (type === "item_wise" && !optionText($("#choice_item"))) {
            toastr.error(lang.selectItem, { CloseButton: true, ProgressBar: true });
            return false;
        }

        var $button = $form.find('button[type="submit"]');


        $button.prop("disabled", true);

        $.ajaxSetup({
            headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") }
        });

        $.post({
            url: config.submitUrl,
            data: new FormData(this),
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
                    window.location.href = config.redirectUrl;
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
})();

"use strict";

(function () {
    var config = window.reelFormConfig || {};
    var lang = config.lang || {};

    function parseDateRange(value) {
        if (!value) {
            return null;
        }

        var parts = value.split(" - ").map(function (part) {
            return $.trim(part);
        });

        if (parts.length !== 2) {
            return null;
        }

        var start = moment(parts[0], "MM/DD/YYYY", true);
        var end = moment(parts[1], "MM/DD/YYYY", true);

        return start.isValid() && end.isValid() ? { start: start, end: end } : null;
    }

    function clearDateValidation(input) {
        if (window.FormValidation) {
            window.FormValidation.clearError(input);
        }

        $(input).removeClass("is-invalid");
    }

    function paintFact(key, value) {
        $('[data-preview-fact="' + key + '"]').text(value || lang.notSet);
    }

    function paintRunsFact() {
        if ($("#is_always_visible").is(":checked")) {
            paintFact("runs", lang.alwaysVisible);
            return;
        }

        paintFact("runs", $.trim($("#dates").val() || ""));
    }

    function paintActionFact() {
        if (!$("#call-to-action-toggle").is(":checked")) {
            paintFact("action", lang.noAction);
            return;
        }

        var $product = $("#product_id");

        paintFact("action", $product.val() ? $.trim($product.find("option:selected").first().text()) : "");
    }

    function initDateRange() {
        var $dates = $("#dates");

        if (!$dates.length) {
            return;
        }

        var currentValue = $dates.val() || $dates.data("initial-value") || "";
        var range = parseDateRange(currentValue);
        var minMoment = range && range.start.isBefore(moment(), "day")
            ? range.start.clone().startOf("day")
            : moment().startOf("day");

        $dates.daterangepicker({
            drops: "up",
            opens: "right",
            startDate: range ? range.start : moment().startOf("day"),
            endDate: range ? range.end : moment().endOf("day"),
            minDate: minMoment,
            autoUpdateInput: false,
            autoApply: false,
            alwaysShowCalendars: true,
            locale: {
                format: "MM/DD/YYYY",
                cancelLabel: lang.clear
            }
        });

        if (range) {
            $dates.val(currentValue).data("last-value", currentValue);
        }

        $dates.on("apply.daterangepicker", function (event, picker) {
            var value = picker.startDate.format("MM/DD/YYYY") + " - " + picker.endDate.format("MM/DD/YYYY");

            $(this).val(value).data("last-value", value);
            clearDateValidation(this);
            paintRunsFact();
        });

        $dates.on("cancel.daterangepicker", function () {
            $(this).val("").data("last-value", "");
            paintRunsFact();
        });
    }

    function syncAlwaysVisible(isInitialLoad) {
        var $dates = $("#dates");
        var checked = $("#is_always_visible").is(":checked");
        var preserved = $dates.data("last-value") || $dates.data("initial-value") || "";

        if (!isInitialLoad && checked && $dates.val()) {
            $dates.data("last-value", $dates.val());
        }

        $dates.prop("disabled", checked).prop("required", !checked);

        if (checked) {
            if (!isInitialLoad) {
                $dates.val("");
            }
        } else if (!$dates.val() && preserved) {
            $dates.val(preserved);
        }

        clearDateValidation($dates[0]);
        paintRunsFact();
    }

    function syncCallToAction() {
        var enabled = $("#call-to-action-toggle").is(":checked");

        $("#product-select-wrapper").toggle(enabled);
        $("#order-now-btn").toggle(enabled);
        paintActionFact();
    }

    function updateTextCounters() {
        $(".reel-des-textarea").each(function () {
            $(this).closest(".tps-field").find(".text-counting").text($(this).val().length + "/200");
        });
    }

    function loadItems(storeId, selectedId) {
        var $product = $("#product_id");

        if (!$product.length || !config.itemsUrl) {
            paintActionFact();
            return;
        }

        var noneOption = '<option value="">' + lang.selectProduct + "</option>";

        if (!storeId) {
            $product.html(noneOption);

            if ($product.hasClass("select2-hidden-accessible")) {
                $product.val("").trigger("change");
            }

            paintActionFact();
            return;
        }

        $.get(config.itemsUrl, { store_id: storeId }, function (response) {
            var options = noneOption;

            (response.items || []).forEach(function (item) {
                var selected = selectedId && parseInt(selectedId, 10) === parseInt(item.id, 10) ? " selected" : "";

                options += '<option value="' + item.id + '"' + selected + ">" + item.name + "</option>";
            });

            $product.html(options);

            if ($product.hasClass("select2-hidden-accessible")) {
                $product.trigger("change");
            }

            paintActionFact();
        });
    }

    function submitForm(form) {
        var $form = $(form);
        var $button = $form.find('button[type="submit"]');

        $button.prop("disabled", true);

        $.ajaxSetup({
            headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") }
        });

        $.ajax({
            url: $form.attr("action"),
            method: "POST",
            data: new FormData(form),
            processData: false,
            contentType: false,
            beforeSend: function () {
                $("#loading").show();
            },
            success: function (response) {
                if (response.errors && response.errors.length) {
                    $button.prop("disabled", false);

                    response.errors.forEach(function (error) {
                        toastr.error(error.message, { CloseButton: true, ProgressBar: true });
                    });

                    return;
                }

                toastr.success(response.message, { CloseButton: true, ProgressBar: true });

                if (response.redirect) {
                    window.location.href = response.redirect;
                }
            },
            error: function (xhr) {
                $button.prop("disabled", false);

                var response = xhr.responseJSON || {};
                var errors = Array.isArray(response.errors) ? response.errors : [];

                if (errors.length) {
                    errors.forEach(function (error) {
                        toastr.error(error.message, { CloseButton: true, ProgressBar: true });
                    });

                    return;
                }

                toastr.error(response.message || lang.somethingWentWrong, { CloseButton: true, ProgressBar: true });
            },
            complete: function () {
                $("#loading").hide();
            }
        });
    }

    $(function () {
        initDateRange();
        syncAlwaysVisible(true);
        syncCallToAction();
        updateTextCounters();

        $(document).on("change", "#is_always_visible", function () {
            syncAlwaysVisible(false);
        });

        $(document).on("change", "#call-to-action-toggle", syncCallToAction);

        $(document).on("change", "#product_id", paintActionFact);

        $(document).on("input", ".reel-des-textarea", updateTextCounters);

        $(document).on("change", "#store_id", function () {
            loadItems($(this).val(), null);
        });

        $("#reel-form").on("submit", function (event) {
            event.preventDefault();
            submitForm(this);
        });

        $("#reel-form").on("reset", function () {
            window.setTimeout(function () {
                $("#store_id, #product_id").trigger("change.select2");
                loadItems($("#store_id").val(), config.selectedProductId);
                syncAlwaysVisible(true);
                syncCallToAction();
                updateTextCounters();
            }, 0);
        });
    });
})();

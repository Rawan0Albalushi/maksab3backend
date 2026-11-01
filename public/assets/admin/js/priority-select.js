/* ==========================================================================
   Row priority selects — save in place instead of reloading the page
   --------------------------------------------------------------------------
   Four list screens carry the same Normal/Medium/High select per row:

     admin-views/category/partials/_list-main.blade.php
     admin-views/category/partials/_list-sub.blade.php
     admin-views/store-category/partials/_row.blade.php
     vendor-views/store-category/index.blade.php

   All four wrapped it in a GET <form> and each bound its own `change` handler
   that called form.submit(), so setting one row's priority rebuilt the whole
   page — losing the Add form's unsaved state on the category screens, and the
   scroll position on all of them.

   This takes over the request the same way status-toggle.js does, and for the
   same reason: the endpoints already flash a Toastr line and redirect back, so
   `StatusToggleResponse` turns that into JSON for a request carrying the
   inline-update header and no controller had to change.

   Delegated from `document`, so rows injected later by an ajax list swap
   (the shared ajax layer's list region — see §10h) work with no re-binding.

   Loaded from layouts/admin/app.blade.php and layouts/vendor/app.blade.php,
   next to status-toggle.js.
   ========================================================================== */

(function ($) {
    "use strict";

    if (typeof $ === "undefined") {
        return;
    }

    var lang = window.prioritySelectLang || {};

    /* The colour says at a glance which rows were singled out, so it has to
       move with the value — otherwise a row set to High stays grey until the
       next full load and the change reads as ignored. */
    var TONES = {
        0: "text-title",
        1: "text-info",
        2: "text-success"
    };

    function t(key, fallback) {
        return lang[key] || fallback;
    }

    function notify(type, message) {
        if (typeof toastr === "undefined") {
            return;
        }
        (type === "error" || type === "warning" ? toastr.error : toastr.success)(message, {
            CloseButton: true,
            ProgressBar: true
        });
    }

    /* Same scope rule as status-toggle.js: a select in a table row is
       self-contained and saves in place. Anywhere else — a filter select, a
       form field — is left alone unless it opts in. */
    function ajaxEnabled($el) {
        var flag = $el.attr("data-ajax");

        if (flag === "false" || flag === "0") {
            return false;
        }
        if (flag === "true" || flag === "1") {
            return true;
        }

        return $el.closest("table").length > 0;
    }

    function tone($select, value) {
        $.each(TONES, function (key, cls) {
            $select.removeClass(cls);
        });

        if (TONES[value]) {
            $select.addClass(TONES[value]);
        }
    }

    function busy($select, on) {
        $select.toggleClass("priority-select--busy", on).prop("disabled", on);
    }

    function refreshRegions($select) {
        var targets = $select.attr("data-ajax-refresh");

        if (!targets || !window.AppAjax) {
            return;
        }

        window.AppAjax.refresh(targets, { url: $select.attr("data-ajax-refresh-url") });
    }

    /* `previous` is what the select held before this change, kept on the
       element so a refused save can put it back. Read on first use rather than
       rendered into the markup, so the four blades stay as they are. */
    function previousOf($select) {
        var stored = $select.data("previous-priority");

        return typeof stored === "undefined" ? $select.find("option[selected]").val() : stored;
    }

    function send($select) {
        var $form = $select.closest("form");
        var url = $form.attr("action") || $select.data("url");

        if (!url) {
            return;
        }

        var chosen = $select.val();
        var previous = previousOf($select);

        busy($select, true);

        $.ajax({
            url: url,
            method: ($form.attr("method") || "GET").toUpperCase(),
            dataType: "json",
            data: $form.length ? $form.serialize() : { priority: chosen },
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                "X-Inline-Update": "1",
                Accept: "application/json"
            },
            success: function (response) {
                response = response || {};

                if (response.redirect) {
                    window.location.href = response.redirect;
                    return;
                }

                // The controller refused it — it says so by flashing an error.
                if (response.ok === false) {
                    $select.val(previous);
                    tone($select, previous);
                    notify("error", response.message || t("failed", "Priority update failed"));
                    return;
                }

                $select.data("previous-priority", chosen);
                tone($select, chosen);
                notify(response.type || "success", response.message || t("done", "Priority updated successfully"));
                refreshRegions($select);
            },
            error: function (xhr) {
                var payload = xhr.responseJSON || {};

                if (payload.redirect) {
                    window.location.href = payload.redirect;
                    return;
                }

                // Nothing changed server side, so put the select back.
                $select.val(previous);
                tone($select, previous);
                notify("error", payload.message || t("failed", "Priority update failed"));
            },
            complete: function () {
                busy($select, false);
            }
        });
    }

    $(document).on("change", "select.priority-select", function () {
        var $select = $(this);

        if (!ajaxEnabled($select)) {
            $select.closest("form").trigger("submit");
            return;
        }

        send($select);
    });

    /* Read the starting value before the admin can change it, so `previous` is
       right on the first save too. Re-run after an ajax list swap. */
    function seed(root) {
        $(root || document).find("select.priority-select").each(function () {
            var $select = $(this);

            if (typeof $select.data("previous-priority") === "undefined") {
                $select.data("previous-priority", $select.val());
            }
        });
    }

    $(seed);

    window.PrioritySelect = { seed: seed };
})(jQuery);

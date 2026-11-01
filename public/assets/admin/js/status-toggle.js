/* ==========================================================================
   Status switches — flip in place instead of reloading the page
   --------------------------------------------------------------------------
   Every list screen in admin and vendor carries row switches that publish an
   item, block a customer, feature a store. They came in three flavours, all of
   which navigated away and rebuilt the whole page to move one switch:

     .redirect-url                       common.js sends the browser to data-url
     .status_change_alert                per-page Swal, then location.href
     .status_form_alert                  per-page Swal, then submits #<data-id>
     .dynamic-checkbox[data-type=status] confirm modal, then submits <id>_form

   All three point at a URL that already encodes the target state, so this file
   keeps each screen's confirm step exactly as it was and only takes over the
   request: fetch the URL in the background, then repaint the one switch that
   changed. `StatusToggleResponse` (PHP) answers those requests with the JSON
   this needs, so no status controller had to change.

   Scope — `ajaxEnabled()` below. Switches inside a table flip in place; a
   settings-page master switch usually gates other fields and still wants its
   full submit, so it keeps the old behaviour unless it opts in with
   `data-ajax="true"`. `data-ajax="false"` opts out either way.

   Loaded from layouts/admin/app.blade.php and layouts/vendor/app.blade.php,
   AFTER common.js — it hands `window.StatusToggle` to the two handlers there.
   ========================================================================== */

(function ($) {
    "use strict";

    if (typeof $ === "undefined") {
        return;
    }

    var lang = window.statusToggleLang || {};

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

    /* Row switches are self-contained, so they flip in place. Anything outside
       a table is left alone: on settings screens the reload is often what
       reveals or hides the fields the switch controls. */
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

    function owns(el) {
        if (!el) {
            return false;
        }

        var $el = $(el);

        if (!$el.is('input[type="checkbox"].toggle-switch-input')) {
            return false;
        }
        if (!$el.is('.redirect-url, .status_change_alert, .status_form_alert, .dynamic-checkbox[data-type="status"], [data-status-toggle]')) {
            return false;
        }

        return ajaxEnabled($el);
    }

    /* Most of these URLs name the target state in the last path segment
       (".../status/12/0"), so the next flip is the same URL with the other
       value. A handful of endpoints instead flip whatever they find (Rental's
       `status-by-store` does `$store->status = !$store->status`) and end in a
       record id — rewriting one of those would point the switch at a different
       row, so the segment has to be the state we just asked for before it is
       touched. `.../status/7` and `.../status/12` fall through untouched; the
       ids that could still read as a state carry `data-url-fixed`. */
    function flipUrl(url, requested) {
        if (!url) {
            return null;
        }

        var segment = new RegExp("/" + requested + "(?=$|\\?|#)");

        if (segment.test(url)) {
            return url.replace(segment, "/" + (requested ? 0 : 1));
        }

        var param = new RegExp("([?&]status=)" + requested + "(?=$|&|#)");

        if (param.test(url)) {
            return url.replace(param, "$1" + (requested ? 0 : 1));
        }

        return null;
    }

    function busy($input, on) {
        var $target = $input.closest(".status-toggle");

        if (!$target.length) {
            $target = $input.closest("label.toggle-switch");
        }

        $target.toggleClass("status-toggle--busy", on);
        $input.prop("disabled", on);
    }

    /* Paint the switch to `state` and move the URL/label with it. `requested`
       is the state the admin asked for, which is what a status-encoded URL is
       still pointing at — see flipUrl. */
    function settle($input, $form, state, requested) {
        var $wrap = $input.closest(".status-toggle");

        $input.prop("checked", state === 1).val(state);

        if ($wrap.length) {
            var label = state ? $input.attr("data-label-on") : $input.attr("data-label-off");

            $wrap.attr("data-status", state);
            $wrap.find(".status-toggle__text").text(label || (state ? t("on", "Active") : t("off", "Inactive")));
        }

        // Reverted, or the server landed somewhere else: nothing moved, so the
        // URL still points where it should.
        if (state !== requested) {
            return;
        }

        if ($input.attr("data-method") !== undefined || $input.attr("data-url-fixed") !== undefined) {
            return;
        }

        // GET-shaped switches carry the next target in the URL; keep it in step.
        var url = $form && $form.length ? $form.attr("action") : $input.data("url");
        var flipped = flipUrl(url, requested);

        if (flipped === null) {
            return;
        }

        if ($form && $form.length) {
            $form.attr("action", flipped);
        } else {
            $input.attr("data-url", flipped).data("url", flipped);
        }
    }

    function refreshRegions($input) {
        var targets = $input.attr("data-ajax-refresh");

        if (!targets || !window.AppAjax) {
            return;
        }

        window.AppAjax.refresh(targets, { url: $input.attr("data-ajax-refresh-url") });
    }

    function send($input, $form) {
        var requested = $input.is(":checked") ? 1 : 0;
        var method = ($input.attr("data-method") || ($form && $form.length ? $form.attr("method") : "") || "GET").toUpperCase();
        var url = $form && $form.length ? $form.attr("action") : $input.data("url");

        if (!url) {
            return;
        }

        busy($input, true);

        $.ajax({
            url: url,
            method: method,
            dataType: "json",
            // Form-backed switches send whatever the form holds — `_token` and
            // `_method` included. The rest carry their target state in the URL,
            // except the POST-shaped ones, which name it in the body.
            data: $form && $form.length
                ? $form.serialize()
                : (method === "POST" ? { status: requested } : {}),
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                "X-Status-Toggle": "1",
                Accept: "application/json"
            },
            success: function (response) {
                response = response || {};

                if (response.redirect) {
                    window.location.href = response.redirect;
                    return;
                }

                // The controller can refuse the change ("the last active zone
                // cannot be turned off") — it says so by flashing an error.
                if (response.ok === false) {
                    settle($input, $form, requested ? 0 : 1, requested);
                    notify("error", response.message || t("failed", "Status update failed"));
                    return;
                }

                var applied = typeof response.status === "undefined"
                    ? requested
                    : parseInt(response.status, 10);

                settle($input, $form, applied, requested);
                notify(response.type || "success", response.message || t("done", "Status updated successfully"));
                refreshRegions($input);
            },
            error: function (xhr) {
                var payload = xhr.responseJSON || {};

                if (payload.redirect) {
                    window.location.href = payload.redirect;
                    return;
                }

                // Nothing changed server side, so put the switch back.
                settle($input, $form, requested ? 0 : 1, requested);
                notify("error", payload.message || t("failed", "Status update failed"));
            },
            complete: function () {
                busy($input, false);
            }
        });
    }

    /* Ask first, when the switch carries something to ask. Directional text
       (`data-confirm-on` / `-off`) wins over the single `data-message` the
       older screens use; a switch with neither just fires. */
    function confirmThen($input, run) {
        var text = $input.is(":checked")
            ? $input.data("confirm-on")
            : $input.data("confirm-off");

        text = text || $input.data("message");

        if (!text) {
            run();
            return;
        }

        if (typeof Swal === "undefined") {
            if (window.confirm(text)) {
                run();
            } else {
                $input.prop("checked", !$input.is(":checked"));
            }
            return;
        }

        Swal.fire({
            title: $input.data("title") || t("confirm_title", "Are you sure?"),
            text: text,
            type: "warning",
            showCancelButton: true,
            cancelButtonColor: "default",
            confirmButtonColor: "#FC6A57",
            cancelButtonText: t("no", "No"),
            confirmButtonText: t("yes", "Yes"),
            reverseButtons: true
        }).then(function (result) {
            if (result.value) {
                run();
            } else {
                $input.prop("checked", !$input.is(":checked"));
            }
        });
    }

    /* The click that flipped the box has already run; common.js bails on these
       (see its `.redirect-url` handler) so nothing navigates.

       `.status_form_alert` names its form in `data-id` rather than carrying a
       `data-url`, so the request goes through that form's action. On the screens
       that still bind their own click handler for it — zone/index, where a
       delete link shares the class — that handler preventDefaults, the box never
       flips, and this never fires. */
    $(document).on(
        "change",
        'input.toggle-switch-input.redirect-url, input.toggle-switch-input.status_change_alert,' +
        ' input.toggle-switch-input.status_form_alert, input.toggle-switch-input[data-status-toggle]',
        function () {
            var $input = $(this);

            if (!owns(this)) {
                return;
            }

            var $form = $input.is(".status_form_alert") ? $("#" + $input.data("id")) : null;

            confirmThen($input, function () {
                send($input, $form);
            });
        }
    );

    window.StatusToggle = {
        owns: owns,
        /* Used by common.js's `.confirm-Status-Toggle` handler: that flow has
           already confirmed through the modal and flipped the box, so it comes
           straight here with the form that would have been submitted. */
        submitForm: function ($input, $form) {
            send($input, $form);
        }
    };
})(jQuery);

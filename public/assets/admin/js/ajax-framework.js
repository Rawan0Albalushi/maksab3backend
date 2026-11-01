(function ($, window, document) {
    "use strict";

    if (typeof $ === "undefined") {
        return;
    }

    var ACTION_HEADER = "X-Ajax-Request";
    var FRAGMENT_HEADER = "X-Ajax-Fragment";

    var lang = window.appAjaxLang || {};
    var mounts = [];

    function t(key, fallback) {
        return lang[key] || fallback;
    }

    function headers(extra) {
        return $.extend({
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json, text/html"
        }, extra || {});
    }

    function notify(type, message) {
        if (!message || typeof toastr === "undefined") {
            return;
        }

        var fn = (type === "error" || type === "danger") ? toastr.error
            : (type === "warning" ? toastr.warning
                : (type === "info" ? toastr.info : toastr.success));

        fn(message, { CloseButton: true, ProgressBar: true });
    }

    function resolve($origin, spec) {
        if (!spec) {
            return $();
        }

        spec = String(spec).trim();

        if (spec === "self") {
            return $origin;
        }
        if (spec.indexOf("closest:") === 0) {
            return $origin.closest($.trim(spec.slice(8)));
        }
        if (spec.indexOf("find:") === 0) {
            return $origin.find($.trim(spec.slice(5)));
        }

        try {
            return $(spec);
        } catch (e) {
            return $();
        }
    }

    function list(value) {
        if (!value) {
            return [];
        }
        if ($.isArray(value)) {
            return value;
        }

        return $.map(String(value).split(","), function (part) {
            return $.trim(part) || null;
        });
    }

    function attrData($el, name) {
        var raw = $el.attr("data-ajax-" + name);

        if (!raw) {
            return {};
        }

        try {
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function opt($el, name, fallback) {
        var value = $el.attr("data-ajax-" + name);

        return typeof value === "undefined" ? fallback : value;
    }

    function truthy(value) {
        return value === "" || value === "true" || value === "1" || value === true;
    }

    function mount(root) {
        var $root = root ? $(root) : $(document);

        if ($.fn.tooltip) {
            $root.find('[data-toggle="tooltip"]').tooltip();
        }
        if ($.fn.popover) {
            $root.find('[data-toggle="popover"]').popover();
        }

        if ($.HSCore && $.HSCore.components && $.HSCore.components.HSSelect2) {
            $root.find("select.js-select2-custom").each(function () {
                if (!$(this).hasClass("select2-hidden-accessible")) {
                    $.HSCore.components.HSSelect2.init($(this));
                }
            });
        }

        if (window.PrioritySelect && window.PrioritySelect.seed) {
            window.PrioritySelect.seed($root);
        }

        if (window.HSUnfold) {
            $root.find(".js-hs-unfold-invoker:not([data-hs-unfold-invoker])").each(function () {
                new window.HSUnfold($(this)).init();
            });
        }

        $root.find(".onerror-image").off("error.appAjax").on("error.appAjax", function () {
            $(this).attr("src", $(this).data("onerror-image"));
        });

        $.each(mounts, function (_, fn) {
            try {
                fn($root);
            } catch (e) {
                if (window.console) {
                    window.console.error("AppAjax mount handler failed", e);
                }
            }
        });

        $root.trigger("ajax:mounted");
    }

    function busyForm($form, on, text) {
        var id = $form.attr("id");
        var $buttons = $form.find('button[type="submit"], input[type="submit"]');

        if (id) {
            $buttons = $buttons.add('[form="' + id + '"][type="submit"]');
        }

        $form.toggleClass("ajax-busy", on);

        $buttons.each(function () {
            var $button = $(this);

            if (on) {
                if (typeof $button.data("ajax-label") === "undefined") {
                    $button.data("ajax-label", $button.is("input") ? $button.val() : $button.html());
                }

                var label = '<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>'
                    + (text || t("working", "Working..."));

                if ($button.is("input")) {
                    $button.val(text || t("working", "Working..."));
                } else {
                    $button.html(label);
                }
            } else {
                var original = $button.data("ajax-label");

                if (typeof original !== "undefined") {
                    if ($button.is("input")) {
                        $button.val(original);
                    } else {
                        $button.html(original);
                    }
                    $button.removeData("ajax-label");
                }
            }

            $button.prop("disabled", on);
        });
    }

    function busyTrigger($trigger, on) {
        $trigger.toggleClass("ajax-busy", on);

        if ($trigger.is("button, input")) {
            $trigger.prop("disabled", on);
        } else {
            $trigger.toggleClass("disabled", on).attr("aria-busy", on ? "true" : null);
        }
    }

    function busyRegion($region, on) {
        $region.toggleClass("ajax-region--busy", on).attr("aria-busy", on ? "true" : null);
    }

    function passesLocalValidation(form) {
        var $form = $(form);

        if ($.fn.validate && $form.data("validator") && !$form.valid()) {
            return false;
        }

        if (window.FormValidation && !window.FormValidation.validateForm(form)) {
            window.FormValidation.notifyFirstInvalid(form);
            return false;
        }

        if (window.fileValidators && window.FileUploadValidator) {
            var owned = window.fileValidators.filter(function (validator) {
                return form.contains(validator.input);
            });

            if (owned.length && !window.FileUploadValidator.validateAll(owned)) {
                return false;
            }
        }

        if (!form.noValidate && typeof form.checkValidity === "function" && !form.checkValidity()) {
            return false;
        }

        return true;
    }

    function clearFieldErrors(form) {
        if (!form) {
            return;
        }

        $(form).find(".form-validation-error").remove();
        $(form).find(".is-invalid").removeClass("is-invalid");
    }

    function findField(form, field) {
        if (!form) {
            return null;
        }

        var bracketed = String(field).replace(/\.(\w+)/g, "[$1]");
        var candidates = [field, field + "[]", bracketed, bracketed + "[]"];

        for (var i = 0; i < candidates.length; i++) {
            var found = form.querySelector('[name="' + candidates[i].replace(/"/g, '\\"') + '"]');

            if (found) {
                return found;
            }
        }

        return null;
    }

    function messageOf(value) {
        if (typeof value === "string") {
            return value;
        }
        if ($.isArray(value)) {
            return value.length ? messageOf(value[0]) : null;
        }
        if (value && typeof value === "object") {
            return value.message || value.msg || null;
        }

        return null;
    }

    function showErrors(form, errors) {
        var first = null;

        $.each(errors, function (field, messages) {
            var message = messageOf(messages);

            if (!message) {
                return;
            }

            var input = /^\d+$/.test(String(field)) ? null : findField(form, field);

            if (input && window.FormValidation) {
                window.FormValidation.showError(input, message);
                $(input).addClass("is-invalid");
            }

            if (!first) {
                first = { input: input, message: message };
            }
        });

        if (!first) {
            return;
        }

        if (first.input && window.showFieldErrorToast) {
            window.showFieldErrorToast($(first.input), first.message);
        } else {
            notify("error", first.message);
        }
    }

    function swap($region, markup) {
        $region.html(markup);
        mount($region);
        $region.trigger("ajax:refreshed");
    }

    function applyMap(map, mode) {
        if (!map) {
            return;
        }

        $.each(map, function (selector, markup) {
            var $target = $(selector);

            if (!$target.length) {
                return;
            }

            if (mode === "append") {
                $target.append($($.parseHTML(String(markup))));
                mount($target);
            } else if (mode === "prepend") {
                $target.prepend($($.parseHTML(String(markup))));
                mount($target);
            } else {
                swap($target, markup);
            }
        });
    }

    function regionUrl($region, override) {
        return override
            || $region.attr("data-ajax-url")
            || $region.attr("data-ajax-refresh-url")
            || window.location.href;
    }

    function refresh(targets, options) {
        options = options || {};

        var groups = [];
        var index = {};

        var wanted = (targets instanceof jQuery || (targets && targets.nodeType))
            ? $(targets).toArray()
            : list(targets);

        $.each(wanted, function (_, target) {
            var isSelector = typeof target === "string";
            var $region = isSelector ? $(target).first() : $(target);

            if (!$region.length) {
                return;
            }

            var selector = isSelector
                ? target
                : ($region.attr("id") ? "#" + $region.attr("id") : null);

            var url = regionUrl($region, options.url);

            if (!index[url]) {
                index[url] = { url: url, entries: [] };
                groups.push(index[url]);
            }

            index[url].entries.push({ selector: selector, $el: $region });
        });

        return $.when.apply($, $.map(groups, function (group) {
            return fetchInto(group.url, group.entries);
        }));
    }

    function fetchInto(url, entries) {
        var selectors = $.map(entries, function (entry) {
            return entry.selector || null;
        }).join(",");

        $.each(entries, function (_, entry) {
            busyRegion(entry.$el, true);
        });

        return $.ajax({
            url: url,
            method: "GET",
            dataType: "html",
            headers: headers((function () {
                var extra = {};
                extra[FRAGMENT_HEADER] = selectors;
                return extra;
            })())
        }).done(function (html, status, xhr) {
            var type = xhr.getResponseHeader("Content-Type") || "";

            if (type.indexOf("json") > -1) {
                var payload = {};

                try {
                    payload = typeof html === "string" ? JSON.parse(html) : html;
                } catch (e) {
                    payload = {};
                }

                applyMap(payload.fragments);
                return;
            }

            var doc;

            try {
                doc = new window.DOMParser().parseFromString(html, "text/html");
            } catch (e) {
                doc = null;
            }

            $.each(entries, function (_, entry) {
                var found = null;

                if (doc && entry.selector) {
                    try {
                        found = doc.querySelector(entry.selector);
                    } catch (e) {
                        found = null;
                    }
                }

                if (found) {
                    swap(entry.$el, found.innerHTML);
                    return;
                }

                if (entries.length === 1) {
                    swap(entry.$el, html);
                }
            });
        }).fail(function () {
            notify("error", t("refresh_failed", "Could not refresh this section"));
        }).always(function () {
            $.each(entries, function (_, entry) {
                busyRegion(entry.$el, false);
            });
        });
    }

    function navigateRegion($region, url) {
        $region.attr("data-ajax-url", url);

        if (truthy(opt($region, "history", "false")) && window.history && window.history.pushState) {
            window.history.pushState({}, "", url);
        }

        return refresh($region, { url: url });
    }

    function applyEnvelope(payload, ctx) {
        payload = payload || {};

        if (payload.redirect) {
            window.location.href = payload.redirect;
            return false;
        }

        if (payload.ok === false) {
            if (payload.errors) {
                showErrors(ctx.form, payload.errors);
            } else {
                notify(payload.type || "error", payload.message || t("failed", "Action failed"));
            }

            return false;
        }

        notify(payload.type || "success", payload.message);

        applyMap(payload.fragments);
        applyMap(payload.append, "append");
        applyMap(payload.prepend, "prepend");

        $.each(list(payload.remove), function (_, selector) {
            $(selector).fadeOut(200, function () {
                $(this).remove();
            });
        });

        var $origin = ctx.$origin;

        var removeSpec = opt($origin, "remove");
        if (removeSpec) {
            resolve($origin, removeSpec).fadeOut(200, function () {
                $(this).remove();
            });
        }

        if (ctx.form && (truthy(opt($origin, "reset", "false")) || payload.reset)) {
            ctx.form.reset();
            clearFieldErrors(ctx.form);
            $(ctx.form).find("select.select2-hidden-accessible").val(null).trigger("change");
            var validator = $(ctx.form).data("validator");
            if (validator) {
                validator.resetForm();
            }
        }

        var closeSpec = opt($origin, "close") || payload.close;
        if (closeSpec) {
            var $closable = resolve($origin, closeSpec);

            if ($closable.hasClass("modal") && $.fn.modal) {
                $closable.modal("hide");
            } else {
                $closable.removeClass("open show");
                $("#offcanvasOverlay").removeClass("show");
                $("body").removeClass("modal-open");
            }
        }

        var targets = list(opt($origin, "refresh")).concat(list(payload.refresh));

        if (targets.length) {
            refresh(targets, { url: opt($origin, "refresh-url") });
        }

        var scrollSpec = opt($origin, "scroll");
        if (scrollSpec) {
            var $scroll = resolve($origin, scrollSpec).first();

            if ($scroll.length) {
                $scroll[0].scrollIntoView({ behavior: "smooth", block: "start" });
            }
        }

        if (truthy(opt($origin, "reload", "false")) || payload.reload) {
            window.location.reload();
            return true;
        }

        var redirectTo = opt($origin, "redirect");

        if (!redirectTo && truthy(opt($origin, "follow-redirect", "false"))) {
            redirectTo = payload.redirect_to;
        }

        if (redirectTo) {
            window.location.href = redirectTo;
        }

        return true;
    }

    function perform(ctx) {
        var $origin = ctx.$origin;
        var before = $.Event("ajax:before");

        $origin.trigger(before, [ctx]);

        if (before.isDefaultPrevented()) {
            return $.Deferred().reject().promise();
        }

        if (ctx.form) {
            clearFieldErrors(ctx.form);
            busyForm($(ctx.form), true, opt($origin, "busy-text"));
        }
        if (!$origin.is("form")) {
            busyTrigger($origin, true);
        }

        var settings = {
            url: ctx.url,
            method: ctx.method,
            dataType: "json",
            headers: headers((function () {
                var extra = {};
                extra[ACTION_HEADER] = "1";
                return extra;
            })()),
            data: ctx.data
        };

        if (ctx.data instanceof window.FormData) {
            settings.processData = false;
            settings.contentType = false;
        }

        return $.ajax(settings)
            .done(function (payload) {
                if (applyEnvelope(payload, ctx) !== false) {
                    $origin.trigger("ajax:success", [payload, ctx]);
                }
            })
            .fail(function (xhr) {
                var payload = xhr.responseJSON || {};

                if (payload.redirect) {
                    window.location.href = payload.redirect;
                    return;
                }

                if (xhr.status === 422 && payload.errors) {
                    showErrors(ctx.form, payload.errors);
                } else if (xhr.status === 419 || xhr.status === 401) {
                    notify("error", t("expired", "Your session has expired. Please sign in again."));
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 1500);
                } else if (xhr.status !== 0) {
                    notify("error", payload.message || t("failed", "Action failed"));
                }

                $origin.trigger("ajax:error", [payload, xhr, ctx]);
            })
            .always(function () {
                if (ctx.form) {
                    busyForm($(ctx.form), false);
                }
                if (!$origin.is("form")) {
                    busyTrigger($origin, false);
                }

                $origin.trigger("ajax:complete", [ctx]);
            });
    }

    function confirmThen($origin, run, cancelled) {
        var message = opt($origin, "confirm");

        if (!message) {
            run();
            return;
        }

        if (typeof Swal === "undefined") {
            if (window.confirm(message)) {
                run();
            } else if (cancelled) {
                cancelled();
            }
            return;
        }

        var config = {
            title: opt($origin, "confirm-title") || t("confirm_title", "Are you sure?"),
            text: message,
            showCancelButton: true,
            cancelButtonColor: "default",
            confirmButtonColor: "#FC6A57",
            cancelButtonText: opt($origin, "confirm-no") || t("no", "No"),
            confirmButtonText: opt($origin, "confirm-yes") || t("yes", "Yes"),
            reverseButtons: true
        };

        var image = opt($origin, "confirm-image");

        if (image) {
            config.imageUrl = image;
            config.imageWidth = 80;
            config.imageHeight = 80;
            config.imageAlt = "";
        } else {
            config.type = "warning";
        }

        Swal.fire(config).then(function (result) {
            if (result.value) {
                run();
            } else if (cancelled) {
                cancelled();
            }
        });
    }

    function submit(form, options) {
        options = options || {};

        var element = form instanceof jQuery ? form[0] : form;

        if (!element) {
            return $.Deferred().reject().promise();
        }

        var $form = $(element);

        if (options.validate !== false && !passesLocalValidation(element)) {
            return $.Deferred().reject().promise();
        }

        var data = new window.FormData(element);

        $.each(options.data || attrData($form, "data"), function (key, value) {
            data.append(key, value);
        });

        return perform({
            $origin: options.origin ? $(options.origin) : $form,
            form: element,
            url: options.url || $form.attr("action") || window.location.href,
            method: (options.method || $form.attr("method") || "POST").toUpperCase(),
            data: data
        });
    }

    function action(options) {
        options = options || {};

        var $origin = options.origin ? $(options.origin) : $(document);
        var method = (options.method || "POST").toUpperCase();
        var data = options.data || {};

        if (method !== "GET" && method !== "POST") {
            data = $.extend({ _method: method }, data);
            method = "POST";
        }

        if (method === "POST") {
            data = $.extend({ _token: $('meta[name="csrf-token"]').attr("content") }, data);
        }

        return perform({
            $origin: $origin,
            form: options.form || null,
            url: options.url,
            method: method,
            data: data
        });
    }

    $(document).on("submit", "form[data-ajax-form]", function (event) {
        if (event.isDefaultPrevented()) {
            return;
        }

        event.preventDefault();

        var $form = $(this);
        var element = this;

        confirmThen($form, function () {
            submit(element);
        });
    });

    $(document).on("click", "[data-ajax-action]", function (event) {
        event.preventDefault();

        var $trigger = $(this);

        if ($trigger.hasClass("ajax-busy") || $trigger.prop("disabled")) {
            return;
        }

        confirmThen($trigger, function () {
            action({
                origin: $trigger,
                url: opt($trigger, "action"),
                method: opt($trigger, "method", "POST"),
                data: attrData($trigger, "data")
            });
        });
    });

    $(document).on("click", "[data-ajax-target]", function (event) {
        event.preventDefault();

        var $trigger = $(this);
        var $form = $(opt($trigger, "target"));

        if (!$form.length) {
            return;
        }

        confirmThen($trigger, function () {
            submit($form, { origin: $trigger });
        });
    });

    $(document).on("click", "[data-ajax-region] a", function (event) {
        var $link = $(this);
        var $region = $link.closest("[data-ajax-region]");
        var selector = $region.attr("data-ajax-links");
        var href = $link.attr("href");

        if (!selector || !$link.is(selector)) {
            return;
        }
        if (!href || href === "#" || href.indexOf("javascript:") === 0) {
            return;
        }
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.which === 2) {
            return;
        }

        event.preventDefault();
        navigateRegion($region, href);
    });

    $(document).on("submit", "[data-ajax-region] form", function (event) {
        var $form = $(this);
        var $region = $form.closest("[data-ajax-region]");
        var selector = $region.attr("data-ajax-forms");

        if (!selector || !$form.is(selector)) {
            return;
        }

        event.preventDefault();

        var base = ($form.attr("action") || regionUrl($region)).split("?")[0];
        var query = $form.serialize();

        navigateRegion($region, query ? base + "?" + query : base);
    });

    window.AppAjax = {
        submit: submit,
        action: action,
        request: perform,
        refresh: refresh,
        navigate: navigateRegion,
        mount: mount,
        onMount: function (fn) {
            if (typeof fn === "function") {
                mounts.push(fn);
            }
        },
        notify: notify,
        showErrors: showErrors,
        applyEnvelope: applyEnvelope
    };
})(jQuery, window, document);

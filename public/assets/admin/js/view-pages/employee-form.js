"use strict";

$(function () {
    const $form = $("#employee_form");

    if (!$form.length) {
        return;
    }

    const $identity = $form.find(".empf-identity");
    const $role = $("#role_id");
    const $photo = $("#employee_image");
    const $dropzone = $photo.closest(".empf-photo");
    const passwordChecks = {
        length: (value) => value.length >= 8,
        lower: (value) => /[a-z]/.test(value),
        upper: (value) => /[A-Z]/.test(value),
        number: (value) => /\d/.test(value),
        symbol: (value) => /[^A-Za-z0-9\s]/.test(value),
    };

    function validateField(element) {
        if ($form.data("validator")) {
            $form.validate().element(element);
        }
    }

    function paint(field, value, fallback) {
        $identity
            .find(`[data-preview="${field}"]`)
            .text(value || fallback)
            .toggleClass("is-empty", !value);
    }

    function renderIdentity() {
        const name = [$("#f_name").val(), $("#l_name").val()]
            .map((part) => (part || "").trim())
            .filter(Boolean)
            .join(" ");
        const phone = ($("#phone").val() || "").trim();
        const hasNumber = phone.replace(/\D/g, "").length >= 6;

        paint("name", name, $identity.data("emptyName"));
        paint("email", ($("#email").val() || "").trim(), $identity.data("emptyValue"));
        paint("phone", hasNumber ? phone : "", $identity.data("emptyValue"));
    }

    function renderRole() {
        const roleId = $role.val() || "";

        paint("role", roleId ? $role.find("option:selected").text().trim() : "", $identity.data("emptyRole"));

        $form.find(".empf-access").each(function () {
            this.hidden = String($(this).data("role")) !== roleId;
        });
    }

    function renderPasswordRules() {
        const value = $("#password").val() || "";

        $("#password_rules [data-rule]").each(function () {
            $(this).toggleClass("is-met", passwordChecks[$(this).data("rule")](value));
        });
    }

    function renderPhoto(file) {
        const $image = $dropzone.find(".empf-photo__img");

        if (!file || !/^image\//.test(file.type)) {
            $image.removeAttr("src");
            $dropzone.removeClass("has-image");
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            $image.attr("src", event.target.result);
            $dropzone.addClass("has-image");
        };
        reader.readAsDataURL(file);
    }

    $form.on("input change keyup", "#f_name, #l_name, #email, #phone", renderIdentity);
    $form.on("input", "#password", renderPasswordRules);

    $role.on("change", function () {
        renderRole();
        validateField(this);
    });

    $photo.on("change", function () {
        renderPhoto(this.files && this.files[0]);
        validateField(this);
    });

    $photo.on("dragenter dragover", () => $dropzone.addClass("is-dragover"));
    $photo.on("dragleave drop", () => $dropzone.removeClass("is-dragover"));

    $form.on("reset", function () {
        setTimeout(function () {
            $form.find(".tps-toggle-secret").each(function () {
                if ($($(this).data("target")).attr("type") === "text") {
                    $(this).trigger("click");
                }
            });

            $role.trigger("change.select2");
            renderPhoto(null);
            renderIdentity();
            renderRole();
            renderPasswordRules();

            if ($form.data("validator")) {
                $form.validate().resetForm();
            }
        }, 0);
    });

    renderIdentity();
    renderRole();
    renderPasswordRules();
});

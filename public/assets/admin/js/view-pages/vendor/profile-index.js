"use strict";

$(function () {
    const $profileForm = $("#vendor-profile-form");
    const $passwordForm = $("#vendor-password-form");

    if (!$profileForm.length) {
        return;
    }

    const $identity = $(".vpf-identity");
    const $photo = $("#vpf_image");
    const $avatar = $("#vpf_avatar");
    const $photoNote = $("#vpf_photo_note");
    const $password = $("#password");
    const $confirm = $("#confirm_password");
    const $match = $("#vpf_match");
    const originalAvatar = $avatar.attr("src");
    const maxBytes = Number($photo.data("maxSize")) * 1024 * 1024;
    const passwordChecks = {
        length: (value) => value.length >= 8,
        lower: (value) => /[a-z]/.test(value),
        upper: (value) => /[A-Z]/.test(value),
        number: (value) => /\d/.test(value),
        symbol: (value) => /[^A-Za-z0-9\s]/.test(value),
    };

    function paint(field, value) {
        $identity
            .find(`[data-preview="${field}"]`)
            .text(value || $identity.data("emptyValue"))
            .toggleClass("is-empty", !value);
    }

    function renderIdentity() {
        const name = [$("#f_name").val(), $("#l_name").val()]
            .map((part) => (part || "").trim())
            .filter(Boolean)
            .join(" ");
        const phone = ($("#phone").val() || "").trim();

        paint("name", name);
        paint("email", ($("#email").val() || "").trim());
        paint("phone", phone.replace(/\D/g, "").length >= 6 ? phone : "");
    }

    function renderPhoto(file) {
        if (!file) {
            $avatar.attr("src", originalAvatar);
            $photoNote.prop("hidden", true);
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            $avatar.attr("src", event.target.result);
            $photoNote.prop("hidden", false);
        };
        reader.readAsDataURL(file);
    }

    function photoError(file) {
        if (!/^image\//.test(file.type)) {
            return $photo.data("invalidType");
        }

        return maxBytes && file.size > maxBytes ? $photo.data("invalidSize") : "";
    }

    function renderPasswordRules() {
        const value = $password.val() || "";

        $("#vpf_password_rules [data-rule]").each(function () {
            $(this).toggleClass("is-met", passwordChecks[$(this).data("rule")](value));
        });
    }

    function renderMatch() {
        const confirm = $confirm.val() || "";

        $match.prop("hidden", !confirm || confirm !== ($password.val() || ""));
    }

    function resetValidation($form) {
        if ($form.data("validator")) {
            $form.validate().resetForm();
        }

        $form.find(".is-invalid").removeClass("is-invalid");
    }

    $photo.on("change", function () {
        const file = this.files && this.files[0];
        const error = file ? photoError(file) : "";

        if (error) {
            toastr.error(error);
            this.value = "";
            renderPhoto(null);
            return;
        }

        renderPhoto(file);
    });

    $profileForm.on("input change keyup", "#f_name, #l_name, #email, #phone", renderIdentity);

    $profileForm.on("reset", function () {
        setTimeout(function () {
            renderPhoto(null);
            renderIdentity();
            resetValidation($profileForm);
        }, 0);
    });

    if ($passwordForm.data("validator")) {
        $confirm.rules("add", {
            equalTo: "#password",
            messages: { equalTo: $confirm.data("mismatch") },
        });
    }

    $password.on("input", function () {
        renderPasswordRules();
        renderMatch();
    });

    $confirm.on("input", function () {
        renderMatch();

        if ($passwordForm.data("validator") && ($confirm.val() || "").length >= ($password.val() || "").length) {
            $passwordForm.validate().element(this);
        }
    });

    $passwordForm.on("reset", function () {
        setTimeout(function () {
            $passwordForm.find(".tps-toggle-secret").each(function () {
                if ($($(this).data("target")).attr("type") === "text") {
                    $(this).trigger("click");
                }
            });

            renderPasswordRules();
            renderMatch();
            resetValidation($passwordForm);
        }, 0);
    });

    renderIdentity();
    renderPasswordRules();
});

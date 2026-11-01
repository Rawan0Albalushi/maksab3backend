"use strict";

(function () {
    var config = window.vendorBannerFormConfig || {};

    function paintFact(key, value) {
        $('[data-preview-fact="' + key + '"]').text(value || config.notSet);
    }

    $(function () {
        $("[data-banner-title]").on("input", function () {
            paintFact("title", $.trim($(this).val()));
        });

        $("#default_link").on("input", function () {
            paintFact("link", $.trim($(this).val()));
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
            $("#banner-preview").removeClass("has-image").find("img").removeAttr("src");
            paintFact("title", "");
            paintFact("link", "");
        }, 0);
    });
})();

"use strict";
$(document).ready(function() {

    // INITIALIZATION OF SELECT2
    // =======================================================
    $('.js-select2-custom').each(function () {
        let select2 = $.HSCore.components.HSSelect2.init($(this));
    });

    let zone_id = [];
    $('#zone_ids').on('change', function(){
        if($(this).val())
        {
            zone_id = $(this).val();
        }
        else
        {
            zone_id = [];
        }
    });

    $('.refund-filter').on('change', function(){
        window.location.href = $(this).val();
    });

    // Opening/closing the filter panel is handled globally by filter-drawer.js.

    // INITIALIZATION OF TAGIFY
    // =======================================================
    $('.js-tagify').each(function () {
        let tagify = $.HSCore.components.HSTagify.init($(this));
    });

    $("#date_from").on("change", function () {
        $('#date_to').attr('min',$(this).val());
    });

    $("#date_to").on("change", function () {
        $('#date_from').attr('max',$(this).val());
    });
});

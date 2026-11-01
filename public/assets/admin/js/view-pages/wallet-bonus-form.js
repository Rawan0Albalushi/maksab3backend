"use strict";

(function ($) {
    var config = window.walletBonusConfig || {};
    var currency = config.currency || { symbol: '', position: 'left', decimals: 2 };
    var lang = config.lang || {};

    var $type = $('.js-bonus-type');
    var $amount = $('#bonus_amount');
    var $minimum = $('#minimum_add_amount');
    var $maximum = $('#maximum_bonus_amount');
    var $from = $('#date_from');
    var $to = $('#date_to');

    function isPercentage() {
        return $('.js-bonus-type:checked').val() !== 'amount';
    }

    function money(value) {
        var amount = Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: currency.decimals,
            maximumFractionDigits: currency.decimals
        });

        return currency.position === 'right'
            ? amount + ' ' + currency.symbol
            : currency.symbol + ' ' + amount;
    }

    function syncType() {
        var percentage = isPercentage();

        $('#bonus_unit').text(percentage ? '(%)' : '(' + currency.symbol + ')');
        $amount.attr('max', percentage ? 100 : 999999999999.99);
        $maximum.prop('disabled', !percentage).prop('required', percentage);
        $('#maximum_bonus_req').toggleClass('d-none', !percentage);

        if (!percentage) {
            $maximum.val('');
        }
    }

    function syncPreview() {
        var $preview = $('#bonus_preview');

        if (!$preview.length) {
            return;
        }

        var percentage = isPercentage();
        var value = Number($amount.val()) || 0;
        var minimum = Number($minimum.val()) || 0;
        var cap = Number($maximum.val()) || 0;
        var bonus = percentage ? (minimum * value) / 100 : value;

        if (percentage && cap > 0) {
            bonus = Math.min(bonus, cap);
        }

        $('#preview_add').text(money(minimum));
        $('#preview_bonus').text('+ ' + money(bonus));
        $('#preview_total').text(money(minimum + bonus));

        var $cap = $('#preview_cap');

        if (percentage && cap > 0 && value > 0) {
            $cap.text((lang.capReached || '') + ': ' + money((cap * 100) / value)).removeClass('d-none');
        } else {
            $cap.addClass('d-none');
        }
    }

    function sync() {
        syncType();
        syncPreview();
    }

    $type.on('change', sync);
    $amount.add($minimum).add($maximum).on('input change', syncPreview);

    if (config.startsToday) {
        $from.attr('min', new Date().toISOString().split('T')[0]);
        $to.attr('min', new Date().toISOString().split('T')[0]);
    }

    if ($from.val()) {
        $to.attr('min', $from.val());
    }

    if ($to.val()) {
        $from.attr('max', $to.val());
    }

    $from.on('change', function () {
        $to.attr('min', $(this).val());
    });

    $to.on('change', function () {
        $from.attr('max', $(this).val());
    });

    $('#reset_btn').on('click', function () {
        window.setTimeout(sync, 0);
    });

    $(sync);
}(jQuery));

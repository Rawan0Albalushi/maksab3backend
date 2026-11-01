"use strict";

$('[data-date-range-select]').on('change', function () {
    const isCustom = $(this).val() === 'custom';
    const $fields = $(this).closest('[data-date-range]').find('[data-custom-date]');
    $fields.prop('hidden', !isCustom);
    $fields.find('input').prop({ disabled: !isCustom, required: isCustom });
});

$('#from_date,#to_date').change(function() {
    let fr = $('#from_date').val();
    let to = $('#to_date').val();
    if (fr != '' && to != '') {
        if (fr > to) {
            $('#from_date').val('');
            $('#to_date').val('');
            toastr.error('Invalid date range!', Error, {
                CloseButton: true,
                ProgressBar: true
            });
        }
    }

})

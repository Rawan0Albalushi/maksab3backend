<script>
    "use strict";

    (function () {
        // The hint under the type cards changes with the choice: "all store" states who bears the
        // cost, "specific criteria" explains the amount field it reveals.
        const HINTS = {
            [@json(\App\Models\FreeDelivery::TYPE_ALL)]: @json(translate('Free delivery is active for all stores. Cost bearer for the free delivery is admin.')),
            [@json(\App\Models\FreeDelivery::TYPE_CRITERIA)]: @json(translate('messages.Set the minimum order amount required to qualify for free delivery.')),
        };
        const CRITERIA = @json(\App\Models\FreeDelivery::TYPE_CRITERIA);

        function syncType() {
            const type = $('.free-delivery-type-input:checked').val();
            const isCriteria = type === CRITERIA;
            $('.rule-method').removeClass('is-selected');
            $('.free-delivery-type-input:checked').closest('.rule-method').addClass('is-selected');
            $('#free-delivery-type-hint').text(HINTS[type] || '');
            $('#minimum-amount-wrapper').toggleClass('d-none', !isCriteria);
            // Required only while it is the field this setup actually uses — all_store ignores
            // it either way, and a hidden required input blocks the browser's own submit.
            $('#minimum_order_amount').prop('required', isCriteria);
        }

        $(document).on('change', '.free-delivery-type-input', syncType);
        syncType();

        // Display only — Parcel isn't blocked from this picker or its save, so this just tells
        // the admin up front that the setup has no effect at parcel checkout (see _form.blade.php).
        function syncParcelWarning() {
            const hasParcel = $('#module_ids option:selected[data-module-type="parcel"]').length > 0;
            $('#parcel-fd-warning').toggleClass('d-none', !hasParcel);
        }

        $(document).on('change', '#module_ids', syncParcelWarning);
        syncParcelWarning();

        // Changing zone re-narrows the module picker: only modules connected to that zone and not
        // already covered by another setup. Built from route(), never a hand-written path.
        $(document).on('change', '#zone_id', function () {
            const zoneId = $(this).val();
            if (!zoneId) { return; }

            $.get('{{ route('admin.business-settings.zone.free-delivery.zone-modules', ['zoneId' => '__ZONE__']) }}'
                    .replace('__ZONE__', zoneId),
                { setup_id: {{ $setup?->id ?? 'null' }} },
                function (data) {
                    const $select = $('#module_ids');
                    $select.empty();
                    (data.modules || []).forEach(function (m) {
                        $select.append($('<option>').val(m.id).attr('data-module-type', m.type).text(m.name));
                    });
                    $select.trigger('change');

                    const none = (data.modules || []).length === 0;
                    $('#modules-all-configured').toggleClass('d-none', !none)
                        .find('span').text(data.modules_empty_message);
                });
        });

        // One error at a time, in field order — the same rule the delivery-rule form follows.
        const REQUIRED = {
            zone: @json(translate('messages.Please select a zone')),
            modules: @json(translate('messages.Please select at least one module')),
            type: @json(translate('messages.Please select a free delivery type')),
            minimumOrderAmount: @json(translate('Enter the minimum order amount for specific criteria, or switch the type to all store')),
        };

        $(document).on('submit', '#free-delivery-form', function (e) {
            let problem = null;

            if (!$('#zone_id').val()) {
                problem = { el: $('#zone_id'), message: REQUIRED.zone };
            } else if (!($('#module_ids').val() || []).length) {
                problem = { el: $('#module_ids'), message: REQUIRED.modules };
            } else if (!$('.free-delivery-type-input:checked').val()) {
                problem = { el: $('.free-delivery-type-input').first(), message: REQUIRED.type };
            } else if ($('.free-delivery-type-input:checked').val() === CRITERIA && !$('#minimum_order_amount').val()) {
                problem = { el: $('#minimum_order_amount'), message: REQUIRED.minimumOrderAmount };
            }

            if (!problem) { return; }

            e.preventDefault();
            toastr.error(problem.message, { CloseButton: true, ProgressBar: true });

            // select2 hides the real <select>, so focus its rendered control instead.
            const $target = problem.el.attr('id') === 'module_ids' || problem.el.attr('id') === 'zone_id'
                ? problem.el.next('.select2').find('.select2-selection')
                : problem.el;

            if ($target.length) {
                $('html, body').animate({ scrollTop: Math.max(0, $target.offset().top - 160) }, 200);
                $target.trigger('focus');
            }

            return false;
        });
    })();
</script>
